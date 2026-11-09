"""Contact sheet of captured screenshots with their target boxes drawn (QA aid).
usage: python3 contact_sheet.py OUT_DIR sheet.png [ID_PREFIX...]"""
import json, sys, os
from PIL import Image, ImageDraw
out, dest = sys.argv[1], sys.argv[2]
pref = sys.argv[3:]
steps = [s for s in json.load(open(os.path.join(out, 'steps.json')))['steps'] if not pref or any(s['id'].startswith(p) for p in pref)]
col = {'click': (0, 200, 255), 'field': (255, 255, 255), 'result': (40, 200, 90), 'caution': (255, 180, 0), 'error': (230, 50, 50)}
W, H, C = 640, 360, 3
rows = (len(steps) + C - 1) // C
sheet = Image.new('RGB', (W * C, (H + 24) * rows), 'white')
d0 = ImageDraw.Draw(sheet)
for i, s in enumerate(steps):
    im = Image.open(os.path.join(out, 'ROAP_Screenshot_Evidence', 'original', s['file'])).convert('RGB')
    d = ImageDraw.Draw(im)
    for b in s['boxes']:
        d.rectangle([b['x'], b['y'], b['x'] + b['w'], b['y'] + b['h']], outline=col[b['kind']], width=5)
    im.thumbnail((W, H))
    x, y = (i % C) * W, (i // C) * (H + 24)
    sheet.paste(im, (x, y + 24))
    d0.text((x + 4, y + 4), f"{s['id']} {s['title']} [{s['url']}]", fill='black')
sheet.save(dest)
print(dest, len(steps))
