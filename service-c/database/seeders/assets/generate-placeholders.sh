#!/usr/bin/env bash
set -euo pipefail

OUT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/dishes" && pwd)"
mkdir -p "${OUT_DIR}"

colors=("#dcc894" "#b4c8a0" "#c8aac0")

for index in 1 2 3; do
  convert -size 800x600 "xc:${colors[$((index - 1))]}" -quality 88 "${OUT_DIR}/placeholder-${index}.jpg"
done

for index in 1 2 3; do
  identify "${OUT_DIR}/placeholder-${index}.jpg"
done
