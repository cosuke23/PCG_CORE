#!/usr/bin/env bash
# Render a deck to a PDF and small slide images for visual QA.
#   render.sh DECK.pptx OUT_DIR [SOFFICE_WRAPPER]
set -euo pipefail
DECK="$1"; OUT="$2"; WRAP="${3:-}"
mkdir -p "$OUT"; cp "$DECK" "$OUT/deck.pptx"; cd "$OUT"; rm -f s-*.jpg grid-*.jpg deck.pdf
if [ -n "$WRAP" ]; then python3 "$WRAP" --headless --convert-to pdf deck.pptx >/dev/null 2>&1; else soffice --headless --convert-to pdf deck.pptx >/dev/null 2>&1; fi
pdftoppm -jpeg -r 50 deck.pdf s
python3 - <<'PY'
from PIL import Image
import glob
fs = sorted(glob.glob('s-*.jpg'))
for k in range(0, len(fs), 12):
    ims = [Image.open(f) for f in fs[k:k + 12]]
    w, h = ims[0].size
    sheet = Image.new('RGB', (w * 3, h * 4), 'white')
    for i, im in enumerate(ims): sheet.paste(im, ((i % 3) * w, (i // 3) * h))
    sheet.save('grid-%02d.jpg' % (k // 12))
print(len(fs), 'slides')
PY
