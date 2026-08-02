"""Create transparent PNG brand assets for Teslatech (TT monogram, electric blue)."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "public" / "images" / "brand"

# Electric blue palette
ELECTRIC_DEEP = (10, 45, 130, 255)  # #0A2D82
SIGNAL = (0, 183, 255, 255)  # #00B7FF
WHITE = (245, 248, 252, 255)
INK = (16, 24, 32, 255)


def rounded_rect(draw: ImageDraw.ImageDraw, box, radius: int, fill):
    draw.rounded_rectangle(box, radius=radius, fill=fill)


def draw_tt(draw: ImageDraw.ImageDraw, size: int, fill) -> None:
    """Geometric interlocking TT monogram — crisp at favicon sizes."""
    # Shared vertical metrics
    top = size * 0.30
    bar_h = size * 0.11
    stem_w = size * 0.10
    stem_bottom = size * 0.72
    gap = size * 0.04

    # Left T
    left_bar = (size * 0.18, top, size * 0.48, top + bar_h)
    left_stem_cx = (left_bar[0] + left_bar[2]) / 2
    left_stem = (
        left_stem_cx - stem_w / 2,
        top + bar_h * 0.35,
        left_stem_cx + stem_w / 2,
        stem_bottom,
    )

    # Right T (slight overlap feel via tighter spacing)
    right_bar = (size * 0.48 + gap, top, size * 0.82, top + bar_h)
    right_stem_cx = (right_bar[0] + right_bar[2]) / 2
    right_stem = (
        right_stem_cx - stem_w / 2,
        top + bar_h * 0.35,
        right_stem_cx + stem_w / 2,
        stem_bottom,
    )

    for box in (left_bar, left_stem, right_bar, right_stem):
        draw.rectangle(box, fill=fill)


def draw_wifi(draw: ImageDraw.ImageDraw, size: int) -> None:
    cx = int(size * 0.74)
    cy = int(size * 0.26)
    for r, width, alpha in (
        (int(size * 0.055), int(size * 0.035), 255),
        (int(size * 0.11), int(size * 0.03), 220),
        (int(size * 0.165), int(size * 0.028), 180),
    ):
        color = (*SIGNAL[:3], alpha)
        draw.arc(
            [cx - r, cy - r, cx + r, cy + r],
            start=220,
            end=320,
            fill=color,
            width=max(2, width),
        )
    dr = int(size * 0.03)
    draw.ellipse([cx - dr, cy - dr, cx + dr, cy + dr], fill=SIGNAL)


def draw_mark(size: int = 512) -> Image.Image:
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    pad = int(size * 0.06)
    radius = int(size * 0.22)
    rounded_rect(draw, [pad, pad, size - pad, size - pad], radius, ELECTRIC_DEEP)
    draw_tt(draw, size, WHITE)
    draw_wifi(draw, size)

    return img


def load_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "/usr/share/fonts/truetype/lato/Lato-Bold.ttf",
        "/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
        r"C:\Windows\Fonts\arialbd.ttf",
        r"C:\Windows\Fonts\segoeuib.ttf",
    ]
    for path in candidates:
        try:
            return ImageFont.truetype(path, size=size)
        except OSError:
            continue
    return ImageFont.load_default()


def draw_full(width: int = 1200, height: int = 320) -> Image.Image:
    img = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    mark_size = int(height * 0.88)
    mark = draw_mark(mark_size)
    mark_y = (height - mark_size) // 2
    mark_x = int(height * 0.06)
    img.paste(mark, (mark_x, mark_y), mark)

    draw = ImageDraw.Draw(img)
    font = load_font(int(height * 0.34))
    text = "Teslatech"
    text_x = mark_x + mark_size + int(height * 0.12)
    bbox = draw.textbbox((0, 0), text, font=font)
    th = bbox[3] - bbox[1]
    text_y = (height - th) / 2 - bbox[1]
    draw.text((text_x, text_y), text, font=font, fill=INK)

    bbox_all = img.getbbox()
    if bbox_all:
        left, top, right, bottom = bbox_all
        pad = 8
        img = img.crop(
            (
                max(0, left - pad),
                max(0, top - pad),
                min(width, right + pad),
                min(height, bottom + pad),
            )
        )

    return img


def write_svg_mark(path: Path) -> None:
    path.write_text(
        """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="none">
  <rect x="31" y="31" width="450" height="450" rx="112" fill="#0A2D82"/>
  <!-- Left T -->
  <rect x="92" y="154" width="154" height="56" rx="4" fill="#F5F8FC"/>
  <rect x="145" y="174" width="51" height="194" rx="4" fill="#F5F8FC"/>
  <!-- Right T -->
  <rect x="266" y="154" width="154" height="56" rx="4" fill="#F5F8FC"/>
  <rect x="318" y="174" width="51" height="194" rx="4" fill="#F5F8FC"/>
  <!-- WiFi signal -->
  <circle cx="379" cy="133" r="15" fill="#00B7FF"/>
  <path d="M348 158c17 16 49 16 66 0" stroke="#00B7FF" stroke-width="16" stroke-linecap="round" opacity="0.9"/>
  <path d="M330 182c27 24 75 24 102 0" stroke="#00B7FF" stroke-width="14" stroke-linecap="round" opacity="0.7"/>
