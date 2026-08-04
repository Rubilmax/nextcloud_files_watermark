#!/usr/bin/env python3

# SPDX-FileCopyrightText: 2026 Watermarked shares contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Rasterize a PDF into full-page JPEG images with a baked-in watermark."""

from __future__ import annotations

import json
import math
import random
import re
import sys
from io import BytesIO
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

try:
    import numpy as np
    from PIL import Image, ImageFilter
except Exception as exception:  # pragma: no cover - executed without dependency
    fail(
        EXIT_DEPENDENCY,
        "dependency_unavailable",
        f"Pillow or NumPy is unavailable: {exception}",
    )


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
    font_size = config.get("watermarkFontSize")
    color = config.get("watermarkColor")
    opacity = config.get("watermarkOpacityPercent")
    angle = config.get("watermarkAngle")
    minimum_horizontal_interval = config.get("watermarkMinimumHorizontalInterval")
    horizontal_gap = config.get("watermarkHorizontalGap")
    vertical_interval = config.get("watermarkVerticalInterval")
    opacity_variation = config.get("watermarkOpacityVariationPercent")
    spacing_variation = config.get("watermarkSpacingVariationPercent")
    position_jitter = config.get("watermarkPositionJitterPoints")
    blur_radius = config.get("watermarkBlurRadiusPixels")
    blur_opacity = config.get("watermarkBlurOpacityPercent")
    distortion_enabled = config.get("watermarkDistortionEnabled")
    distortion_strength = config.get("watermarkDistortionStrengthPixels")
    random_seed = config.get("randomSeed")
    if not isinstance(text, str) or not text or len(text) > 128:
        fail(EXIT_BAD_REQUEST, "invalid_text", "Watermark text must contain 1 to 128 characters.")
    if type(dpi) is not int or not 96 <= dpi <= 300:
        fail(EXIT_BAD_REQUEST, "invalid_dpi", "Raster DPI must be from 96 to 300.")
    if type(max_pages) is not int or not 1 <= max_pages <= 5000:
        fail(EXIT_BAD_REQUEST, "invalid_page_limit", "Maximum pages must be from 1 to 5000.")
    if type(quality) is not int or not 1 <= quality <= 100:
        fail(EXIT_BAD_REQUEST, "invalid_quality", "JPEG quality must be from 1 to 100.")
    if type(font_size) is not int or not 8 <= font_size <= 144:
        fail(EXIT_BAD_REQUEST, "invalid_font_size", "Watermark font size must be from 8 to 144 points.")
    if not isinstance(color, str) or re.fullmatch(r"#[0-9a-fA-F]{6}", color) is None:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_color",
            "Watermark color must be a six-digit hexadecimal color.",
        )
    if type(opacity) is not int or not 1 <= opacity <= 100:
        fail(EXIT_BAD_REQUEST, "invalid_opacity", "Watermark opacity must be from 1 to 100 percent.")
    if type(angle) is not int or not -180 <= angle <= 180:
        fail(EXIT_BAD_REQUEST, "invalid_angle", "Watermark angle must be from -180 to 180 degrees.")
    if (
        type(minimum_horizontal_interval) is not int
        or not 20 <= minimum_horizontal_interval <= 2000
    ):
        fail(
            EXIT_BAD_REQUEST,
            "invalid_minimum_horizontal_interval",
            "Watermark minimum horizontal interval must be from 20 to 2000 points.",
        )
    if type(horizontal_gap) is not int or not 0 <= horizontal_gap <= 1000:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_horizontal_gap",
            "Watermark horizontal gap must be from 0 to 1000 points.",
        )
    if type(vertical_interval) is not int or not 20 <= vertical_interval <= 2000:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_vertical_interval",
            "Watermark vertical interval must be from 20 to 2000 points.",
        )
    if type(opacity_variation) is not int or not 0 <= opacity_variation <= 50:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_opacity_variation",
            "Watermark opacity variation must be from 0 to 50 percent.",
        )
    if type(spacing_variation) is not int or not 0 <= spacing_variation <= 40:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_spacing_variation",
            "Watermark spacing variation must be from 0 to 40 percent.",
        )
    if type(position_jitter) is not int or not 0 <= position_jitter <= 100:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_position_jitter",
            "Watermark position jitter must be from 0 to 100 points.",
        )
    if type(blur_radius) is not int or not 0 <= blur_radius <= 64:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_blur_radius",
            "Watermark blur radius must be from 0 to 64 pixels.",
        )
    if type(blur_opacity) is not int or not 0 <= blur_opacity <= 100:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_blur_opacity",
            "Watermark blur opacity must be from 0 to 100 percent.",
        )
    if type(distortion_enabled) is not bool:
        fail(EXIT_BAD_REQUEST, "invalid_distortion", "Watermark distortion must be enabled or disabled.")
    if type(distortion_strength) is not int or not 0 <= distortion_strength <= 128:
        fail(
            EXIT_BAD_REQUEST,
            "invalid_distortion_strength",
            "Watermark distortion strength must be from 0 to 128 pixels.",
        )
    if not isinstance(random_seed, str) or re.fullmatch(r"[0-9a-f]{64}", random_seed) is None:
        fail(EXIT_BAD_REQUEST, "invalid_random_seed", "Random seed must contain exactly 256 bits.")

    return config


