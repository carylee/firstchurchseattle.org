# /// script
# requires-python = ">=3.11"
# dependencies = ["pillow"]
# ///
"""Render the committed static map of First Church from OpenStreetMap tiles.

Stitches ~15 OSM tiles (one-time fetch, well within the tile usage policy),
draws a brand-colored pin at the church and the required attribution, and
writes the result to the child theme:

    wp-content/themes/maranatha-child/assets/map.webp

The image is 1280x800 — a 2x (retina) rendering of a 640x400 CSS-pixel map
at zoom 15. It's served by inc/static-map.php in the page content and the
footer. Re-run this script only if the neighborhood changes enough to
matter:

    uv run ops/scripts/render-osm-map.py
"""

import io
import math
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

LAT, LON = 47.6188056, -122.353198  # 180 Denny Way (ctc_location authored meta)
ZOOM = 16  # tile zoom; canvas displayed at half size = visual zoom 15 @2x
W, H = 1280, 800
TILE = 256
BRAND = (0x70, 0x33, 0x4E)  # maroon, same as the old map marker
UA = "FirstChurchSeattle-website/1.0 (one-time static map render; office@firstchurchseattle.org)"
OUT = (
    Path(__file__).resolve().parents[2]
    / "wp-content/themes/maranatha-child/assets/map.webp"
)


def global_px(lat: float, lon: float, zoom: int) -> tuple[float, float]:
    n = TILE * 2**zoom
    x = (lon + 180.0) / 360.0 * n
    lat_r = math.radians(lat)
    y = (1.0 - math.log(math.tan(lat_r) + 1.0 / math.cos(lat_r)) / math.pi) / 2.0 * n
    return x, y


def fetch_tile(z: int, x: int, y: int) -> Image.Image:
    url = f"https://tile.openstreetmap.org/{z}/{x}/{y}.png"
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as resp:
        return Image.open(io.BytesIO(resp.read())).convert("RGB")


def main() -> None:
    cx, cy = global_px(LAT, LON, ZOOM)
    left, top = cx - W / 2, cy - H / 2

    canvas = Image.new("RGB", (W, H))
    for tx in range(math.floor(left / TILE), math.floor((left + W - 1) / TILE) + 1):
        for ty in range(math.floor(top / TILE), math.floor((top + H - 1) / TILE) + 1):
            canvas.paste(fetch_tile(ZOOM, tx, ty), (round(tx * TILE - left), round(ty * TILE - top)))

    draw = ImageDraw.Draw(canvas)

    # Pin: tail triangle pointing at the church, head circle, white outline + dot.
    px, py = W / 2, H / 2
    draw.polygon([(px - 13, py - 32), (px + 13, py - 32), (px, py)], fill=BRAND, outline="white")
    draw.ellipse((px - 19, py - 60, px + 19, py - 22), fill=BRAND, outline="white", width=4)
    draw.ellipse((px - 7, py - 48, px + 7, py - 34), fill="white")

    # Attribution (required by the OSM tile/data license), bottom-right.
    text = "© OpenStreetMap contributors"
    try:
        font = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", 22)
    except OSError:
        font = ImageFont.load_default(size=22)
    tw = draw.textlength(text, font=font)
    box = Image.new("RGBA", (int(tw) + 20, 36), (255, 255, 255, 200))
    canvas.paste(box, (W - box.width, H - box.height), box)
    draw.text((W - tw - 10, H - 31), text, fill=(60, 60, 60), font=font)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    canvas.save(OUT, "WEBP", quality=82, method=6)
    print(f"wrote {OUT} ({OUT.stat().st_size:,} bytes)")


if __name__ == "__main__":
    main()
