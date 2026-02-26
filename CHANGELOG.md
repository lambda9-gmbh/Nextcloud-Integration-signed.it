# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html),
and the requirements of the [Nextcloud Appstore Metadata specification](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#changelog).

## 1.0.0

Initial release of the signd.it Integration for Nextcloud.

### Added

- Admin settings: API key entry, login, registration with price display and ToS/privacy links
- File action "Digitally sign" in the Files context menu for PDF files
- Sidebar tab showing signing processes per file with status, signer list, and actions
- Start signing wizard via signd.it (opens external wizard, creates DB entry)
- Resume and cancel draft wizard processes from the sidebar
- Manual refresh and PDF download for finished processes
- Duplicate prevention on PDF download with filename counter (`contract_signed.pdf`, `contract_signed_2.pdf`, ...)
- Download fallback to user root directory when original file directory was deleted
- Overview page with process list, filters (status, search, date range, only mine), sortable columns, and pagination
- Detail sidebar in overview with refresh, cancel, and download actions
- Shared signer list component used in both sidebar tab and overview page
- Link from Files sidebar to overview page ("Show all processes")
- Background cleanup job for orphaned DB entries (removes processes whose original file was deleted)
- Instance scoping via stable `ncInstanceId` (independent of access URL)
- Translations: en, de, es, fr, it, pt, da, pl (91 strings)
- App logo and consistent "signd.it" branding
- Docker Compose dev environment with multi-version support (NC 30-32)
- PHPUnit, Vitest, and Playwright test suites
