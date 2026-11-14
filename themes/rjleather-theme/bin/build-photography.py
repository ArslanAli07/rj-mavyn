#!/usr/bin/env python3
"""Build the theme's bundled photography from the store's media library.

The merchant's originals live in the application's media library
(``storage/app/public``) and are served from the storage symlink. They are far
too large to ship with the theme, so this script writes web-sized derivatives
into ``resources/images`` — the folder the theme build copies to
``dist/images`` and publishes in the asset manifest, where
``theme_asset('images/...')`` resolves them with a versioned URL.

Usage:
    python3 bin/build-photography.py [--source DIR] [--force]

Pillow is required (the application itself does not need it at runtime).
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    from PIL import Image
except ImportError:  # pragma: no cover - developer tooling only
    sys.exit("Pillow is required: pip install Pillow")

# Source folder -> theme folder, with the widest edge kept for each group.
GROUPS = {
    "gallery": ("gallery", 1800),
    "category-images": ("category", 1200),
    "showcase-images": ("showcase", 1200),
    "hero-banners": ("hero", 2200),
}

MOBILE_WIDTH = 900
QUALITY = 80


def write(image: Image.Image, target: Path, width: int, force: bool) -> bool:
    if target.exists() and not force:
        print(f"  skip {target.name} (exists)")
        return False
    if image.width > width:
        height = round(image.height * width / image.width)
        image = image.resize((width, height), Image.LANCZOS)
    target.parent.mkdir(parents=True, exist_ok=True)
    image.convert("RGB").save(target, "JPEG", quality=QUALITY, optimize=True, progressive=True)
    print(f"  {target.name:28} {image.width}x{image.height} {target.stat().st_size // 1024} KB")
    return True


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", default=None, help="Media library folder (default: the application's storage/app/public)")
    parser.add_argument("--target", default="resources/images")
    parser.add_argument("--force", action="store_true")
    args = parser.parse_args()

    root = Path(__file__).resolve().parent.parent
    app = root.parent.parent
    source = Path(args.source).resolve() if args.source else (app / "storage/app/public").resolve()
    target = (root / args.target).resolve()
    if not source.is_dir():
        sys.exit(f"Media library not found: {source}")

    written = 0
    for folder, (theme_folder, width) in GROUPS.items():
        directory = source / folder
        if not directory.is_dir():
            continue
        print(f"{folder} -> {theme_folder}/")
        for path in sorted(directory.glob("*.jpeg")):
            name = path.stem
            with Image.open(path) as image:
                if name.endswith("-mobile"):
                    written += write(image, target / theme_folder / f"{name}.jpeg", MOBILE_WIDTH, args.force)
                elif name.endswith("-hero"):
                    written += write(image, target / theme_folder / f"{name[:-5]}-wide.jpeg", width, args.force)
                else:
                    written += write(image, target / theme_folder / f"{name}.jpeg", width, args.force)

    print(f"\n{written} image(s) written to {target}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