def load_font() -> pymupdf.Font:
    """Prefer Noto Sans and fall back to MuPDF's universal Unicode font."""
    try:
        return pymupdf.Font(fontname="notos")
    except Exception:
        return pymupdf.Font(fontname="cjk")


def color_from_hex(value: str) -> tuple[float, float, float]:
    """Convert a validated six-digit hexadecimal color into PyMuPDF RGB values."""
    return (
        int(value[1:3], 16) / 255,
        int(value[3:5], 16) / 255,
        int(value[5:7], 16) / 255,
    )


def make_watermark_tile(
    text: str,
    config: dict[str, Any],
    opacity_percent: float,
) -> pymupdf.Document:
    font_size = float(config["watermarkFontSize"])
    opacity = opacity_percent / 100
    color = color_from_hex(config["watermarkColor"])
    font = load_font()
    text_width = max(font.text_length(text, fontsize=font_size), font_size * 3)
    tile = pymupdf.open()
    tile_page = tile.new_page(width=text_width + 24, height=font_size * 1.8)
    writer = pymupdf.TextWriter(
        tile_page.rect,
        opacity=opacity,
        color=color,
    )
    writer.append(
        pymupdf.Point(12, font_size * 1.25),
        text,
        font=font,
        fontsize=font_size,
    )
    writer.write_text(
        tile_page,
        opacity=opacity,
        color=color,
        overlay=True,
    )
    return tile


def add_staggered_watermarks(
    page: pymupdf.Page,
    tile: pymupdf.Document,
    config: dict[str, Any],
    rng: random.Random,
) -> None:
    """Cover the page with repeated, randomly offset watermark tiles."""
    natural = tile[0].rect
    watermark_angle = config["watermarkAngle"]
    angle = math.radians(watermark_angle)

    # show_pdf_page fits the *rotated* source into the target rectangle. Using
    # the unrotated aspect ratio here makes long text shrink to sub-pixel size.
    # The exact rotated bounds preserve the configured tile at its natural scale.
    target_width = natural.width * abs(math.cos(angle)) + natural.height * abs(math.sin(angle))
    target_height = natural.width * abs(math.sin(angle)) + natural.height * abs(math.cos(angle))
    base_step_x = max(
        float(config["watermarkMinimumHorizontalInterval"]),
        target_width + config["watermarkHorizontalGap"],
    )
    base_step_y = float(config["watermarkVerticalInterval"])
    spacing_variation = config["watermarkSpacingVariationPercent"] / 100
    step_x = base_step_x * rng.uniform(1 - spacing_variation, 1 + spacing_variation)
    step_y = base_step_y * rng.uniform(1 - spacing_variation, 1 + spacing_variation)
    jitter = float(config["watermarkPositionJitterPoints"])

    row = 0
    y = -target_height + step_y + rng.uniform(-jitter, jitter)
    while y < page.rect.height:
        x = (-(step_x / 2) if row % 2 else 0) + rng.uniform(-jitter, jitter)
        while x < page.rect.width:
            jitter_x = rng.uniform(-jitter, jitter)
            jitter_y = rng.uniform(-jitter, jitter)
            rect = pymupdf.Rect(
                x + jitter_x,
                y + jitter_y,
                x + jitter_x + target_width,
                y + jitter_y + target_height,
            )
            page.show_pdf_page(
                rect,
                tile,
                pno=0,
                rotate=watermark_angle,
                keep_proportion=True,
                overlay=True,
            )
            x += step_x
        y += step_y
        row += 1


def image_from_pixmap(pixmap: pymupdf.Pixmap) -> Image.Image:
    """Convert a PyMuPDF pixmap into an unpremultiplied Pillow image."""
    channels = pixmap.n
    rows = np.frombuffer(pixmap.samples, dtype=np.uint8).reshape(pixmap.height, pixmap.stride)
    pixels = rows[:, : pixmap.width * channels].reshape(pixmap.height, pixmap.width, channels).copy()
    if pixmap.alpha:
        alpha = pixels[:, :, 3:4].astype(np.float32)
        rgb = np.where(
            alpha > 0,
            np.minimum(255, pixels[:, :, :3].astype(np.float32) * 255 / np.maximum(alpha, 1)),
            0,
        )
        pixels[:, :, :3] = rgb.astype(np.uint8)
        return Image.fromarray(pixels, "RGBA")
    return Image.fromarray(pixels[:, :, :3], "RGB")


