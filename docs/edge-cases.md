# Edge Cases & Error Scenarios

> Analysis of non-happy-path scenarios for the integration_signd NC app.
> Focus: Data inconsistency between NC DB and signd.it, file operation errors, authorization.
>
> **Completed:** 2026-02-21 — All 13 scenarios analyzed and assessed.
> Required actions have been added to [status.md](status.md) as tasks.

## Status Legend

- ✅ Analyzed & assessed
- 🔧 Fix planned (→ status.md)

---

## 1. Original PDF Deleted After Process Start

**Risk:** Medium
**Category:** Data inconsistency NC ↔ signd.it

**Problem:**
- `oc_integration_signd_processes` entry remains as orphan (no FK on NC file possible, since `oc_filecache` is NC-internal)
- Sidebar tab can no longer be opened → processes only visible via overview
- `download()` needs a target directory for the signed PDF → original directory may no longer exist

**Assessment:**
- The signd.it process itself continues without issues (independent of NC filesystem)
- Deletion on the NC side cannot be reliably intercepted (files can also be manipulated externally in the filesystem)
- Local DB stores `target_dir` (directory of the original at the time of start) → check if directory still exists on download, otherwise fallback (e.g. user root or error message)
- Orphaned DB entries: Cleanup mechanism needed (background job checks if `file_id` still exists)

**Actions:**
- [x] Store `target_dir` on process start
- [x] Download: Fallback when target directory no longer exists
- [x] Cleanup job for orphaned entries (file_id no longer exists in NC)
- [x] Overview shows processes correctly even without associated NC file (link grayed out/removed)

**Status:** ✅ Done

---

## 2. Process Cancelled Directly in signd.it

**Risk:** Low (mitigated by architecture)
**Category:** Status synchronization

**Assessment:**
- Not a real problem: Status is not stored locally but always queried live from the signd API
- As soon as the user opens the overview or views the sidebar tab of a file, they see the current status
- No action needed

**Status:** ✅ Not a problem

---

## 3. Process Finished on signd.it, but NC Doesn't Know

**Risk:** Low (mitigated by architecture)
**Category:** Status synchronization

**Assessment:**
- Same as scenario 2: Status comes live from the API, never stale once the user checks
- Overview page and sidebar tab always show the current state
- No action needed

**Status:** ✅ Not a problem

---

## 4. Duplicate Process Start / Wizard Handling

**Risk:** Low (partially already secured)
**Category:** Race condition / UX

**Current state:**
- `StartProcessButton.vue` already disables the button during the request (`isStarting` flag) → double-click protection in place
- `SignApiService.php` has `resumeWizard()` and `cancelWizard()` implemented, but they are not yet called anywhere

**Assessment:**
- Multiple processes per original file are **deliberately allowed** (e.g. different signer configurations)
- On click, exactly one wizard tab should open — secured by button disable
- When sidebar shows too many processes for a file → link to overview (with file filter preset)

**Actions:**
- [x] Add `resume-wizard` to sidebar: When a running wizard (draft) exists for the file, offer "Resume wizard" instead of "Start new"
- [x] Add `cancel-wizard` to sidebar: Allow cancelling draft processes
- [ ] Sidebar: Link to overview beyond a certain number of processes (with `fileId` filter)
- [ ] Later (NC-internal wizard): Also button disable until backend response

**Status:** 🔧 Fix planned (→ status.md priority 1)

---

## 5. Parallel Download of the Same Signed PDF

**Risk:** Low
**Category:** Race condition / file operations

**Problem:**
- Two requests simultaneously → both download from signd API → possible duplicate (`_signed.pdf` + `_signed_1.pdf`)
- On error between file write and DB update → next download creates duplicate

**Assessment:**
- Normal case is caught: `finishedPdfPath` set → "Already downloaded"
- Worst case: One extra file — harmless, user deletes it
- No data loss, no corrupted state

**Status:** ✅ Accepted residual risk, no action needed

---

## 6. NC Storage Full / Quota Exceeded

**Risk:** Low
**Category:** File operations

