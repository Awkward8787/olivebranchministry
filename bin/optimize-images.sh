#!/bin/sh
# Rebuild the committed responsive images from assets/brand.
# The server does not need this. Run it locally when a source file changes.
set -eu
root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
out="$root/public/assets/img"
mkdir -p "$out"
banner="$root/assets/brand/obm-banner.jpg"
portrait="$root/assets/brand/obm-pastors-portrait.jpg"
for width in 640 960 1284; do
    convert "$banner" -filter Lanczos -resize "${width}x" -strip -quality 90 "$out/banner-${width}.jpg"
    cwebp -quiet -q 86 "$out/banner-${width}.jpg" -o "$out/banner-${width}.webp"
    avifenc --min 62 --max 62 --speed 6 "$out/banner-${width}.jpg" "$out/banner-${width}.avif"
done
for width in 480 800 1012; do
    convert "$portrait" -filter Lanczos -resize "${width}x" -strip -quality 90 "$out/portrait-${width}.jpg"
    cwebp -quiet -q 86 "$out/portrait-${width}.jpg" -o "$out/portrait-${width}.webp"
    avifenc --min 62 --max 62 --speed 6 "$out/portrait-${width}.jpg" "$out/portrait-${width}.avif"
done
