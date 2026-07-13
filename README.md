<!--
  - SPDX-FileCopyrightText: 2026 Watermarked shares contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Watermarked shares

Watermarked shares (`files_watermark`) is a standalone app for Nextcloud 34. It adds a compact section to the standard Files sharing sidebar for PDF files. A user can create an ordinary public link either to the original PDF (when watermark text is blank) or to a rasterized derivative saved beside it. Compatibility is deliberately bounded to Nextcloud 34 so later server majors can be tested before they are advertised.

The watermark is baked into full-page RGB JPEG pixels. It is not a selectable or independently removable PDF object, but this is only a best-effort deterrent: no watermark can be guaranteed against reconstruction, screenshots, inpainting, or other AI-assisted removal.

## Requirements

- Nextcloud 34 with `files_sharing` enabled
- PHP 8.2–8.5 with the `intl` and `mbstring` extensions and `proc_open` enabled
- Python 3.10 or newer available locally
- PyMuPDF `>=1.28.0,<1.29.0` under its AGPL license

The app does not install Python or system packages. Administrators provide and maintain the local renderer environment.

## Install as a Nextcloud submodule

The repository includes compiled frontend assets and a production Composer autoloader under `vendor/`, so it can be mounted directly in a Nextcloud `apps/` directory without running Composer or npm on the server. The checkout directory must match the app ID:

```sh
cd /var/www/nextcloud
git submodule add git@github.com:Rubilmax/nextcloud_files_watermark.git apps/files_watermark
php occ app:enable files_watermark
```

After updating the submodule, enable the app normally with `occ`. Build-time dependencies are not needed on the Nextcloud host.

## Renderer setup

From the app directory, create a local virtual environment with a sufficiently recent Python:

```sh
python3.10 -m venv .venv
.venv/bin/python -m pip install --upgrade pip
.venv/bin/pip install -r requirements.txt
```

In **Administration settings → Watermark settings**, set **Python executable** to the absolute virtual-environment executable, for example:

```text
/var/www/nextcloud/apps/files_watermark/.venv/bin/python
```

The same setting can be applied with `occ`:

```sh
php occ config:app:set files_watermark python_executable --value="/var/www/nextcloud/apps/files_watermark/.venv/bin/python"
```

Run `php occ setupchecks` to verify `proc_open`, the executable, PyMuPDF's pinned version range, and all configured bounds.

## Administration settings

| Setting | Default | Allowed range |
| --- | ---: | ---: |
| Python executable | `python3` | non-empty executable name or path |
| Raster DPI | 180 | 96–300 |
| Maximum source size | 50 MiB | 1–1024 MiB |
| Maximum pages | 200 | 1–5000 |
| Timeout | 120 seconds | 10–3600 seconds |

Invalid stored values are reported by the setup check and clamped to safe defaults at runtime.

## How generation works

1. The authenticated OCS endpoint verifies that the current user-visible path and file ID still refer to the same readable PDF and that its parent accepts new files.
2. The source is streamed through Nextcloud's Node API into `ITempManager` storage. No data-directory path is accessed, so remote/object storage and transparent server-side encryption remain supported.
3. PyMuPDF renders the original page and annotations, adds dense staggered Unicode watermark tiles at about 20% opacity and 30°, then rebuilds the page from a quality-88 RGB JPEG at the configured DPI.
4. The result is streamed back through the Node API as `<original> - <watermark>.pdf`; invalid filename characters, the 240-byte UTF-8 limit, and collisions are handled before creation.
5. The browser creates an ordinary `shareType=3` link through Nextcloud's OCS Share API, so core password, expiration, permission, and policy checks remain authoritative.

Output deliberately contains no searchable text, forms, links, annotations, layers, attachments, JavaScript, metadata, or digital signatures. Rasterization reduces accessibility and may substantially increase file size. Password-encrypted PDFs are rejected; Nextcloud server-side encryption is transparent to the Node stream and is supported.

To prevent malformed or unusually large page geometries from exhausting server memory, a single rendered page is limited to 50 million pixels and 32,768 pixels on either axis. Lowering the configured DPI can bring large-format pages under these hard safety limits.

If share creation fails after rendering, the derivative is retained and the UI offers retry and standard generated-file sharing settings. Custom tokens are set from those standard settings because core does not accept them during link creation.

## API

```text
POST /ocs/v2.php/apps/files_watermark/api/v1/watermarks
OCS-APIRequest: true
```

Request:

```json
{ "sourceId": "42", "sourcePath": "/Reports/File.pdf", "text": "Confidential" }
```

Successful OCS data:

```json
{
  "id": "84",
  "path": "/Reports/File - Confidential.pdf",
  "name": "File - Confidential.pdf",
  "mime": "application/pdf",
  "size": 123456
}
```

The endpoint is authenticated, CSRF-protected, and limited to five requests per user per minute. It returns distinct codes for stale paths/IDs, permissions, MIME, source size, unsafe page dimensions, encrypted/malformed PDFs, page limits, missing dependencies, and timeouts.

## Development

Frontend development follows the Nextcloud 34 toolchain and requires Node.js 24 with npm 11.

```sh
composer install
npm ci
npm run lint
npm run typecheck
npm run test:frontend
npm run build
composer test
composer analyse

# Rebuild the committed production Composer autoloader before packaging
composer run build:vendor

python3.10 -m venv .venv
.venv/bin/pip install -r requirements-dev.txt
.venv/bin/pytest tests/python
```

For an installed Nextcloud development server, also run `php occ setupchecks` and exercise the integration cases against local, remote, and encrypted storage. Nextcloud's old `app:check-code` command has been obsolete since Nextcloud 21; PHPStan and the Nextcloud 34 OCP package provide the relevant static API checks here.
