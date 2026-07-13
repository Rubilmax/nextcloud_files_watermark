#!/usr/bin/env python3

# SPDX-FileCopyrightText: 2026 Watermarked shares contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Rasterize a PDF into full-page JPEG images with a baked-in watermark."""

from __future__ import annotations

import json
import math
import re
import sys
from pathlib import Path
from typing import Any, NoReturn

EXIT_ENCRYPTED = 10
EXIT_MALFORMED = 11
EXIT_PAGE_LIMIT = 12
EXIT_BAD_REQUEST = 13
EXIT_PAGE_TOO_LARGE = 14
EXIT_DEPENDENCY = 20

MIN_VERSION = (1, 28, 0)
MAX_VERSION = (1, 29, 0)
MAX_PAGE_PIXELS = 50_000_000
MAX_PIXEL_DIMENSION = 32_768
WATERMARK_ANGLE = 30.0


def fail(exit_code: int, code: str, message: str) -> NoReturn:
    """Write one machine-readable, bounded error and exit."""
    payload = json.dumps(
        {"code": code, "message": message[:2000]},
        ensure_ascii=False,
        separators=(",", ":"),
    )
    print(payload, file=sys.stderr)
    raise SystemExit(exit_code)


try:
    import pymupdf
except Exception as exception:  # pragma: no cover - executed without dependency
    fail(EXIT_DEPENDENCY, "dependency_unavailable", f"PyMuPDF is unavailable: {exception}")


def version_tuple(version: str) -> tuple[int, int, int]:
    numbers = [int(value) for value in re.findall(r"\d+", version)[:3]]
    return tuple((numbers + [0, 0, 0])[:3])  # type: ignore[return-value]


if not MIN_VERSION <= version_tuple(pymupdf.__version__) < MAX_VERSION:
    fail(
        EXIT_DEPENDENCY,
        "unsupported_pymupdf",
        f"PyMuPDF {pymupdf.__version__} is outside the supported >=1.28.0,<1.29.0 range.",
    )


def read_config() -> dict[str, Any]:
    try:
        config = json.load(sys.stdin)
    except (json.JSONDecodeError, UnicodeError) as exception:
        fail(EXIT_BAD_REQUEST, "invalid_config", f"Invalid renderer configuration: {exception}")

    if not isinstance(config, dict):
        fail(EXIT_BAD_REQUEST, "invalid_config", "Renderer configuration must be an object.")

    text = config.get("text")
    dpi = config.get("dpi")
    max_pages = config.get("maxPages")
    quality = config.get("jpegQuality")
    if not isinstance(text, str) or not text or len(text) > 128:
        fail(EXIT_BAD_REQUEST, "invalid_text", "Watermark text must contain 1 to 128 characters.")
    if type(dpi) is not int or not 96 <= dpi <= 300:
        fail(EXIT_BAD_REQUEST, "invalid_dpi", "Raster DPI must be from 96 to 300.")
    if type(max_pages) is not int or not 1 <= max_pages <= 5000:
        fail(EXIT_BAD_REQUEST, "invalid_page_limit", "Maximum pages must be from 1 to 5000.")
    if type(quality) is not int or not 1 <= quality <= 100:
        fail(EXIT_BAD_REQUEST, "invalid_quality", "JPEG quality must be from 1 to 100.")

    return config


def load_font() -> pymupdf.Font:
    """Prefer Noto Sans and fall back to MuPDF's universal Unicode font."""
    try:
        return pymupdf.Font(fontname="notos")
    except Exception:
        return pymupdf.Font(fontname="cjk")


def make_watermark_tile(text: str) -> pymupdf.Document:
    font_size = 28.0
    font = load_font()
    text_width = max(font.text_length(text, fontsize=font_size), font_size * 3)
    tile = pymupdf.open()
    tile_page = tile.new_page(width=text_width + 24, height=font_size * 1.8)
    writer = pymupdf.TextWriter(
        tile_page.rect,
        opacity=0.20,
        color=(0.20, 0.20, 0.20),
    )
    writer.append(
        pymupdf.Point(12, font_size * 1.25),
        text,
        font=font,
        fontsize=font_size,
    )
    writer.write_text(
        tile_page,
        opacity=0.20,
        color=(0.20, 0.20, 0.20),
        overlay=True,
    )
    return tile