def distort_watermark_layer(layer: Image.Image, strength: int) -> Image.Image:
    """Apply FiligraneFacile-style nonlinear vertical displacement."""
    if strength <= 0:
        return layer
    pixels = np.asarray(layer)
    height, width = pixels.shape[:2]
    y, x = np.indices((height, width))
    offset = strength * np.sin(y * 8 / max(height, 1)) * np.sin(x * 12 / max(width, 1)) ** 2
    source_y = np.clip(np.rint(y + offset), 0, height - 1).astype(np.int32)
    distorted = pixels[source_y, x]
    return Image.fromarray(distorted.astype(np.uint8), "RGBA")


def render_visible_watermark(
    original_pixmap: pymupdf.Pixmap,
    visible_rect: pymupdf.Rect,
    config: dict[str, Any],
    rng: random.Random,
) -> Image.Image:
    """Render randomized sharp and blurred watermark layers above the source pixels."""
    opacity_variation = config["watermarkOpacityVariationPercent"]
    opacity = min(
        100.0,
        max(
            1.0,
            config["watermarkOpacityPercent"]
            + rng.uniform(-opacity_variation, opacity_variation),
        ),
    )
    tile = make_watermark_tile(config["text"], config, opacity)
    layer_document = pymupdf.open()
    try:
        layer_page = layer_document.new_page(width=visible_rect.width, height=visible_rect.height)
        add_staggered_watermarks(layer_page, tile, config, rng)
        layer_pixmap = layer_page.get_pixmap(
            dpi=config["dpi"],
            colorspace=pymupdf.csRGB,
            alpha=True,
            annots=False,
        )
        sharp_layer = image_from_pixmap(layer_pixmap)
    finally:
        layer_document.close()
        tile.close()

    base = image_from_pixmap(original_pixmap).convert("RGBA")
    blur_radius = config["watermarkBlurRadiusPixels"]
    blur_opacity = config["watermarkBlurOpacityPercent"] / 100
    if blur_radius > 0 and blur_opacity > 0:
        blurred_layer = sharp_layer.filter(ImageFilter.GaussianBlur(radius=blur_radius))
        alpha = blurred_layer.getchannel("A").point(lambda value: round(value * blur_opacity))
        blurred_layer.putalpha(alpha)
        base.alpha_composite(blurred_layer)

    if config["watermarkDistortionEnabled"]:
        sharp_layer = distort_watermark_layer(
            sharp_layer,
            config["watermarkDistortionStrengthPixels"],
        )
    base.alpha_composite(sharp_layer)
    return base.convert("RGB")


def encode_jpeg(image: Image.Image, quality: int) -> bytes:
    output = BytesIO()
    image.save(output, format="JPEG", quality=quality, optimize=True)
    return output.getvalue()


def validate_page_geometry(page: pymupdf.Page, config: dict[str, Any]) -> None:
    """Reject invalid or unsafe pages before rendering output."""
    visible_rect = page.rect
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


def rasterize(
    input_path: Path,
    output_path: Path,
    config: dict[str, Any],
    preview_image_path: Path | None = None,
) -> None:
    rng = random.Random(bytes.fromhex(config["randomSeed"]))
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

        for source_page in source:
            validate_page_geometry(source_page, config)

        output = pymupdf.open()
        try:
            for source_page in source:
                visible_rect = source_page.rect

                # Render the original and its annotations first. The watermark is then
                # placed above that image, so annotations cannot cover the baked mark.
                original_pixmap = source_page.get_pixmap(
                    dpi=config["dpi"],
                    colorspace=pymupdf.csRGB,
                    alpha=False,
                    annots=True,
                )
                marked_image = render_visible_watermark(
                    original_pixmap,
                    visible_rect,
                    config,
                    rng,
                )
                jpeg = encode_jpeg(marked_image, config["jpegQuality"])
                if preview_image_path is not None and source_page.number == 0:
                    preview_image_path.write_bytes(jpeg)

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


def main() -> None:
    if len(sys.argv) not in (3, 4):
        fail(
            EXIT_BAD_REQUEST,
            "invalid_arguments",
            "Expected input and output PDF paths, with an optional preview image path.",
        )

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])
    preview_image_path = Path(sys.argv[3]) if len(sys.argv) == 4 else None
    if not input_path.is_file():
        fail(EXIT_BAD_REQUEST, "missing_input", "Input PDF does not exist.")
    if output_path == input_path:
        fail(EXIT_BAD_REQUEST, "invalid_output", "Output path must differ from input path.")
    if preview_image_path is not None and preview_image_path in (input_path, output_path):
        fail(
            EXIT_BAD_REQUEST,
            "invalid_preview_output",
            "Preview image path must differ from the input and output PDF paths.",
        )

    config = read_config()
    rasterize(input_path, output_path, config, preview_image_path)
    with pymupdf.open(output_path) as output:
        print(json.dumps({"pages": output.page_count}, separators=(",", ":")))


if __name__ == "__main__":
    main()
