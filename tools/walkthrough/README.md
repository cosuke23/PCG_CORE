# End-to-end user walkthrough deck — how to update it

The deck and its companion files are generated from the **running application**.
Nothing is drawn by hand: `capture.js` performs every step in a real browser on the
development database and records where each control is on screen; `build_deck.js`
places those screenshots in PowerPoint and draws the arrows, outlines and numbered
markers as editable shapes on top.

> The deck starts at **PCG HRMIS** (`/`, the main landing page) and continues in
> **MEPCOM HRMIS** (`/mepcom` and the member, checker and admin pages).

The presenter script lives in `script.js`: one entry per step id (`S02-01` …) and
per overview slide (`cover`, `roles`, `div3` …). When a step changes in `capture.js`,
update its script entry too; `build_deck.js` warns about any slide without one.

## What gets produced

| File | What it is |
|---|---|
| `PCG_HRMIS_End_to_End_Walkthrough.pptx` | The editable deck (16:9) in the design of the PCG HRMIS website (crimson / azure / gold / sand, Cambria headings, the hero's diagonal band), from the PCG HRMIS landing page to re-enlistment. Screenshots are pictures; every arrow, outline, marker, card and table is a PowerPoint shape. The speaker notes of every slide hold the presenter script (`script.js`), then the route, role and evidence file. |
| `PCG_HRMIS_End_to_End_Walkthrough.pdf` | The same deck as a PDF (rendered with LibreOffice). |
| `ROAP_Workflow_Inventory.xlsx` | Coverage of the requested storyboard (Verified / NOT IMPLEMENTED / NOT VERIFIED), every captured step with its exact labels and source code, all routes with their roles, the permission matrix, the admin roles. |
| `ROAP_QA_Validation.xlsx` | One row per screenshot: slide, workflow, role, route, screenshot, target found, error/loading state, personal data and secret checks, status. |
| `ROAP_Screenshot_Evidence/original/` | Untouched captures (1920×1080 desktop, 390×844 phone at 2× pixels). |
| `ROAP_Screenshot_Evidence/zoomed/` | Real crops of the originals used on slides where the controls are small. |
| `ROAP_Screenshot_Evidence/annotated/` | Flattened copies with the same annotations (for documents and email). |
| `ROAP_Screenshot_Evidence/manifest.csv` | File, slide, route, role, viewport and capture time of every screenshot. |
| `steps.json`, `deck_steps.json` | Raw capture data (targets in CSS pixels) and what the deck used. |

## Regenerate after a change in the system

Requirements: the dev setup from `tests/e2e/README.md` (MariaDB with `pcg_core_dev`,
`.env` with `APP_ENV=local`, `DB_DATABASE=pcg_core_dev`, `PCG_ADMIN_2FA=totp`),
Node 18+, Python 3 with Pillow and openpyxl, LibreOffice (`soffice`) and `pdftoppm`.

```bash
cd tools/walkthrough && npm install && cd ../..
```

```bash
php artisan serve --host=127.0.0.1 --port=8123
```

```bash
bash tools/walkthrough/reseed.sh
```

```bash
node tools/walkthrough/capture.js walkthrough-out
```

```bash
DECK_COMPACT=1 node tools/walkthrough/build_deck.js walkthrough-out
```

(`DECK_COMPACT=1` stores the screenshots as quality-88 JPEG: about 8 MB instead of 18 MB, easier to download and email. Leave it out for lossless PNG.)

```bash
php artisan route:list --json > walkthrough-out/routes.json
```

```bash
python3 tools/walkthrough/build_docs.py walkthrough-out walkthrough-out/routes.json .
```

```bash
bash tools/walkthrough/render.sh walkthrough-out/PCG_HRMIS_End_to_End_Walkthrough.pptx walkthrough-out/render
```

The last command writes `render/deck.pdf` (copy it to `PCG_HRMIS_End_to_End_Walkthrough.pdf`)
and `render/grid-NN.jpg` contact sheets for a visual check. `walkthrough-out/` is
ignored by git: never commit screenshots.

Notes:

- **Re-seed before every capture.** The capture changes data (it registers a sample
  member, returns and approves documents, creates the unit `MEPSAM`, approves a registration).
- `capture.js` uses full Chromium (`channel: 'chromium'`), not the headless shell,
  because the review dialog shows PDFs in the browser's PDF viewer. The registration
  photo comes from Chromium's built-in test camera (the green test pattern).
- Admin sign-in passes the real second step with the dev-only authenticator key the
  seeder enrols; no step is bypassed. Two admin sign-ins of the same account need
  different 30-second codes, so the run waits when needed (about 5 minutes in total).
- When a page changes, adjust its step in `capture.js` (selectors, action and expected
  text). `capture.js` prints each step with how many of its targets it found and lists
  warnings at the end; the run should end with `warnings 0`.
- To make PowerPoint use the deck colours in its theme, set `PPTX_APPLY_THEME` to the
  path of the pptx skill's `scripts/apply_theme.js` (optional).
- Steps that the system does not have are listed as NOT IMPLEMENTED on the coverage
  slides and in the inventory. Do not add slides for them.
