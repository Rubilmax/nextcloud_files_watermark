# SPDX-FileCopyrightText: 2026 Watermarked shares contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

import pymupdf

SCRIPT = Path(__file__).parents[2] / "scripts" / "rasterize.py"


def run_renderer(
    source: Path,
    output: Path,
    *,
    text: str = "CONFIDENTIAL – 東京",
    dpi: int = 120,
    max_pages: int = 20,
    appearance: dict[str, object] | None = None,
    preview_image: Path | None = None,
) -> subprocess.CompletedProcess[str]:
    config: dict[str, object] = {
        "text": text,
        "dpi": dpi,
        "maxPages": max_pages,
        "jpegQuality": 88,
        "watermarkFontSize": 28,
        "watermarkColor": "#333333",
        "watermarkOpacityPercent": 30,
        "watermarkAngle": 30,
        "watermarkMinimumHorizontalInterval": 145,
        "watermarkHorizontalGap": 48,
        "watermarkVerticalInterval": 78,
        "watermarkOpacityVariationPercent": 5,
        "watermarkSpacingVariationPercent": 10,
        "watermarkPositionJitterPoints": 8,
        "watermarkBlurRadiusPixels": 6,
        "watermarkBlurOpacityPercent": 80,
        "watermarkDistortionEnabled": False,
        "watermarkDistortionStrengthPixels": 12,
        "randomSeed": "a" * 64,
    }
    config.update(appearance or {})
    command = [sys.executable, str(SCRIPT), str(source), str(output)]
    if preview_image is not None:
        command.append(str(preview_image))
    return subprocess.run(
        command,
        input=json.dumps(config, ensure_ascii=False),
        text=True,
        capture_output=True,
        check=False,
    )


def create_feature_pdf(path: Path) -> None:
    document = pymupdf.open()
    first = document.new_page(width=400, height=300)
    first.insert_text((40, 60), "Searchable secret text", fontsize=20)
    first.insert_link(
        {
            "kind": pymupdf.LINK_URI,
            "from": pymupdf.Rect(35, 70, 210, 100),
            "uri": "https://example.com/secret",
        }
    )
    annotation = first.add_text_annot((200, 100), "Annotation secret")
    annotation.update()
    widget = pymupdf.Widget()
    widget.field_name = "secret-field"
    widget.field_type = pymupdf.PDF_WIDGET_TYPE_TEXT
    widget.rect = pymupdf.Rect(40, 120, 240, 150)
    first.add_widget(widget)

    second = document.new_page(width=300, height=500)
    second.insert_text((30, 70), "Rotated second page", fontsize=18)
    second.set_rotation(90)
    document.set_metadata({"title": "Secret title", "author": "Secret author"})
    document.save(path)
    document.close()


def test_rasterizes_mixed_pages_and_removes_interactive_content(tmp_path: Path) -> None:
    source = tmp_path / "source.pdf"
    output = tmp_path / "output.pdf"
    create_feature_pdf(source)

    result = run_renderer(source, output)
    assert result.returncode == 0, result.stderr

    with pymupdf.open(output) as document:
        assert document.page_count == 2
        assert document.metadata.get("title", "") == ""
        assert document.metadata.get("author", "") == ""
        assert document.embfile_names() == []
        assert document[0].get_text() == ""
        assert document[1].get_text() == ""
        assert document[0].get_links() == []
        assert list(document[0].annots() or []) == []
        assert list(document[0].widgets() or []) == []
        assert document[0].rect.width == 400
        assert document[0].rect.height == 300
        assert document[1].rect.width == 500
        assert document[1].rect.height == 300

        image = document[0].get_images(full=True)[0]
        assert abs(image[2] - round(400 * 120 / 72)) <= 1
        assert abs(image[3] - round(300 * 120 / 72)) <= 1


def test_unicode_and_long_watermark_produces_visible_tiling(tmp_path: Path) -> None:
    source = tmp_path / "blank.pdf"
    output = tmp_path / "marked.pdf"
    document = pymupdf.open()
    document.new_page(width=600, height=800)
    document.save(source)
    document.close()

    text = ("Ångström 東京 Привет مرحبا " * 5)[:128]
    result = run_renderer(source, output, text=text, dpi=96)
    assert result.returncode == 0, result.stderr

    with pymupdf.open(output) as marked:
        pixmap = marked[0].get_pixmap(dpi=24, colorspace=pymupdf.csGRAY)
        samples = pixmap.samples
        dark_pixels = sum(value < 245 for value in samples)
        assert dark_pixels > len(samples) * 0.002


