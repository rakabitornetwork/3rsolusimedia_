"""Create transparent PNG brand assets for 3R Solusi Media."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "public" / "images" / "brand"

TEAL = (15, 92, 90, 255)  # #0F5C5A
SIGNAL = (20, 184, 166, 255)  # #14B8A6
WHITE = (245, 248, 250, 255)
INK = (15, 23, 28, 255)


def rounded_rect(draw: ImageDraw.ImageDraw, box, radius: int, fill):
    draw.rounded_rectangle(box, radius=radius, fill=fill)


def draw_mark(size: int = 512) -> Image.Image:
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    pad = int(size * 0.06)
    radius = int(size * 0.22)
    rounded_rect(draw, [pad, pad, size - pad, size - pad], radius, TEAL)

    # "3R" lettermark
    font_size = int(size * 0.42)
    font = load_font(font_size)
    text = "3R"
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    x = (size - tw) / 2 - bbox[0]
    y = (size - th) / 2 - bbox[1] - size * 0.04
    draw.text((x, y), text, font=font, fill=WHITE)

    # Wifi arcs (top-right)
    cx = int(size * 0.72)
    cy = int(size * 0.28)
    for i, (r, width, alpha) in enumerate(
        [
            (int(size * 0.055), int(size * 0.035), 255),
            (int(size * 0.11), int(size * 0.03), 220),
            (int(size * 0.165), int(size * 0.028), 180),
        ]
    ):
        color = (*SIGNAL[:3], alpha)
        draw.arc(
            [cx - r, cy - r, cx + r, cy + r],
            start=220,
            end=320,
            fill=color,
            width=max(2, width),
        )

    # Dot
    dr = int(size * 0.03)
    draw.ellipse([cx - dr, cy - dr, cx + dr, cy + dr], fill=SIGNAL)

    return img


def load_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        r"C:\Windows\Fonts\arialbd.ttf",
        r"C:\Windows\Fonts\segoeuib.ttf",
        r"C:\Windows\Fonts\verdana.ttf",
        r"C:\Windows\Fonts\arial.ttf",
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
    text = "3R Solusi Media"
    text_x = mark_x + mark_size + int(height * 0.12)
    bbox = draw.textbbox((0, 0), text, font=font)
    th = bbox[3] - bbox[1]
    text_y = (height - th) / 2 - bbox[1]
    draw.text((text_x, text_y), text, font=font, fill=INK)

    # Trim unused transparent right side for a tighter asset
    bbox_all = img.getbbox()
    if bbox_all:
        # keep a little padding
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


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    mark = draw_mark(512)
    mark_path = OUT / "logo-mark.png"
    mark.save(mark_path, format="PNG", optimize=True)

    fav = mark.resize((192, 192), Image.Resampling.LANCZOS)
    fav_path = OUT / "favicon.png"
    fav.save(fav_path, format="PNG", optimize=True)

    full = draw_full()
    full_path = OUT / "logo-full.png"
    full.save(full_path, format="PNG", optimize=True)

    for path in (mark_path, full_path, fav_path):
        sample = Image.open(path)
        print(f"{path.name}: {sample.size} mode={sample.mode} bytes={path.stat().st_size}")
        # Verify corner is transparent
        px = sample.getpixel((0, 0))
        print(f"  corner alpha={px[3] if isinstance(px, tuple) and len(px) > 3 else px}")


if __name__ == "__main__":
    main()