def add_staggered_watermarks(page: pymupdf.Page, tile: pymupdf.Document) -> None:
    """Cover the page with repeated, staggered tiles rotated by 30 degrees."""
    natural = tile[0].rect
    angle = math.radians(WATERMARK_ANGLE)

    # show_pdf_page fits the *rotated* source into the target rectangle. Using
    # the unrotated aspect ratio here makes long text shrink to sub-pixel size.
    # The exact rotated bounds preserve the 28-point tile at its natural scale.
    target_width = natural.width * abs(math.cos(angle)) + natural.height * abs(math.sin(angle))
    target_height = natural.width * abs(math.sin(angle)) + natural.height * abs(math.cos(angle))
    step_x = max(145.0, target_width + 48.0)
    step_y = 78.0

    row = 0
    y = -target_height + step_y
    while y < page.rect.height:
        x = -(step_x / 2) if row % 2 else 0
        while x < page.rect.width:
            rect = pymupdf.Rect(x, y, x + target_width, y + target_height)
            page.show_pdf_page(
                rect,
                tile,
                pno=0,
                rotate=WATERMARK_ANGLE,
                keep_proportion=True,
                overlay=True,
            )
            x += step_x
        y += step_y
        row += 1


def rasterize(input_path: Path, output_path: Path, config: dict[str, Any]) -> None:
    try:
        source = pymupdf.open(input_path)
    except Exception as exception:
        fail(EXIT_MALFORMED, "malformed_pdf", f"The PDF could not be opened: {exception}")

    with source:
        if source.needs_pass or source.is_encrypted:
            fail(EXIT_ENCRYPTED, "encrypted_pdf", "Password-encrypted PDFs are not supported.")
        try:
            page_count = source.page_count
        except Exception as exception:
            fail(EXIT_MALFORMED, "malformed_pdf", f"The PDF page tree is invalid: {exception}")
        if page_count < 1:
            fail(EXIT_MALFORMED, "empty_pdf", "The PDF has no pages.")
        if page_count > config["maxPages"]:
            fail(
                EXIT_PAGE_LIMIT,
                "page_limit_exceeded",
                f"The PDF has {page_count} pages; the configured limit is {config['maxPages']}.",
            )

        tile = make_watermark_tile(config["text"])
        output = pymupdf.open()
        try:
            for source_page in source:
                visible_rect = source_page.rect
                if visible_rect.is_empty or visible_rect.is_infinite:
                    fail(EXIT_MALFORMED, "invalid_page", "A PDF page has invalid visible dimensions.")

                pixel_width = math.ceil(visible_rect.width * config["dpi"] / 72)
                pixel_height = math.ceil(visible_rect.height * config["dpi"] / 72)
                if (
                    pixel_width > MAX_PIXEL_DIMENSION
                    or pixel_height > MAX_PIXEL_DIMENSION
                    or pixel_width * pixel_height > MAX_PAGE_PIXELS
                ):
                    fail(
                        EXIT_PAGE_TOO_LARGE,
                        "page_too_large",
                        (
                            f"A PDF page would render to {pixel_width} by {pixel_height} pixels; "
                            "reduce the raster DPI or use a smaller page."
                        ),
                    )

                # Render the original and its annotations first. The watermark is then
                # placed above that image, so annotations cannot cover the baked mark.
                original_pixmap = source_page.get_pixmap(
                    dpi=config["dpi"],
                    colorspace=pymupdf.csRGB,
                    alpha=False,
                    annots=True,
                )
                scratch = pymupdf.open()
                try:
                    scratch_page = scratch.new_page(
                        width=visible_rect.width,
                        height=visible_rect.height,
                    )
                    scratch_page.insert_image(
                        scratch_page.rect,
                        pixmap=original_pixmap,
                        keep_proportion=False,
                        overlay=True,
                    )
                    add_staggered_watermarks(scratch_page, tile)
                    marked_pixmap = scratch_page.get_pixmap(
                        dpi=config["dpi"],
                        colorspace=pymupdf.csRGB,
                        alpha=False,
                        annots=False,
                    )
                    jpeg = marked_pixmap.tobytes(
                        "jpeg",
                        jpg_quality=config["jpegQuality"],
                    )
                finally:
                    scratch.close()

                output_page = output.new_page(
                    width=visible_rect.width,
                    height=visible_rect.height,
                )
                output_page.insert_image(
                    output_page.rect,
                    stream=jpeg,
                    keep_proportion=False,
                    overlay=True,
                )

            output.set_metadata({})
            output.save(output_path, garbage=4, deflate=True, clean=True)
        except SystemExit:
            raise
        except Exception as exception:
            fail(EXIT_MALFORMED, "render_failed", f"The PDF could not be rendered: {exception}")
        finally:
            output.close()
            tile.close()


def main() -> None:
    if len(sys.argv) != 3:
        fail(EXIT_BAD_REQUEST, "invalid_arguments", "Expected input and output PDF paths.")

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])
    if not input_path.is_file():
        fail(EXIT_BAD_REQUEST, "missing_input", "Input PDF does not exist.")
    if output_path == input_path:
        fail(EXIT_BAD_REQUEST, "invalid_output", "Output path must differ from input path.")

    config = read_config()
    rasterize(input_path, output_path, config)
    with pymupdf.open(output_path) as output:
        print(json.dumps({"pages": output.page_count}, separators=(",", ":")))


if __name__ == "__main__":
    main()