def test_applies_configured_watermark_appearance(tmp_path: Path) -> None:
    source = tmp_path / "blank.pdf"
    output = tmp_path / "red-watermark.pdf"
    preview_image = tmp_path / "red-watermark.jpg"
    document = pymupdf.open()
    document.new_page(width=400, height=300)
    document.save(source)
    document.close()

    result = run_renderer(
        source,
        output,
        text="CUSTOM",
        dpi=96,
        appearance={
            "watermarkFontSize": 60,
            "watermarkColor": "#ff0000",
            "watermarkOpacityPercent": 100,
            "watermarkAngle": 0,
            "watermarkMinimumHorizontalInterval": 100,
            "watermarkHorizontalGap": 0,
            "watermarkVerticalInterval": 80,
        },
        preview_image=preview_image,
    )
    assert result.returncode == 0, result.stderr

    with pymupdf.open(output) as marked:
        pixmap = marked[0].get_pixmap(dpi=48, colorspace=pymupdf.csRGB)
        pixels = zip(pixmap.samples[0::3], pixmap.samples[1::3], pixmap.samples[2::3])
        assert sum(red > green + 40 and red > blue + 40 for red, green, blue in pixels) > 100

    assert preview_image.read_bytes().startswith(b"\xff\xd8")
    preview_pixmap = pymupdf.Pixmap(preview_image)
    assert abs(preview_pixmap.width - round(400 * 96 / 72)) <= 1
    assert abs(preview_pixmap.height - round(300 * 96 / 72)) <= 1


def test_rejects_invalid_watermark_appearance(tmp_path: Path) -> None:
    source = tmp_path / "blank.pdf"
    document = pymupdf.open()
    document.new_page()
    document.save(source)
    document.close()

    invalid_values = {
        "watermarkFontSize": 7,
        "watermarkColor": "red",
        "watermarkOpacityPercent": 0,
        "watermarkAngle": 181,
        "watermarkMinimumHorizontalInterval": 19,
        "watermarkHorizontalGap": -1,
        "watermarkVerticalInterval": 2001,
        "watermarkOpacityVariationPercent": 51,
        "watermarkSpacingVariationPercent": 41,
        "watermarkPositionJitterPoints": 101,
        "watermarkBlurRadiusPixels": 65,
        "watermarkBlurOpacityPercent": 101,
        "watermarkDistortionEnabled": "yes",
        "watermarkDistortionStrengthPixels": 129,
        "randomSeed": "short",
    }
    for key, value in invalid_values.items():
        output = tmp_path / f"invalid-{key}.pdf"
        result = run_renderer(source, output, appearance={key: value})
        assert result.returncode == 13
        assert json.loads(result.stderr)["code"].startswith("invalid_")
        assert not output.exists()


def test_rejects_page_limit(tmp_path: Path) -> None:
    source = tmp_path / "many.pdf"
    output = tmp_path / "output.pdf"
    document = pymupdf.open()
    document.new_page()
    document.new_page()
    document.save(source)
    document.close()

    result = run_renderer(source, output, max_pages=1)
    assert result.returncode == 12
    assert json.loads(result.stderr)["code"] == "page_limit_exceeded"
    assert not output.exists()


def test_randomized_visible_watermark_is_seeded_and_distortion_changes_output(tmp_path: Path) -> None:
    source = tmp_path / "blank.pdf"
    first = tmp_path / "first.pdf"
    repeated = tmp_path / "repeated.pdf"
    different = tmp_path / "different.pdf"
    distorted = tmp_path / "distorted.pdf"
    document = pymupdf.open()
    document.new_page(width=400, height=300)
    document.save(source)
    document.close()

    assert run_renderer(source, first).returncode == 0
    assert run_renderer(source, repeated).returncode == 0
    assert run_renderer(source, different, appearance={"randomSeed": "b" * 64}).returncode == 0
    assert run_renderer(
        source,
        distorted,
        appearance={"watermarkDistortionEnabled": True},
    ).returncode == 0

    def page_jpeg(path: Path) -> bytes:
        with pymupdf.open(path) as rendered:
            xref = rendered[0].get_images(full=True)[0][0]
            return rendered.extract_image(xref)["image"]

    assert page_jpeg(first) == page_jpeg(repeated)
    assert page_jpeg(first) != page_jpeg(different)
    assert page_jpeg(first) != page_jpeg(distorted)


def test_rejects_page_dimensions_that_would_exhaust_memory(tmp_path: Path) -> None:
    source = tmp_path / "huge-page.pdf"
    output = tmp_path / "output.pdf"
    document = pymupdf.open()
    document.new_page(width=10_000, height=10_000)
    document.save(source)
    document.close()

    result = run_renderer(source, output, dpi=300)
    assert result.returncode == 14
    assert json.loads(result.stderr)["code"] == "page_too_large"
    assert not output.exists()


def test_rejects_encrypted_pdf(tmp_path: Path) -> None:
    source = tmp_path / "encrypted.pdf"
    output = tmp_path / "output.pdf"
    document = pymupdf.open()
    document.new_page()
    document.save(
        source,
        encryption=pymupdf.PDF_ENCRYPT_AES_256,
        owner_pw="owner-secret",
        user_pw="user-secret",
    )
    document.close()

    result = run_renderer(source, output)
    assert result.returncode == 10
    assert json.loads(result.stderr)["code"] == "encrypted_pdf"


def test_rejects_malformed_pdf(tmp_path: Path) -> None:
    source = tmp_path / "broken.pdf"
    output = tmp_path / "output.pdf"
    source.write_bytes(b"%PDF-1.7\nthis is not a PDF")

    result = run_renderer(source, output)
    assert result.returncode == 11
    assert json.loads(result.stderr)["code"] == "malformed_pdf"
