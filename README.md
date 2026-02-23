# signd.it Integration

Nextcloud app for integrating with [signd.it](https://signd.it) — digitally sign PDF documents directly from your Nextcloud.

**Compatible with Nextcloud 30, 31, and 32.**

## Features

- **Start PDF signing** — Launch signature processes directly from the file browser context menu
- **Track status** — View signature status in the file sidebar (signers, progress)
- **Download signed PDFs** — Retrieve finished signed documents back into your cloud
- **Process overview** — See all running and completed signature processes at a glance, with filtering, search, and sorting
- **Admin settings** — API key management via manual entry, login, or registration

## Requirements

- Nextcloud 30, 31, or 32
- PHP 8.1+
- A [signd.it](https://signd.it) account (API key)

## Installation

1. Extract the app into your Nextcloud apps directory (`apps/integration_signd/`)
2. Enable the app in the Nextcloud admin panel or via CLI:
   ```bash
   occ app:enable integration_signd
   ```
3. Go to **Administration → signd.it** and enter your API key

## Usage

### Starting a signature process

1. Select a PDF file in the file browser
2. Choose **"Digitally sign"** from the context menu
3. The signd.it wizard opens — configure signers and start the process

### Viewing status

- Open the **signd.it** tab in the file sidebar to see all signature processes for that file
- The link **"Show all processes"** leads to the overview page

### Overview page

- Accessible via the **signd.it** navigation entry
- Lists all signature processes with filters for status, date, search, and "Only mine"
- Detail sidebar with refresh, cancel, and download actions

## License

AGPL-3.0-or-later — see [LICENSE](LICENSE).

## Development

For development setup, build instructions, tests, and architecture details: [docs/development.md](docs/development.md)