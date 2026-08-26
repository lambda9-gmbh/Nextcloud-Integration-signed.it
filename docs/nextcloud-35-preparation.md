# Nextcloud 35 compatibility preparation

Status: pre-release review, 2026-08-26

Nextcloud 35 has not been added to the app's declared support range yet. The
declaration and release remain gated on tests with the final Docker image and
on staging.

## Critical changes review

The review is based on Nextcloud's current
[critical changes](https://github.com/nextcloud/documentation/blob/master/developer_manual/release_notes/critical_changes.rst)
and
[deprecations](https://github.com/nextcloud/documentation/blob/master/developer_manual/release_notes/deprecations.rst)
for Nextcloud 35.

| Nextcloud 35 change | Plugin impact | Action |
| --- | --- | --- |
| Add 35 to `info.xml` support range | Required before release | Keep `max-version="34"` until final-image and staging tests pass, then set it to `35` |
| Minimum PHP version is 8.3 | No code change: PHP 8.2 remains necessary for supported NC 33/34 installations | Run NC 35 verification with PHP 8.3 or newer |
| phpseclib 2 to 3 | Not used | None |
| Symfony Console 6 to 7 | No console commands in the app | None |
| MariaDB/MySQL requirements and stricter `GROUP BY` handling | No database-specific SQL, `GROUP BY`, or MD5 query function | Include the staging database in the final verification |
| Removed frontend globals and libraries | None are used; `@nextcloud/files/dav` is an explicitly bundled package API, not the removed global `dav` library | None |
| New public DBAL/schema wrappers | The existing migration already uses `OCP\DB\ISchemaWrapper` and `OCP\DB\Types` | None |
| Removed Remote, preview, calendar/room and autocomplete APIs | Not used | None |
| `IRootFolder` no longer implements the private hooks emitter | The app uses only public `IRootFolder` methods and no hooks | Verify unit tests against the NC 35 OCP package |
| Signed cloud federation notifications | The app has no federation provider | None |
| Newly deprecated MD5 query function, broadcast event and task-processing provider | Not used | None |

## Work completed before the final release

- The development dependency `nextcloud/ocp` targets `dev-stable35` and the
  lockfile pins the reviewed snapshot, so backend tests exercise the NC 35
  public API during the release-candidate phase.
- The source was checked for every removed and newly deprecated API listed
  above.
- The obsolete test stub for the former private `IRootFolder` hooks-emitter
  parent was removed.
- The public README compatibility information was corrected to match the
  currently released app metadata (Nextcloud 33-34, PHP 8.2+).

Verification against the pinned NC 35 OCP snapshot:

- PHPUnit: 130 tests, 270 assertions passed
- Vitest: 153 tests passed
- Production frontend build: passed
- PHP syntax check: passed for all source and test files
- Composer validation: passed
- Composer audit: no known advisories after updating the locked test dependencies

The existing `npm run lint` command cannot currently run because ESLint is not
declared in the project dependencies. This is a general development-tooling
issue, not an NC 35 compatibility failure.

## Final release gate

Once the final Nextcloud 35 Docker image is available:

1. Start a clean environment with `NC_VERSION=35 npm run up`.
2. Build the frontend and enable the app without forcing compatibility.
3. Run PHPUnit, Vitest, and Playwright tests.
4. Manually verify admin setup, starting a signing process, sidebar status,
   process overview, cancellation, and signed-PDF download.
5. Deploy the candidate to staging and repeat the full signing workflow against
   the staging signd.it API.
6. Check the Nextcloud log and browser console for errors and deprecations.
7. Re-read the final NC 35 critical changes in case the guide changed after
   this review.
8. Replace the pre-release `dev-stable35` OCP constraint with the final 35.x
   release, update the lockfile, and repeat the backend tests.
9. Set `appinfo/info.xml` to `max-version="35"`, select the new plugin version,
   update the changelog, and only then create the release tag.
