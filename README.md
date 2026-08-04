<!--
  - SPDX-FileCopyrightText: 2026 Watermarked shares contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Watermarked shares

Watermarked shares (`files_watermark`) is a standalone app for Nextcloud 34. It adds a compact section to the standard Files sharing sidebar for PDF files. A user can create an ordinary public link either to the original PDF (when watermark text is blank) or to a rasterized derivative saved beside it. Compatibility is deliberately bounded to Nextcloud 34 so later server majors can be tested before they are advertised.

The visible watermark is baked into full-page RGB JPEG pixels with randomized spacing, position, and opacity, plus a blurred layer and optional distortion. After that visible pass, the app embeds a random 256-bit [PixelSeal](https://github.com/facebookresearch/videoseal) message into every page. Neither technique is a guarantee against reconstruction, screenshots, inpainting, heavy image transformations, or other AI-assisted removal; they are complementary, best-effort deterrence and tracing signals.

## Requirements

- Nextcloud 34 with `files_sharing` enabled
- PHP 8.2–8.5 with the `intl` and `mbstring` extensions and `proc_open` enabled
- Python 3.10 or newer available locally
- PyMuPDF `>=1.28.0,<1.29.0` under AGPL, plus NumPy and Pillow, for visible rasterization
- Meta VideoSeal/PixelSeal under MIT, plus PyTorch and TorchVision, for invisible watermarking
- The official PixelSeal checkpoint stored locally at the configured path

The app does not install Python or system packages. Administrators provide and maintain the local renderer environment.

## Installation

### Install the app as a Nextcloud submodule

The repository includes compiled frontend assets and a production Composer autoloader under `vendor/`, so it can be mounted directly in a Nextcloud `apps/` directory without running Composer or npm on the server. The checkout directory must match the app ID:

```sh
cd /var/www/nextcloud
git submodule add git@github.com:Rubilmax/nextcloud_files_watermark.git apps/files_watermark
php occ app:enable files_watermark
```

After updating the submodule, enable the app normally with `occ`. Build-time dependencies are not needed on the Nextcloud host.

### Set up the renderer on a traditional server

The renderer is not bundled with the app. On Debian or Ubuntu, install Python 3.10 or newer and virtual-environment support:

```sh
sudo apt-get update
sudo apt-get install ca-certificates curl git python3 python3-venv
python3 --version
```

Create the renderer virtual environment at the app's default location with a sufficiently recent Python, then install the pinned dependency range from the app directory:

```sh
cd /var/www/nextcloud/apps/files_watermark
sudo python3 -m venv /opt/files-watermark-python
sudo /opt/files-watermark-python/bin/python -m pip install --upgrade pip
sudo /opt/files-watermark-python/bin/python -m pip install -r requirements.txt
sudo install -d -m 0755 /opt/files-watermark-python/models
sudo curl --fail --location \
	--output /opt/files-watermark-python/models/pixelseal.pth \
	https://dl.fbaipublicfiles.com/videoseal/pixelseal/checkpoint.pth
sudo chmod 0644 /opt/files-watermark-python/models/pixelseal.pth
```

The default **Python executable** in **Administration settings → Watermark settings** already points to:

```text
/opt/files-watermark-python/bin/python
```

If an earlier installation stored another value, reset it with `occ`:

```sh
cd /var/www/nextcloud
sudo -u www-data php occ config:app:set files_watermark python_executable --value="/opt/files-watermark-python/bin/python"
```

There is no need to activate the virtual environment. Verify that the PHP/web-server user can execute it:

```sh
sudo -u www-data /opt/files-watermark-python/bin/python \
	-c 'import numpy, pymupdf, torch, videoseal; from PIL import Image; print(pymupdf.__version__, torch.__version__)'
sudo -u www-data test -r /opt/files-watermark-python/models/pixelseal.pth
sudo -u www-data php /var/www/nextcloud/occ setupchecks
```

Replace `www-data` if PHP runs under a different account.

### Set up the renderer with Docker

Do not install Python interactively in a running container: those changes disappear when the container is replaced. Instead, extend the same Nextcloud image variant used by the deployment and bake the renderer into a reproducible image. For example, create `Dockerfile.nextcloud` next to the deployment's Compose file:

```dockerfile
FROM nextcloud:34-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends ca-certificates curl git python3 python3-venv \
	&& python3 -m venv /opt/files-watermark-python \
	&& /opt/files-watermark-python/bin/python -m pip install --no-cache-dir --upgrade pip \
	&& /opt/files-watermark-python/bin/python -m pip install --no-cache-dir \
		"PyMuPDF>=1.28.0,<1.29.0" "numpy>=1.26,<3" "Pillow>=10,<13" \
		"torch>=2.3.1,<3" "torchvision>=0.18,<1" \
		"videoseal @ git+https://github.com/facebookresearch/videoseal.git@870ca7fb33578b90f14c602016b6c2788096226e" \
	&& install -d -m 0755 /opt/files-watermark-python/models \
	&& curl --fail --location \
		--output /opt/files-watermark-python/models/pixelseal.pth \
		https://dl.fbaipublicfiles.com/videoseal/pixelseal/checkpoint.pth \
	&& chmod 0644 /opt/files-watermark-python/models/pixelseal.pth \
	&& rm -rf /var/lib/apt/lists/*
```

Change the `FROM` line if the deployment uses another Debian-based Nextcloud variant, such as `nextcloud:34-fpm`. Then build that Dockerfile from the existing Compose service, retaining its current volumes, environment, networks, and database configuration:

```yaml
services:
  nextcloud:
    build:
      context: .
      dockerfile: Dockerfile.nextcloud
    image: local/nextcloud-files-watermark:34
```

Build and replace the Nextcloud container, then configure the absolute in-image path:

```sh
docker compose build --pull nextcloud
docker compose up -d nextcloud
docker compose exec -u www-data nextcloud php occ config:app:set \
	files_watermark python_executable \
	--value="/opt/files-watermark-python/bin/python"
docker compose exec -u www-data nextcloud \
	/opt/files-watermark-python/bin/python \
	-c 'import numpy, pymupdf, torch, videoseal; from PIL import Image; print(pymupdf.__version__, torch.__version__)'
docker compose exec -u www-data nextcloud php occ setupchecks
```

The renderer is now part of the immutable image rather than container state. Docker can reuse the installation layer between builds, while rebuilding the image deliberately picks up base-image and dependency security updates. The application can also use a renderer sidecar, but that requires an HTTP renderer backend; the current implementation launches only a local executable with PHP `proc_open`.

## Administration settings

| Setting | Default | Allowed range |
| --- | ---: | ---: |
| Python executable | `/opt/files-watermark-python/bin/python` | non-empty executable name or path |
| Raster DPI | 180 | 96–300 |
| Watermark font size | 28 pt | 8–144 pt |
| Watermark color | `#333333` | six-digit hexadecimal RGB color |
| Watermark opacity | 30% | 1–100% |
| Watermark angle | 30° | -180–180° |
| Minimum horizontal interval | 145 pt | 20–2000 pt |
| Horizontal gap | 48 pt | 0–1000 pt |
| Vertical interval | 78 pt | 20–2000 pt |
| Opacity variation | 5 percentage points | 0–50 percentage points |
| Spacing variation | 10% | 0–40% |
| Position jitter | 8 pt | 0–100 pt |
| Blur radius | 6 px | 0–64 px |
| Blur opacity | 80% | 0–100% |
| Distortion | disabled | enabled or disabled |
| Distortion strength | 12 px | 0–128 px |
| PixelSeal | enabled | enabled or disabled |
| PixelSeal checkpoint | `/opt/files-watermark-python/models/pixelseal.pth` | absolute local path |
| PixelSeal strength | 20% | 1–100% |
| PixelSeal device | `auto` | `auto`, `cpu`, `cuda`, or `mps` |
| Maximum source size | 50 MiB | 1–1024 MiB |
| Maximum pages | 200 | 1–5000 |
| Timeout | 120 seconds | 10–3600 seconds |

The settings page includes a live, one-page preview rendered through the same visible pipeline as generated files. The browser displays the rendered page as an image for reliable cross-browser viewing, with a link to open the actual preview PDF. The preview uses a stable seed so refreshes are comparable and deliberately skips PixelSeal because an invisible signal has no useful visual preview and loading the neural model would make administration changes unnecessarily expensive.

The visible approach is based on the French government FiligraneFacile implementation in [`BOPdfDocumentTemplate.java`](https://github.com/MTES-MCT/dossierfacile-backend/blob/develop/dossierfacile-pdf-generator/src/main/java/fr/dossierfacile/api/pdfgenerator/service/templates/BOPdfDocumentTemplate.java) and its sinusoidal [`DFFilter.java`](https://github.com/MTES-MCT/dossierfacile-backend/blob/develop/dossierfacile-pdf-generator/src/main/java/fr/dossierfacile/api/pdfgenerator/service/filters/DFFilter.java). Defaults retain the app's previous visible appearance while adding modest variation and blur; distortion remains opt-in.

Invalid stored values are reported by the setup check and clamped to safe defaults at runtime.

## How generation works

1. The authenticated OCS endpoint verifies that the current user-visible path and file ID still refer to the same readable PDF and that its parent accepts new files.
2. The source is streamed through Nextcloud's Node API into `ITempManager` storage. No data-directory path is accessed, so remote/object storage and transparent server-side encryption remain supported.
3. The app creates a cryptographically random 256-bit identifier for the derivative. That identifier also seeds the visible layout, so each new derivative has a different but internally repeatable pattern.
4. PyMuPDF renders the original page and annotations. The renderer adds configurable staggered Unicode tiles with randomized spacing, position, and opacity, composites a Gaussian-blurred copy, optionally distorts the sharp layer, and flattens the result.
5. PixelSeal embeds the same 256-bit identifier into every visibly watermarked page. The page is then encoded as a quality-88 RGB JPEG and placed alone in a new PDF page.
6. The result is streamed back through the Node API as `<original> - <watermark>.pdf`; invalid filename characters, the 240-byte UTF-8 limit, and collisions are handled before creation.
7. The browser creates an ordinary `shareType=3` link through Nextcloud's OCS Share API, so core password, expiration, permission, and policy checks remain authoritative. It displays the invisible identifier beside the public URL so the owner can retain it for later extraction comparisons.

Output deliberately contains no searchable text, forms, links, annotations, layers, attachments, JavaScript, metadata, or digital signatures. Rasterization reduces accessibility and may substantially increase file size. Password-encrypted PDFs are rejected; Nextcloud server-side encryption is transparent to the Node stream and is supported.

To prevent malformed or unusually large page geometries from exhausting server memory, a single rendered page is limited to 50 million pixels and 32,768 pixels on either axis. Because neural inference needs additional full-page tensors, PixelSeal-enabled pages have a lower 20-million-pixel limit. Lowering the configured DPI can bring large-format pages under these hard safety limits.

PixelSeal inference is substantially heavier than the visible PyMuPDF pass. `auto` chooses CUDA, then Apple MPS, then CPU; set the device explicitly if automatic acceleration is unsuitable. The app never downloads model code or weights during a web request. Generation fails with a renderer-dependency error when PixelSeal is enabled but its package, device, or local checkpoint is unavailable. Administrators can disable PixelSeal while retaining the randomized visible watermark.

The identifier is technically per generated derivative because the current workflow must finish rendering before Nextcloud creates the public-link share. A retry that reuses the retained derivative also reuses its identifier. Creating another derivative creates a new identifier; manually creating several links to the same derivative does not.

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
  "size": 123456,
  "invisibleWatermarkId": "3c12e1b5881c2f91f7b9f92cb423dbd27ae8542be9a6765db036fcbed10310ea"
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