**Problem:**
- `download()` fetches PDF from signd API, writing to NC fails due to quota/storage
- PDF data in RAM is lost

**Assessment:**
- Self-healing: Process on signd.it remains FINISHED, user frees up space and clicks download again
- No data loss, no corrupted state

**Actions:**
- [x] Display clear and understandable error message for the user (e.g. "Storage full — please free up space and try again")

**Status:** ✅ Accepted residual risk, error UX implemented

---

## 7. signd.it Unreachable (Temporarily)

**Risk:** Low
**Category:** Network / API errors

**Problem:**
- API calls fail, user sees error messages
- `startWizard()` only creates a draft on the signd side — ghost drafts are automatically deleted after a certain time

**Assessment:**
- No ghost process problem: Only after wizard completion does it become a real process
- Drafts can be viewed/resumed/cancelled in the signd.it UI
- Overview/sidebar show errors instead of data — self-healing once API is reachable again
- No action needed beyond clean error messages

**Status:** ✅ Not a problem

---

## 8. Missing Ownership Checks (Authorization)

**Risk:** Low (deliberate design decision)
**Category:** Security

**Assessment:**
- Currently intentional: One API key per NC instance, all users share the signd account
- All logged-in users can see/manage all processes — matches the current model
- Fine-grained permissions (users/groups) planned as a future feature (see decisions.md)
- No action needed for v1

**Status:** ✅ Deliberately accepted for v1

---

## 9. NC Accessible Behind Different URLs

**Risk:** Medium
**Category:** Data inconsistency / configuration

**Problem:**
- Overview currently filters by `applicationMetaData.ncInstanceUrl` (based on `getAbsoluteURL('/')`)
- URL varies depending on access domain/IP → processes "disappear" from the overview
- App will be in the App Store → no control over how users access their NC

**Solution:**
- Use `ncInstanceId` instead of `ncInstanceUrl` — stable `instanceid` per NC installation (`$config->getSystemValue('instanceid')`, independent of access URL)
- Remove URL entirely from metadata, only use `ncInstanceId` for scoping

**Actions:**
- [x] `apiClientMetaData`: Replace `ncInstanceUrl` with `ncInstanceId`
- [x] Overview filter: Switch `metadataSearch` to `ncInstanceId`

**Status:** ✅ Done

---

## 10. Admin Changes API Key While Processes Are Running

**Risk:** No risk
**Category:** Configuration

**Assessment:**
- API key only serves for authentication, not for process mapping
- As long as the new key belongs to the same signd account, all processes remain accessible
- No action needed

**Status:** ✅ Not a problem

---

## 11. NC User Deleted

**Risk:** No risk
**Category:** Data inconsistency / cleanup

**Assessment:**
- Processes belong to the NC instance, not the individual user
- Other users can still see and manage the processes in the overview
- DB entries should deliberately **not** be cleaned up — the mapping (file ↔ process) remains useful
- `user_id` in the DB is only informational ("who started it"), not functionally critical

**Status:** ✅ Not a problem

---

## 12. signd API Changes Response Format / Unexpected Statuses

**Risk:** Low
**Category:** API compatibility

**Assessment:**
- signd API is only updated without breaking changes
- `interrupted` is an intermediate state resolved in the signd UI (resume or cancel) — not relevant for NC display
- Status is not stored locally but always queried live → no persistence issues
- For display purposes: running / finished / cancelled is sufficient

**Status:** ✅ Not a problem

---

## 13. Large PDFs (Memory)

**Risk:** Low
**Category:** Performance / stability

**Problem:**
- `getFinishedPdf()` loads the entire PDF via `$response->getBody()` into RAM
- For very large documents, PHP `memory_limit` (NC default: 512 MB) could be reached

**Assessment:**
- In signd's runtime history, no problematically large documents have occurred
- Finished PDFs are enriched (protocol, signature images) but remain manageable in size
- If needed: Streaming instead of RAM (Guzzle `sink` option writing directly to NC file stream)
- No action needed currently, streaming can be added if required

**Status:** ✅ Accepted residual risk