</svg>
""",
        encoding="utf-8",
    )


def write_svg_full(path: Path) -> None:
    path.write_text(
        """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 160" fill="none">
  <rect x="8" y="8" width="144" height="144" rx="36" fill="#0A2D82"/>
  <rect x="30" y="48" width="48" height="18" rx="2" fill="#F5F8FC"/>
  <rect x="46" y="54" width="16" height="62" rx="2" fill="#F5F8FC"/>
  <rect x="84" y="48" width="48" height="18" rx="2" fill="#F5F8FC"/>
  <rect x="100" y="54" width="16" height="62" rx="2" fill="#F5F8FC"/>
  <circle cx="122" cy="42" r="5" fill="#00B7FF"/>
  <path d="M112 50c5.5 5 16 5 21.5 0" stroke="#00B7FF" stroke-width="5" stroke-linecap="round"/>
  <path d="M106 58c9 8 25 8 34 0" stroke="#00B7FF" stroke-width="4.5" stroke-linecap="round" opacity="0.75"/>
  <text x="176" y="98" fill="#101820" font-family="Syne, Lato, Ubuntu, DejaVu Sans, sans-serif" font-size="52" font-weight="700" letter-spacing="-0.02em">Teslatech</text>
</svg>
""",
        encoding="utf-8",
    )


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    mark = draw_mark(512)
    mark_path = OUT / "logo-mark.png"
    mark.save(mark_path, format="PNG", optimize=True)

    fav = mark.resize((192, 192), Image.Resampling.LANCZOS)
    fav_path = OUT / "favicon.png"
    fav.save(fav_path, format="PNG", optimize=True)

    # Multi-size favicon.ico for browser tab
    ico_sizes = [(16, 16), (32, 32), (48, 48)]
    ico_images = [mark.resize(s, Image.Resampling.LANCZOS) for s in ico_sizes]
    ico_path = ROOT / "public" / "favicon.ico"
    ico_images[0].save(
        ico_path,
        format="ICO",
        sizes=[(im.width, im.height) for im in ico_images],
        append_images=ico_images[1:],
    )

    full = draw_full()
    full_path = OUT / "logo-full.png"
    full.save(full_path, format="PNG", optimize=True)

    write_svg_mark(OUT / "logo-mark.svg")
    write_svg_full(OUT / "logo-full.svg")

    for path in (mark_path, full_path, fav_path, ico_path, OUT / "logo-mark.svg", OUT / "logo-full.svg"):
        if path.suffix.lower() in {".png", ".ico"}:
            sample = Image.open(path)
            print(f"{path.name}: {sample.size} mode={sample.mode} bytes={path.stat().st_size}")
            px = sample.getpixel((0, 0))
            print(f"  corner alpha={px[3] if isinstance(px, tuple) and len(px) > 3 else px}")
        else:
            print(f"{path.name}: svg bytes={path.stat().st_size}")


if __name__ == "__main__":
    main()
