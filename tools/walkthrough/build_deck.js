// Builds PCG_HRMIS_End_to_End_Walkthrough.pptx from the capture (steps.json +
// screenshots), in the design of the PCG HRMIS website (crimson / azure / gold /
// sand, serif headings, the hero's diagonal band). Speaker notes carry the
// presenter script from script.js. Screenshots are pictures; every annotation (outline, arrow,
// numbered marker, cards, legend) is an editable PowerPoint shape on top.
//
//   node tools/walkthrough/build_deck.js OUT_DIR
const path = require('path');
const fs = require('fs');
const pptxgen = require('pptxgenjs');
const React = require('react');
const ReactDOMServer = require('react-dom/server');
const sharp = require('sharp');
const fa = require('react-icons/fa6');

const OUT = path.resolve(process.argv[2] || 'walkthrough-out');
const DATA = JSON.parse(fs.readFileSync(path.join(OUT, 'steps.json'), 'utf8'));
const ORIG = path.join(OUT, 'ROAP_Screenshot_Evidence', 'original');
const DECK = path.join(OUT, 'PCG_HRMIS_End_to_End_Walkthrough.pptx');
const SCRIPT = require('./script');
const BAND = path.join(OUT, 'deck-band.png');
const APP = path.resolve(__dirname, '../..');
// DECK_COMPACT=1: screenshots go in as high-quality JPEG (a much smaller file)
const COMPACT = process.env.DECK_COMPACT === '1';
const JPG = path.join(OUT, 'deck-jpg');
const media = p => COMPACT ? path.join(JPG, path.basename(path.dirname(p)) + '-' + path.basename(p).replace(/\.png$/, '.jpg')) : p;
const SKILL_THEME = process.env.PPTX_APPLY_THEME; // optional path to apply_theme.js

const BRAND = 'Philippine Coast Guard';
const DOC = 'PCG HRMIS · End-to-end walkthrough';
const THEME = {
  name: 'PCG HRMIS',
  headFontFace: 'Cambria',
  bodyFontFace: 'Calibri',
  colors: {
    dk1: '1F1A1B', lt1: 'FFFFFF', dk2: '3D0F13', lt2: 'FAF7F4',
    accent1: '9A272E', accent2: '232C52', accent3: 'EB9440', accent4: '16A34A', accent5: '5468B2', accent6: '5B5357',
    hlink: '9A272E', folHlink: '641A20',
  },
};
const COL = {
  navy: '3D0F13', graphite: '641A20', ink: '1F1A1B', muted: '5B5357', faint: '6E6468', line: 'E6DBD5',
  bg: 'FAF7F4', card: 'FFFFFF', cyan: 'EB9440', cyanDark: '9A272E', green: '16A34A', greenTint: 'E9F7EF',
  amber: '8C4710', amberTint: 'FDECD9', red: 'DC2626', redTint: 'FBE4E3', blue: '7287D2', navy2: '641A20',
  azure: '232C52', azure100: 'DFE6F7', gold: 'EB9440', gold300: 'F5B672', frameGold: 'D4AF37', sand100: 'F4EEEA', crimson100: 'FBE4E3',
};
const KIND = {
  click: { color: 'EB9440', dash: 'solid', word: 'Click / open' },
  field: { color: COL.blue, dash: 'dash', word: 'Field to complete' },
  result: { color: COL.green, dash: 'solid', word: 'Result' },
  caution: { color: 'FACC15', dash: 'solid', word: 'Requirement / caution' },
  error: { color: COL.red, dash: 'solid', word: 'Error / blocked' },
};

const W = 13.333, H = 7.5;
const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';
pres.author = 'PCG HRMIS team';
pres.company = 'Philippine Coast Guard';
pres.title = 'PCG HRMIS: end-to-end user walkthrough';
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };

async function icon(name, color = 'FFFFFF', size = 256) {
  const Comp = fa[name];
  const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(Comp, { color: '#' + color, size: String(size) }));
  const buf = await sharp(Buffer.from(svg)).png().toBuffer();
  return 'image/png;base64,' + buf.toString('base64');
}

// ---------------------------------------------------------------- layouts
pres.defineSlideMaster({
  title: 'ROAP_COVER', background: { path: BAND },
  objects: [{ placeholder: { options: { name: 'title', type: 'title', x: 0.8, y: 2.25, w: 6.6, h: 1.9, fontSize: 44, bold: true, color: 'FFFFFF', valign: 'top', align: 'left', margin: 0, fontFace: 'Cambria' }, text: '' } }],
});
pres.defineSlideMaster({
  title: 'ROAP_DIVIDER', background: { path: BAND },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.8, y: 3.0, w: 9.0, h: 1.2, fontSize: 40, bold: true, color: 'FFFFFF', valign: 'top', align: 'left', margin: 0, fontFace: 'Cambria' }, text: '' } },
    { text: { text: `${DOC} · ${BRAND}`, options: { x: 0.8, y: 6.85, w: 6, h: 0.3, fontSize: 10, color: 'F6C9C8', margin: 0 } } },
  ],
  slideNumber: { x: 12.2, y: 6.85, w: 0.6, h: 0.3, fontSize: 10, color: 'F6C9C8', align: 'right' },
});
pres.defineSlideMaster({
  title: 'ROAP_CONTENT', background: { color: COL.bg },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.6, y: 0.62, w: 12.1, h: 0.7, fontSize: 30, bold: true, color: COL.navy, valign: 'top', align: 'left', margin: 0, fontFace: 'Cambria' }, text: '' } },
    { text: { text: `${DOC} · ${BRAND}`, options: { x: 0.6, y: 7.05, w: 6, h: 0.28, fontSize: 10, color: COL.faint, margin: 0 } } },
  ],
  slideNumber: { x: 12.15, y: 7.05, w: 0.6, h: 0.28, fontSize: 10, color: COL.faint, align: 'right' },
});
pres.defineSlideMaster({
  title: 'ROAP_STEP', background: { color: COL.bg },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.45, y: 0.5, w: 12.4, h: 0.6, fontSize: 26, bold: true, color: COL.navy, valign: 'top', align: 'left', margin: 0, fontFace: 'Cambria' }, text: '' } },
  ],
  slideNumber: { x: 12.25, y: 7.08, w: 0.6, h: 0.28, fontSize: 10, color: COL.faint, align: 'right' },
});

const C = pres.SchemeColor;
let currentSection = '';
function slideIn(master, section) {
  if (section !== currentSection) { pres.addSection({ title: section }); currentSection = section; }
  return pres.addSlide({ masterName: master, sectionTitle: section });
}
function kicker(s, text, y = 0.3, color = COL.cyanDark, x = 0.45) {
  s.addShape(pres.shapes.LINE, { x, y: y + 0.125, w: 0.4, h: 0, line: { color, width: 1 }, objectName: 'Eyebrow line' });
  s.addText(text.toUpperCase(), { x: x + 0.52, y, w: 10, h: 0.25, fontSize: 11, bold: true, color, charSpacing: 2, margin: 0, isTextBox: true });
}
function card(s, x, y, w, h, fill = COL.card, name) {
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h, rectRadius: 0.08, fill: { color: fill }, line: { color: COL.line, width: 0.75 },
    shadow: { type: 'outer', color: '3D0F13', opacity: 0.08, blur: 6, offset: 1.5, angle: 90 }, objectName: name });
}

// ---------------------------------------------------------------- step slides
const SECTION_NAMES = {
  '2': 'Landing pages', '3': 'Applicant registration', '4': 'Applicant application workflow', '5': 'Administrator access',
  '6': 'Admin unit management', '7': 'Applicant management', '8': 'Recruitment processing', '9': 'Re-enlistment workflow',
};
const steps = DATA.steps.filter(s => s.device === 'desktop').sort((a, b) => a.id.localeCompare(b.id));
const phone = DATA.steps.filter(s => s.device === 'mobile');
// numbering inside each workflow
const totals = {};
steps.forEach(s => { const k = s.section + '|' + s.workflow; totals[k] = (totals[k] || 0) + 1; });
const counters = {};
steps.forEach(s => { const k = s.section + '|' + s.workflow; counters[k] = (counters[k] || 0) + 1; s.n = counters[k]; s.total = totals[k]; });
const pad2 = n => String(n).padStart(2, '0');
const slideOf = {}; // step id -> slide number (filled while building)
let slideNo = 0;
const add = (master, section) => { slideNo += 1; return slideIn(master, section); };

function estLines(text, charsPerLine) {
  return text.split('\n').reduce((n, p) => n + Math.max(1, Math.ceil(p.length / charsPerLine)), 0);
}

function placeImage(s, step, ax, ay, aw, ah) {
  const ratio = step.image.w / step.image.h;
  let w = aw, h = aw / ratio;
  if (h > ah) { h = ah; w = ah * ratio; }
  const x = ax + (aw - w) / 2, y = ay + (ah - h) / 2;
  // frame
  s.addShape(pres.shapes.RECTANGLE, { x: x - 0.04, y: y - 0.04, w: w + 0.08, h: h + 0.08, fill: { color: 'FFFFFF' }, line: { color: COL.line, width: 0.75 },
    shadow: { type: 'outer', color: '3D0F13', opacity: 0.18, blur: 10, offset: 3, angle: 90 }, objectName: 'Screenshot frame' });
  s.addImage({ path: media(step.zoomedFile ? path.join(ORIG, '..', step.zoomedFile) : path.join(ORIG, step.file)), x, y, w, h, altText: `Screenshot: ${step.title} (${step.url}, ${step.role})`, objectName: 'Screenshot ' + step.id });
  return { x, y, w, h, sc: w / step.image.w };
}

function annotate(s, step, img) {
  const boxes = step.boxes;
  const toIn = b => {
    const p = 4; // px padding around the control
    let x = img.x + (b.x - p) * img.sc, y = img.y + (b.y - p) * img.sc, w = (b.w + 2 * p) * img.sc, h = (b.h + 2 * p) * img.sc;
    const x2 = Math.min(img.x + img.w, x + w), y2 = Math.min(img.y + img.h, y + h);
    x = Math.max(img.x, x); y = Math.max(img.y, y);
    return { x, y, w: Math.max(0.12, x2 - x), h: Math.max(0.1, y2 - y) };
  };
  const rects = boxes.map(toIn);
  rects.forEach((r, i) => {
    const k = KIND[boxes[i].kind] || KIND.click;
    s.addShape(pres.shapes.RECTANGLE, { x: r.x, y: r.y, w: r.w, h: r.h, fill: { type: 'none' }, line: { color: k.color, width: 3, dashType: k.dash },
      objectName: `Highlight ${i + 1}: ${boxes[i].label || boxes[i].kind}` });
  });
  // arrow to the primary target: the first click target, else the first box
  const pi = Math.max(0, boxes.findIndex(b => b.kind === 'click'));
  if (rects.length) {
    const r = rects[pi], k = KIND[boxes[pi].kind] || KIND.click;
    const cx = r.x + r.w / 2, cy = r.y + r.h / 2;
    const icx = img.x + img.w / 2, icy = img.y + img.h / 2;
    let dx = icx - cx, dy = icy - cy;
    if (Math.hypot(dx, dy) < 0.6) { dx = -1; dy = 1; }
    const len = Math.hypot(dx, dy); dx /= len; dy /= len;
    const L = Math.min(1.35, Math.max(0.8, img.w * 0.12));
    // end point: box edge along the direction towards the start
    const tx = dx !== 0 ? (r.w / 2 + 0.04) / Math.abs(dx) : Infinity, ty = dy !== 0 ? (r.h / 2 + 0.04) / Math.abs(dy) : Infinity;
    const t = Math.min(tx, ty);
    let ex = Math.min(img.x + img.w - 0.03, Math.max(img.x + 0.03, cx + dx * t)), ey = Math.min(img.y + img.h - 0.03, Math.max(img.y + 0.03, cy + dy * t));
    let sx = ex + dx * L, sy = ey + dy * L;
    sx = Math.min(img.x + img.w - 0.05, Math.max(img.x + 0.05, sx)); sy = Math.min(img.y + img.h - 0.05, Math.max(img.y + 0.05, sy));
    const line = (color, width, name) => s.addShape(pres.shapes.LINE, {
      x: Math.min(sx, ex), y: Math.min(sy, ey), w: Math.max(0.01, Math.abs(ex - sx)), h: Math.max(0.01, Math.abs(ey - sy)),
      flipH: ex < sx, flipV: ey < sy, line: { color, width, endArrowType: 'triangle' }, objectName: name });
    line('FFFFFF', 8, 'Arrow halo');
    line(k.color, 4.5, 'Arrow to ' + (boxes[pi].label || 'target'));
  }
  // numbered markers
  rects.forEach((r, i) => {
    const d = 0.34;
    let mx = r.x - d - 0.05, my = r.y + r.h / 2 - d / 2;
    const hits = (x, y) => rects.some((o, j) => j !== i && x < o.x + o.w && x + d > o.x && y < o.y + o.h && y + d > o.y);
    if (mx < img.x || hits(mx, my)) { mx = r.x - d * 0.3; my = r.y - d - 0.04; }
    if (my < img.y || hits(mx, my)) { mx = r.x - d * 0.55; my = r.y - d * 0.55; }
    mx = Math.min(img.x + img.w - d, Math.max(img.x, mx)); my = Math.min(img.y + img.h - d, Math.max(img.y, my));
    const k = KIND[boxes[i].kind] || KIND.click;
    s.addShape(pres.shapes.OVAL, { x: mx, y: my, w: d, h: d, fill: { color: COL.navy }, line: { color: k.color, width: 2 }, objectName: `Marker ${i + 1}` });
    s.addText(String(i + 1), { x: mx, y: my, w: d, h: d, fontSize: 13, bold: true, color: 'FFFFFF', align: 'center', valign: 'middle', margin: 0, isTextBox: true, objectName: `Marker ${i + 1} number` });
  });
}

// Readability: when the controls sit in a small part of the screen, the slide
// shows a real crop of the same screenshot around them (saved in zoomed/).
const KEEP_FULL = new Set(['S02-01', 'S02-02', 'S02-03', 'S02-04', 'S02-05', 'S03-10', 'S04-01', 'S05-03', 'S05-05', 'S06-01', 'S07-06', 'S07-07', 'S07-12', 'S08-01', 'S09-02', 'S04-15']);
const ZOOM = path.join(OUT, 'ROAP_Screenshot_Evidence', 'zoomed');
async function autoZoom(step) {
  if (step.zoom || step.device !== 'desktop' || !step.boxes.length || KEEP_FULL.has(step.id)) return;
  const pad = 110;
  const x0 = Math.min(...step.boxes.map(b => b.x)) - pad, y0 = Math.min(...step.boxes.map(b => b.y)) - pad;
  const x1 = Math.max(...step.boxes.map(b => b.x + b.w)) + pad, y1 = Math.max(...step.boxes.map(b => b.y + b.h)) + pad;
  let w = Math.max(x1 - x0, 1040), h = Math.max(y1 - y0, 585);
  if (w / h > 16 / 9) h = w * 9 / 16; else w = h * 16 / 9;
  if (w > 1400) return; // the controls are spread out: keep the whole screen
  const cx = (x0 + x1) / 2, cy = (y0 + y1) / 2;
  const left = Math.round(Math.max(0, Math.min(cx - w / 2, step.image.w - w))), top = Math.round(Math.max(0, Math.min(cy - h / 2, step.image.h - h)));
  w = Math.round(w); h = Math.round(h);
  fs.mkdirSync(ZOOM, { recursive: true });
  await sharp(path.join(ORIG, step.file)).extract({ left, top, width: w, height: h }).toFile(path.join(ZOOM, step.file));
  step.crop = { left, top, width: w, height: h };
  step.image = { w, h };
  step.boxes = step.boxes.map(b => ({ ...b, x: b.x - left, y: b.y - top }));
  step.zoomedFile = 'zoomed/' + step.file;
}

function stepSlide(step) {
  const sectionName = SECTION_NAMES[step.section];
  const s = add('ROAP_STEP', `${step.section}. ${sectionName}`);
  slideOf[step.id] = slideNo;
  kicker(s, `Section ${step.section} · ${step.workflow}`, 0.2);
  s.addText(`Step ${pad2(step.n)} — ${step.title}`, { placeholder: 'title' });
  const portrait = step.image.h > step.image.w;
  const img = placeImage(s, step, 0.45, 1.3, portrait ? 8.9 : 8.95, 5.45);
  annotate(s, step, img);

  // right column
  const px = 9.75, pw = 3.13;
  let y = 1.3;
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: px, y, w: pw, h: 0.42, rectRadius: 0.08, fill: { color: COL.navy }, line: { color: COL.navy }, objectName: 'Role chip' });
  s.addText([{ text: 'ROLE  ', options: { bold: true, color: COL.gold300 } }, { text: step.role, options: { color: 'FFFFFF' } }],
    { x: px + 0.15, y, w: pw - 0.3, h: 0.42, fontSize: 11, valign: 'middle', margin: 0, isTextBox: true, fit: 'shrink' });
  y += 0.55;

  const actionLines = estLines(step.action, 31);
  const markers = step.boxes.map((b, i) => `${i + 1}  ${b.label || KIND[b.kind].word}`);
  const markerLines = markers.length;
  const actionH = 0.42 + actionLines * 0.25 + (markerLines ? 0.12 + markerLines * 0.22 : 0) + 0.12;
  card(s, px, y, pw, actionH, COL.card, 'Action card');
  s.addText(`ACTION · STEP ${pad2(step.n)} OF ${pad2(step.total)}`, { x: px + 0.18, y: y + 0.14, w: pw - 0.36, h: 0.22, fontSize: 10, bold: true, color: COL.cyanDark, charSpacing: 1, margin: 0, isTextBox: true });
  s.addText(step.action, { x: px + 0.18, y: y + 0.4, w: pw - 0.36, h: actionLines * 0.25, fontSize: 14, color: COL.ink, valign: 'top', margin: 0, isTextBox: true, lineSpacingMultiple: 0.95 });
  if (markerLines) {
    const runs = [];
    step.boxes.forEach((b, i) => {
      const k = KIND[b.kind] || KIND.click;
      runs.push({ text: `${i + 1}  `, options: { bold: true, color: k.color === 'FFFFFF' ? COL.navy : k.color } });
      runs.push({ text: (b.label || k.word) + (k.dash === 'dash' ? ' (field)' : ''), options: { color: COL.muted, breakLine: i < step.boxes.length - 1 } });
    });
    s.addText(runs, { x: px + 0.18, y: y + 0.4 + actionLines * 0.25 + 0.1, w: pw - 0.36, h: markerLines * 0.22, fontSize: 11, valign: 'top', margin: 0, isTextBox: true });
  }
  y += actionH + 0.18;

  const expLines = estLines(step.expected, 31);
  const expH = 0.42 + expLines * 0.23 + 0.12;
  card(s, px, y, pw, expH, COL.azure, 'Expected result card');
  s.addText('EXPECTED RESULT', { x: px + 0.18, y: y + 0.14, w: pw - 0.36, h: 0.22, fontSize: 10, bold: true, color: COL.gold300, charSpacing: 1, margin: 0, isTextBox: true });
  s.addText(step.expected, { x: px + 0.18, y: y + 0.4, w: pw - 0.36, h: expLines * 0.23, fontSize: 13, color: 'FFFFFF', valign: 'top', margin: 0, isTextBox: true, lineSpacingMultiple: 0.95 });
  y += expH + 0.18;

  if (step.warning) {
    const wl = estLines(step.warning, 34);
    const wh = 0.42 + wl * 0.22 + 0.1;
    if (y + wh <= 6.8) {
      card(s, px, y, pw, wh, COL.amberTint, 'Warning card');
      s.addText('IMPORTANT', { x: px + 0.18, y: y + 0.14, w: pw - 0.36, h: 0.22, fontSize: 10, bold: true, color: 'B45309', charSpacing: 1, margin: 0, isTextBox: true });
      s.addText(step.warning, { x: px + 0.18, y: y + 0.4, w: pw - 0.36, h: wl * 0.22, fontSize: 12, color: COL.ink, valign: 'top', margin: 0, isTextBox: true });
      y += wh + 0.18;
    } else console.warn('warning card does not fit:', step.id);
  }
  if (y > 6.85) console.warn('right column overflows:', step.id, y.toFixed(2));

  // footer: progress dots, role, next
  const fy = 7.08;
  const dots = Math.min(step.total, 16);
  for (let i = 0; i < dots; i++) {
    s.addShape(pres.shapes.OVAL, { x: 0.45 + i * 0.2, y: fy + 0.07, w: 0.12, h: 0.12, fill: { color: i < step.n ? COL.cyanDark : COL.line }, line: { type: 'none' }, objectName: 'Progress dot' });
  }
  const after = 0.45 + dots * 0.2 + 0.1;
  s.addText(`Step ${pad2(step.n)} of ${pad2(step.total)}  ·  ${step.role}  ·  ${DOC}`, { x: after, y: fy, w: 7.2, h: 0.28, fontSize: 10, color: COL.faint, margin: 0, isTextBox: true });
  const nextTxt = step.n < step.total ? 'Next: step ' + pad2(step.n + 1) : 'End of this workflow';
  s.addText(nextTxt + (step.n < step.total ? '  →' : ''), { x: 9.75, y: fy, w: 2.35, h: 0.28, fontSize: 10, bold: true, color: COL.cyanDark, align: 'right', margin: 0, isTextBox: true });
  if (!SCRIPT[step.id]) console.warn('no script for', step.id);
  s.addNotes(`${SCRIPT[step.id] || step.action}\n\n—\nReference: ${step.id} · ${step.role} · ${step.url}\nAction: ${step.action}\nExpected: ${step.expected}${step.warning ? '\nImportant: ' + step.warning : ''}\nEvidence: ROAP_Screenshot_Evidence/original/${step.file}${step.zoomedFile ? ' (shown as the real crop ' + step.zoomedFile + ')' : ''} (viewport ${step.viewport}${step.zoom ? ', real crop' : ''}, captured ${step.time}).`);
}

// ---------------------------------------------------------------- other slides
function divider(num, title, sub, iconData) {
  const s = add('ROAP_DIVIDER', `${num}. ${title}`);
  kicker(s, num === 'M' ? 'On a phone' : `Section ${num}`, 2.5, COL.gold300, 0.8);
  s.addText(title, { placeholder: 'title' });
  s.addText(sub, { x: 0.8, y: 4.3, w: 8.6, h: 1.0, fontSize: 18, color: 'FBE4E3', valign: 'top', margin: 0, isTextBox: true });
  if (SCRIPT['div' + num]) s.addNotes(SCRIPT['div' + num]);
  if (iconData) {
    s.addShape(pres.shapes.OVAL, { x: 10.2, y: 2.55, w: 2.1, h: 2.1, fill: { color: COL.navy }, line: { color: COL.frameGold, width: 2 } });
    s.addImage({ data: iconData, x: 10.75, y: 3.1, w: 1.0, h: 1.0 });
  }
  return s;
}
function contentSlide(section, title, kick, noteKey) {
  const s = add('ROAP_CONTENT', section);
  if (noteKey) { if (!SCRIPT[noteKey]) console.warn('no script for', noteKey); else s.addNotes(SCRIPT[noteKey]); }
  if (kick) kicker(s, kick, 0.3);
  s.addText(title, { placeholder: 'title' });
  return s;
}
function table(s, rows, opts) {
  const head = rows[0].map(t => ({ text: t, options: { bold: true, color: 'FFFFFF', fill: { color: COL.navy }, fontSize: opts.headSize || 12 } }));
  const body = rows.slice(1).map((r, i) => r.map(t => {
    const o = { fontSize: opts.fontSize || 12, color: COL.ink, fill: { color: i % 2 ? COL.bg : 'FFFFFF' } };
    if (typeof t === 'object') return { text: t.text, options: { ...o, ...t.options } };
    return { text: t, options: o };
  }));
  const rowH = Math.min(opts.maxRow || 0.55, (opts.h || 5.3) / rows.length);
  s.addTable([head, ...body], { x: opts.x ?? 0.6, y: opts.y ?? 1.55, w: opts.w ?? 12.1, colW: opts.colW, rowH, border: { type: 'solid', color: COL.line, pt: 0.75 }, margin: 0.06, valign: 'middle', autoPage: false });
}
const statusCell = st => ({ text: st, options: { bold: true, color: st === 'Verified' ? '15803D' : st === 'NOT IMPLEMENTED' ? 'B91C1C' : st === 'NOT VERIFIED' ? 'B45309' : COL.ink } });

function iconCards(s, items, cols, x0, y0, cw, ch, gap) {
  items.forEach((it, i) => {
    const x = x0 + (i % cols) * (cw + gap), y = y0 + Math.floor(i / cols) * (ch + gap);
    card(s, x, y, cw, ch, COL.card, 'Card ' + it.title);
    s.addShape(pres.shapes.OVAL, { x: x + 0.22, y: y + 0.22, w: 0.56, h: 0.56, fill: { color: it.color || COL.azure }, line: { type: 'none' } });
    s.addImage({ data: it.icon, x: x + 0.355, y: y + 0.355, w: 0.29, h: 0.29 });
    s.addText(it.title, { x: x + 0.95, y: y + 0.2, w: cw - 1.1, h: 0.4, fontSize: 16, bold: true, color: COL.navy, fontFace: 'Cambria', margin: 0, valign: 'middle', isTextBox: true });
    s.addText(it.text, { x: x + 0.95, y: y + 0.62, w: cw - 1.1, h: ch - 0.75, fontSize: it.size || 14, color: COL.muted, margin: 0, valign: 'top', isTextBox: true });
  });
}

function flow(s, nodes, edges, opts = {}) {
  // nodes: {id, x, y, w, h, text, kind}  edges: [from, to, label, fromSide, toSide]
  const fill = { start: COL.navy, step: 'FFFFFF', decision: 'FDECD9', ok: 'DCFCE7', stop: 'FBE4E3', info: 'DFE6F7' };
  const txt = { start: 'FFFFFF' };
  const byId = {};
  nodes.forEach(n => { byId[n.id] = n; });
  const anchor = (n, side) => ({ r: [n.x + n.w, n.y + n.h / 2], l: [n.x, n.y + n.h / 2], t: [n.x + n.w / 2, n.y], b: [n.x + n.w / 2, n.y + n.h] }[side]);
  edges.forEach(([a, b, label, sa = 'r', sb = 'l']) => {
    const [x1, y1] = anchor(byId[a], sa), [x2, y2] = anchor(byId[b], sb);
    s.addShape(pres.shapes.LINE, { x: Math.min(x1, x2), y: Math.min(y1, y2), w: Math.max(0.01, Math.abs(x2 - x1)), h: Math.max(0.01, Math.abs(y2 - y1)),
      flipH: x2 < x1, flipV: y2 < y1, line: { color: '64748B', width: 1.75, endArrowType: 'triangle' }, objectName: `Flow ${a} to ${b}` });
    if (label) {
      const vertical = Math.abs(x2 - x1) < 0.05, horizontal = Math.abs(y2 - y1) < 0.05;
      const lx = horizontal ? (x1 + x2) / 2 - 0.75 : (x1 + x2) / 2 + 0.08;
      const ly = horizontal ? Math.min(byId[a].y, byId[b].y) - 0.3 : (y1 + y2) / 2 - 0.12;
      s.addText(label, { x: lx, y: ly, w: 1.5, h: 0.24, fontSize: 10, italic: true, color: COL.muted, align: horizontal ? 'center' : 'left', margin: 0, isTextBox: true });
    }
  });
  nodes.forEach(n => {
    s.addShape(n.kind === 'decision' ? pres.shapes.ROUNDED_RECTANGLE : pres.shapes.ROUNDED_RECTANGLE, { x: n.x, y: n.y, w: n.w, h: n.h, rectRadius: 0.08,
      fill: { color: fill[n.kind || 'step'] }, line: { color: n.kind === 'start' ? COL.navy : 'C6B5AE', width: 1 }, objectName: 'Flow node ' + n.id });
    s.addText(n.text, { x: n.x + 0.06, y: n.y, w: n.w - 0.12, h: n.h, fontSize: opts.fontSize || 12, bold: n.kind === 'start', color: txt[n.kind] || COL.ink, align: 'center', valign: 'middle', margin: 0, isTextBox: true });
  });
}

(async () => {
  const I = {};
  for (const [k, n, c] of [
    ['ship', 'FaShip'], ['user', 'FaUserPlus'], ['file', 'FaFileSignature'], ['shield', 'FaShieldHalved'], ['sitemap', 'FaSitemap'],
    ['check', 'FaClipboardCheck'], ['route', 'FaRoute'], ['cal', 'FaCalendarCheck'], ['flag', 'FaFlagCheckered'], ['folder', 'FaFolderOpen'],
    ['grad', 'FaGraduationCap'], ['chart', 'FaChartColumn'], ['medal', 'FaMedal'], ['lock', 'FaLock'], ['users', 'FaUsersGear'],
    ['eye', 'FaEye'], ['mobile', 'FaMobileScreen'], ['id', 'FaIdCard'], ['globe', 'FaGlobe'], ['user1', 'FaUser'],
  ]) I[k] = await icon(n, c || 'F5B672');
  for (const st of steps) await autoZoom(st);
  if (COMPACT) {
    fs.mkdirSync(JPG, { recursive: true });
    for (const st of steps.concat(phone)) {
      const src = st.zoomedFile ? path.join(ORIG, '..', st.zoomedFile) : path.join(ORIG, st.file);
      await sharp(src).flatten({ background: '#ffffff' }).jpeg({ quality: 88, chromaSubsampling: '4:4:4', mozjpeg: true }).toFile(media(src));
    }
  }
  const shot = id => DATA.steps.find(s => s.id === id);

  // the hero band of the website: azure on the left meets crimson, as a 16:9 picture
  const bandSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><defs><linearGradient id="g" x1="0" y1="0.2" x2="1" y2="0.8">
    <stop offset="0" stop-color="#2c3766"/><stop offset="0.2" stop-color="#2c3766"/><stop offset="0.34" stop-color="#7e1f26"/>
    <stop offset="0.62" stop-color="#641a20"/><stop offset="1" stop-color="#3d0f13"/></linearGradient></defs><rect width="1920" height="1080" fill="url(#g)"/></svg>`;
  await sharp(Buffer.from(bandSvg)).png().toFile(BAND);
  const logo = 'image/png;base64,' + (await sharp(path.join(APP, 'public/images/brand/pcg-logo.webp')).resize(256).png().toBuffer()).toString('base64');
  const bannerPath = path.join(APP, 'public/images/pcg/commands-banner-2400.jpg');

  // ---- 1. cover (the website hero: band, eyebrow, serif headline with a gold line, the official banner)
  let s = add('ROAP_COVER', '1. Introduction');
  s.addImage({ data: logo, x: 0.8, y: 0.7, w: 0.75, h: 0.75, altText: 'Philippine Coast Guard seal' });
  s.addText([{ text: 'PCG HRMIS', options: { fontFace: 'Cambria', bold: true, fontSize: 20, color: 'FFFFFF', breakLine: true } },
    { text: 'PHILIPPINE COAST GUARD · HUMAN RESOURCES', options: { fontSize: 10, color: 'F6C9C8', charSpacing: 2 } }],
    { x: 1.7, y: 0.72, w: 6, h: 0.75, margin: 0, valign: 'middle', isTextBox: true });
  kicker(s, 'End-to-end walkthrough', 1.85, COL.gold300, 0.8);
  s.addText([{ text: 'From the front page', options: { breakLine: true } }, { text: 'to re-enlistment.', options: { color: COL.gold300 } }], { placeholder: 'title' });
  s.addText('Every step of PCG HRMIS and MEPCOM HRMIS, shown on the real screens: landing pages, registration, the SOI and documents, administration, units, processing and re-enlistment.',
    { x: 0.8, y: 4.35, w: 6.3, h: 1.3, fontSize: 17, color: 'FBE4E3', valign: 'top', margin: 0, isTextBox: true });
  s.addText(`Captured ${DATA.captured.slice(0, 10)} on the development copy, with sample data only`, { x: 0.8, y: 6.6, w: 7, h: 0.3, fontSize: 11, color: 'F6C9C8', margin: 0, isTextBox: true });
  s.addShape(pres.shapes.RECTANGLE, { x: 7.55, y: 1.6, w: 5.3, h: 2.65 + 0.1, fill: { color: COL.navy }, line: { color: COL.frameGold, width: 2 }, shadow: { type: 'outer', color: '000000', opacity: 0.45, blur: 16, offset: 5, angle: 90 } });
  s.addImage({ path: bannerPath, x: 7.6, y: 1.65, w: 5.2, h: 2.6, altText: 'Official PCG banner of its three functional commands: MSSC, MEPCOM and MARSLEC' });
  s.addText('Official banner: ASN Francis Xavier Bucoy II, PCG. Used with permission.', { x: 7.55, y: 4.42, w: 5.3, h: 0.25, fontSize: 9, color: 'F6C9C8', align: 'right', margin: 0, isTextBox: true });
  s.addNotes(SCRIPT.cover);

  // ---- 2. about this walkthrough
  s = contentSlide('1. Introduction', 'What this walkthrough covers', 'Read first');
  iconCards(s, [
    { icon: I.eye, title: 'Real screens only', text: 'Every step was performed in the running application and photographed as it happened. Nothing is mocked up or redrawn.' },
    { icon: I.id, title: 'Sample data', text: 'All people, serial numbers (DEMO-xxxx) and emails (@demo.test) come from the development seeder. No real personnel record appears.' },
    { icon: I.flag, title: 'Two systems, one gateway', text: 'PCG HRMIS is the main landing page of the Coast Guard. MEPCOM HRMIS, the personnel system of the Marine Environmental Protection Command, runs on it today.', color: COL.navy2 },
    { icon: I.lock, title: 'Honest gaps', text: 'Steps the system does not have yet are marked NOT IMPLEMENTED (section 9) instead of being invented.', color: COL.navy },
  ], 2, 0.6, 1.55, 5.95, 2.35, 0.25);
  s.addNotes(SCRIPT.about);

  // ---- 3. purpose
  s = contentSlide('1. Introduction', 'One system for personnel records, documents and training', 'System overview and purpose', 'purpose');
  iconCards(s, [
    { icon: I.user, title: 'Registration', text: 'Officers and non-officers register online with a verification photo; HR approves each account.' },
    { icon: I.file, title: 'Statement of Information', text: 'Members complete their SOI in six steps and submit it; it then locks.' },
    { icon: I.check, title: 'Document review', text: 'Unit checkers review required documents and 201 files, approve them or return items with a comment.' },
    { icon: I.sitemap, title: 'Personnel master', text: 'HR registers personnel, assigns them to units in the organization tree, verifies 201 documents, runs reports.' },
    { icon: I.grad, title: 'Training', text: 'Members apply for administrative and operational track courses; MEPTDI runs courses and OSAERT certificates.' },
    { icon: I.cal, title: 'Re-enlistment', text: 'A watchlist of non-officers whose end of term of enlistment (ETE) is near.' },
  ], 3, 0.6, 1.55, 3.9, 2.3, 0.2);

  // ---- 4. roles
  s = contentSlide('1. Introduction', 'Who uses the system', 'Available user roles', 'roles');
  table(s, [
    ['Role', 'Signs in at', 'What the role can do (from the permission matrix)'],
    ['Applicant / member', '/login', 'Register; SOI; required documents; 201 files; training applications; print SOI'],
    ['Unit checker', '/unit/login', 'Review documents and 201 files of members whose mother unit is theirs'],
    ['M1 checker', '/unit/loginM1', 'Same review for all units; disciplinary actions'],
    ['Super admin', '/admin/login', 'Everything, including admin accounts, organization changes and the audit log'],
    ['HR admin', '/admin/login', 'Registrations, personnel, 201 documents, reports, self-registration approval, unit accounts, SOI reopen/edit, ETE list'],
    ['Records reviewer', '/admin/login', 'Reviews registrations (endorse / return) and verifies documents; no approval'],
    ['Records approver', '/admin/login', 'Approves or rejects registrations, verifies documents, restricted documents'],
    ['Auditor', '/admin/login', 'Read-only: personnel, documents list, reports, ETE list and audit log'],
    ['Adjutant', '/adjutant/login', 'CG awards and schooling in members\' SOI; SOI submissions (reopen / edit), own command'],
    ['Re-enlistment admin', '/reenlistment/login', 'ETE watchlist, member details and CSV export only (password-only sign-in)'],
    ['LMS admin', '/admin/login', 'Training tracks, applications, MEPTDI courses, students, OSAERT'],
  ], { colW: [2.4, 2.1, 7.6], fontSize: 13, y: 1.5, h: 5.35 });

  // ---- 5. process map
  s = contentSlide('1. Introduction', 'End-to-end process map', 'Verified transitions', 'map');
  flow(s, [
    { id: 'reg', x: 0.6, y: 1.65, w: 1.9, h: 0.75, text: 'Register online\n(applicant)', kind: 'start' },
    { id: 'pend', x: 2.95, y: 1.65, w: 1.7, h: 0.75, text: 'Account Pending' },
    { id: 'hr', x: 5.1, y: 1.65, w: 1.9, h: 0.75, text: 'HR reviews\nSelf-registrations', kind: 'decision' },
    { id: 'rej', x: 5.1, y: 2.75, w: 1.9, h: 0.55, text: 'Rejected: cannot sign in', kind: 'stop' },
    { id: 'in', x: 7.45, y: 1.65, w: 1.7, h: 0.75, text: 'Member signs in', kind: 'ok' },
    { id: 'soi', x: 9.6, y: 1.65, w: 1.6, h: 0.75, text: 'SOI: 6 steps' },
    { id: 'sub', x: 11.45, y: 1.65, w: 1.3, h: 0.75, text: 'Submit:\nread-only', kind: 'info' },
    { id: 'docs', x: 7.45, y: 3.75, w: 1.7, h: 0.75, text: 'Upload documents\nand 201 files' },
    { id: 'uc', x: 9.6, y: 3.75, w: 1.6, h: 0.75, text: 'Unit checker\nreviews', kind: 'decision' },
    { id: 'ok', x: 11.45, y: 3.45, w: 1.3, h: 0.55, text: 'Approved', kind: 'ok' },
    { id: 'ret', x: 11.45, y: 4.3, w: 1.3, h: 0.55, text: 'Returned', kind: 'stop' },
    { id: 'hrreg', x: 0.6, y: 5.55, w: 1.9, h: 0.75, text: 'HR: new\nregistration', kind: 'start' },
    { id: 'rv', x: 2.95, y: 5.55, w: 1.7, h: 0.75, text: 'Reviewer:\nendorse / return', kind: 'decision' },
    { id: 'ap', x: 5.1, y: 5.55, w: 1.9, h: 0.75, text: 'Approver:\napprove / reject', kind: 'decision' },
    { id: 'pm', x: 7.45, y: 5.55, w: 1.7, h: 0.75, text: 'Personnel record\n+ assignment', kind: 'ok' },
    { id: 'ete', x: 9.6, y: 5.55, w: 3.15, h: 0.75, text: 'Non-officers by ETE:\nre-enlistment watchlist', kind: 'info' },
  ], [
    ['reg', 'pend'], ['pend', 'hr'], ['hr', 'in'], ['hr', 'rej', 'Reject', 'b', 't'], ['in', 'soi'], ['soi', 'sub'],
    ['in', 'docs', '', 'b', 't'], ['docs', 'uc'], ['uc', 'ok', '', 'r', 'l'], ['uc', 'ret', '', 'r', 'l'], ['ret', 'docs', '', 'b', 'b'],
    ['hrreg', 'rv'], ['rv', 'ap'], ['ap', 'pm'], ['pm', 'ete'],
  ], { fontSize: 11 });
  s.addText('Applicant path', { x: 0.6, y: 1.32, w: 3, h: 0.26, fontSize: 11, bold: true, color: COL.cyanDark, margin: 0, isTextBox: true });
  s.addText('Documents path', { x: 7.45, y: 3.42, w: 3, h: 0.26, fontSize: 11, bold: true, color: COL.cyanDark, margin: 0, isTextBox: true });
  s.addText('Returned items: the member uploads again, and the checker reviews again.', { x: 7.45, y: 5.0, w: 5.3, h: 0.26, fontSize: 10, italic: true, color: COL.muted, margin: 0, isTextBox: true });
  s.addText('HR registration path (personnel master)', { x: 0.6, y: 5.22, w: 5, h: 0.26, fontSize: 11, bold: true, color: COL.cyanDark, margin: 0, isTextBox: true });

  // ---- 6. legend
  s = contentSlide('1. Introduction', 'How to read the step slides', 'Annotation legend', 'legend');
  const lg = [['click', 'Gold outline and arrow', 'Click, open or navigate here'], ['field', 'Blue dashed outline', 'Field or area to complete'],
    ['result', 'Green outline', 'The successful result to look for'], ['caution', 'Yellow outline', 'Important requirement or caution'], ['error', 'Red outline', 'Error or blocked action']];
  lg.forEach(([k, a, b], i) => {
    const y = 1.6 + i * 0.85;
    s.addShape(pres.shapes.RECTANGLE, { x: 0.8, y, w: 1.3, h: 0.55, fill: { color: 'FFFFFF' }, line: { color: KIND[k].color, width: 3, dashType: KIND[k].dash } });
    s.addText(a, { x: 2.35, y, w: 3.4, h: 0.3, fontSize: 15, bold: true, color: COL.navy, margin: 0, isTextBox: true });
    s.addText(b, { x: 2.35, y: y + 0.3, w: 3.4, h: 0.28, fontSize: 12, color: COL.muted, margin: 0, isTextBox: true });
  });
  card(s, 6.4, 1.6, 6.3, 4.1, COL.card, 'Layout explainer');
  s.addText([
    { text: 'Header', options: { bold: true, color: COL.navy, breakLine: true } },
    { text: 'Section and workflow, then the step number and its title.', options: { color: COL.muted, breakLine: true } },
    { text: 'Screenshot', options: { bold: true, color: COL.navy, breakLine: true } },
    { text: 'The real screen. Numbered markers match the list in the Action card; the arrow points at the control to use.', options: { color: COL.muted, breakLine: true } },
    { text: 'Action and Expected result', options: { bold: true, color: COL.navy, breakLine: true } },
    { text: 'One action per step, with the exact labels on screen, and what you should see next.', options: { color: COL.muted, breakLine: true } },
    { text: 'Footer', options: { bold: true, color: COL.navy, breakLine: true } },
    { text: 'Progress through the workflow, the role, and the next step. Zoomed screens are real crops of the same capture.', options: { color: COL.muted } },
  ], { x: 6.7, y: 1.85, w: 5.7, h: 3.7, fontSize: 14, valign: 'top', margin: 0, paraSpaceAfter: 4, isTextBox: true });

  // ---- sections with steps
  const sectionIntro = {
    '2': ['Landing pages', 'PCG HRMIS, the main landing page with the three commands and the organizational structure, then MEPCOM HRMIS, where registration and sign-in begin.', I.globe],
    '3': ['Applicant registration', 'Register as an officer or non-officer, wait for HR approval, then sign in to your dashboard.', I.user],
    '4': ['Applicant application workflow', 'Complete and submit the SOI, upload required documents and 201 files, answer returns, apply for training.', I.file],
    '5': ['Administrator access', 'Sign in with the second step, find your way around the console, and see the personnel database.', I.shield],
    '6': ['Admin unit management', 'Create, edit and deactivate units in the organization tree; list a unit\'s personnel; give a unit its checker account.', I.sitemap],
    '7': ['Applicant management', 'Approve self-registrations, review documents and 201 files as a unit checker, and manage submitted SOIs.', I.check],
    '8': ['Recruitment processing', 'Register personnel through Draft, Submitted, Endorsed and Approved; verify 201 documents; run reports and exports.', I.route],
    '9': ['Re-enlistment workflow', 'Find non-officers whose term of enlistment is ending and check their re-enlistment form status.', I.cal],
  };
  for (const sec of ['2', '3', '4', '5', '6', '7', '8', '9']) {
    const [t, sub, ic] = sectionIntro[sec];
    divider(sec, t, sub, ic);
    if (sec === '4') {
      s = contentSlide(`4. ${t}`, 'Application types in this system', 'Before you start', 'types');
      table(s, [
        ['Requested item', 'What the system actually has', 'Status'],
        ['Select application type', 'Registration type is chosen before registering: Officer or Non-officer (/register?type=…)', statusCell('Verified')],
        ['Personal information', 'The SOI, six steps (Basic, Personal, Dependents, Education and training, Appointments and duties, Awards and career)', statusCell('Verified')],
        ['Upload required documents', '10 required documents plus residence pin; 201 files in 11 sections', statusCell('Verified')],
        ['Review before submitting', '"Is the data you entered correct?" prompt before the SOI is submitted', statusCell('Verified')],
        ['Track status', 'Dashboard: review status, returned documents with comments; page banners while waiting', statusCell('Verified')],
        ['Respond to corrections', 'Returned items reopen with the checker\'s comment; upload again', statusCell('Verified')],
        ['Apply for a position / recruitment slot', 'No such application exists; "applications" in the system are training applications', statusCell('NOT IMPLEMENTED')],
      ], { colW: [3.0, 7.0, 2.1], fontSize: 14, h: 5.2 });
    }
    if (sec === '8') {
      s = contentSlide(`8. ${t}`, 'Processing stages and who acts at each', 'Verified states', 'stages');
      flow(s, [
        { id: 'd', x: 0.6, y: 1.8, w: 1.9, h: 0.8, text: 'Draft\n(HR admin)', kind: 'start' },
        { id: 's', x: 3.0, y: 1.8, w: 1.9, h: 0.8, text: 'Submitted' },
        { id: 'e', x: 5.4, y: 1.8, w: 2.1, h: 0.8, text: 'Endorsed\n(records reviewer)', kind: 'decision' },
        { id: 'r', x: 3.0, y: 3.25, w: 1.9, h: 0.8, text: 'Returned for\ncorrection', kind: 'stop' },
        { id: 'a', x: 8.0, y: 1.8, w: 2.1, h: 0.8, text: 'Approved\n(records approver)', kind: 'ok' },
        { id: 'x', x: 8.0, y: 3.25, w: 2.1, h: 0.8, text: 'Rejected\n(remarks required)', kind: 'stop' },
        { id: 'p', x: 10.6, y: 1.8, w: 2.1, h: 0.8, text: 'Personnel record\n+ assignment', kind: 'ok' },
      ], [['d', 's', 'Save and submit'], ['s', 'e', 'Endorse'], ['s', 'r', 'Return', 'b', 't'], ['r', 'd', 'HR corrects', 'l', 'b'], ['e', 'a', 'Approve'], ['e', 'x', 'Reject', 'b', 't'], ['a', 'p']]);
      s.addText([
        { text: 'The author of a registration cannot decide it; each decision is recorded in History and the audit log.', options: { bullet: true, breakLine: true } },
        { text: 'Approvers can also decide a Submitted registration directly (the endorse step is the reviewer\'s recommendation).', options: { bullet: true, breakLine: true } },
        { text: 'Self-registrations follow a simpler path: Pending, then Approved or Rejected (section 7).', options: { bullet: true } },
      ], { x: 0.6, y: 4.55, w: 12, h: 1.6, fontSize: 14, color: COL.ink, valign: 'top', margin: 0, paraSpaceAfter: 6, isTextBox: true });
    }
    if (sec === '9') {
      s = contentSlide(`9. ${t}`, 'Re-enlistment: what exists and what does not', 'Prerequisites and coverage', 'reenlistment');
      table(s, [
        ['Requested step', 'In the system', 'Status'],
        ['Eligible records and prerequisites', 'Non-officer accounts (registration type non_officer) with an ETE; ETE defaults to 3 years after the date of appointment when not given', statusCell('Verified')],
        ['Open the re-enlistment function', 'Re-enlistment admin signs in at /reenlistment/login; ETE watchlist at /admin/reenlistment', statusCell('Verified')],
        ['Review existing details', 'Member page: term of enlistment, account, re-enlistment form status (the SOI)', statusCell('Verified')],
        ['Submit required information', 'The member\'s SOI is the re-enlistment form (section 4); "Submitted" once it is saved', statusCell('Verified')],
        ['Initiate re-enlistment as a case', 'No action to start a re-enlistment case', statusCell('NOT IMPLEMENTED')],
        ['Verification, approval or disposition', 'The module is read-only: no approve, reject or decision step', statusCell('NOT IMPLEMENTED')],
        ['Resulting record and status', 'No re-enlistment status is stored; ETE is not changed by the module', statusCell('NOT IMPLEMENTED')],
        ['Export and print', 'Export CSV of the list, Print of a member page (both audited)', statusCell('Verified')],
      ], { colW: [3.0, 7.0, 2.1], fontSize: 13, h: 5.3 });
    }
    steps.filter(x => x.section === sec).forEach(stepSlide);
  }

  // ---- phones
  s = divider('M', 'Phone screens', 'The same pages at 390 × 844: menus collapse, tables scroll inside their frame.', I.mobile);
  for (let i = 0; i < phone.length; i += 3) {
    s = contentSlide('Phone screens', i ? 'Phone screens (continued)' : 'The pages work on a phone', 'Responsive check at 390 × 844', 'phones');
    phone.slice(i, i + 3).forEach((p, j) => {
      const x = 0.9 + j * 4.15;
      const img = placeImage(s, p, x, 1.5, 2.45, 5.0);
      annotate(s, p, img);
      s.addText(p.title, { x: x + 2.65, y: 1.6, w: 1.3, h: 1.0, fontSize: 14, bold: true, color: COL.navy, valign: 'top', margin: 0, isTextBox: true });
      s.addText(p.action, { x: x + 2.65, y: 2.65, w: 1.3, h: 2.6, fontSize: 11, color: COL.muted, valign: 'top', margin: 0, isTextBox: true });
      slideOf[p.id] = slideNo;
    });
  }

  // ---- 10. completion
  divider('10', 'Completion', 'Summary, common errors, who does what, questions and a final checklist.', I.flag);
  s = contentSlide('10. Completion', 'The whole workflow on one page', 'Summary', 'summary');
  table(s, [
    ['#', 'Workflow', 'Role', 'Where', 'Slides'],
    ['1', 'Register and wait for approval', 'Applicant', '/register → /login', ''],
    ['2', 'Approve a self-registration', 'HR admin / super admin', 'Self-registrations', ''],
    ['3', 'Complete and submit the SOI', 'Applicant', '/profile', ''],
    ['4', 'Upload documents and 201 files; answer returns', 'Applicant', '/upload-documents, /upload-201', ''],
    ['5', 'Review documents and 201 files', 'Unit / M1 checker', '/unit/dashboard, /unit/201-files', ''],
    ['6', 'Create and maintain units', 'Super admin', '/admin/organization', ''],
    ['7', 'Register personnel (draft to approved)', 'HR admin, reviewer, approver', '/admin/registrations', ''],
    ['8', 'Verify 201 documents, run reports', 'Reviewer / approver / HR', '/admin/documents, /admin/reports', ''],
    ['9', 'Correct or reopen a submitted SOI', 'Adjutant / HR admin', '/admin/soi', ''],
    ['10', 'Watch ETE and export', 'Re-enlistment admin', '/admin/reenlistment', ''],
  ].map((r, i) => {
    if (!i) return r;
    const ranges = { '1': ['S03-01', 'S03-10'], '2': ['S07-01', 'S07-04'], '3': ['S04-01', 'S04-08'], '4': ['S04-09', 'S04-13'], '5': ['S07-05', 'S07-12'], '6': ['S06-01', 'S06-09'], '7': ['S08-01', 'S08-08'], '8': ['S08-09', 'S08-11'], '9': ['S07-13', 'S07-15'], '10': ['S09-01', 'S09-06'] }[r[0]];
    r[4] = `${slideOf[ranges[0]]}–${slideOf[ranges[1]]}`;
    return r;
  }), { colW: [0.5, 4.3, 2.7, 3.4, 1.2], fontSize: 14, h: 5.3 });

  s = contentSlide('10. Completion', 'Common errors and how to resolve them', 'Messages as they appear in the system', 'errors');
  table(s, [
    ['What you see', 'Why', 'What to do'],
    ['"Your account is waiting for admin approval."', 'The registration is still Pending', 'Wait for HR to approve it in Self-registrations'],
    ['"Unable to edit… contact your Adjutant or HR Unit Admin"', 'The SOI was submitted and is read-only', 'Ask the Adjutant or HR admin to correct it (Edit SOI) or reopen it'],
    ['Valid until flagged on a clearance', 'NBI, Ombudsman and Sandiganbayan clearances need a valid-until date (today or later)', 'Enter the date printed on the clearance, then Save and upload'],
    ['Every box and "Save and upload" locked', 'The record is waiting for the unit checker', 'Wait for the review; returned items reopen afterwards'],
    ['Returning without a comment does nothing', 'The checker must say what to fix', 'Type a comment on each returned item'],
    ['Second-step code refused', 'The code expired or was mistyped', 'Request a new code (email) or use the current one from the app'],
    ['Too many sign-in attempts', 'Sign-in forms are rate-limited per email', 'Wait a minute, then try again'],
    ['A page is missing from your sidebar', 'Your role does not include it', 'Ask the super admin to check your role (Admin accounts)'],
  ], { colW: [4.0, 4.1, 4.0], fontSize: 13, h: 5.3, maxRow: 0.62 });

  s = contentSlide('10. Completion', 'Role responsibility matrix', 'Who does what', 'matrix');
  const Y = { text: '●', options: { color: COL.cyanDark, bold: true, align: 'center' } }, N = { text: '', options: {} }, R = { text: 'view', options: { color: COL.muted, align: 'center' } };
  table(s, [
    ['Task', 'Applicant', 'Unit checker', 'HR admin', 'Reviewer', 'Approver', 'Super admin', 'Adjutant', 'Re-enlist.', 'Auditor'],
    ['Register / SOI / uploads', Y, N, N, N, N, N, N, N, N],
    ['Approve self-registrations', N, N, Y, N, N, Y, N, N, N],
    ['Review documents and 201 files', N, Y, N, N, N, N, N, N, N],
    ['Create / edit units', N, N, R, R, R, Y, N, N, R],
    ['Create unit checker accounts', N, N, Y, N, N, Y, N, N, N],
    ['New personnel registration', N, N, Y, N, N, Y, N, N, N],
    ['Endorse / return a registration', N, N, Y, Y, Y, Y, N, N, N],
    ['Approve / reject a registration', N, N, Y, N, Y, Y, N, N, N],
    ['Verify 201 documents', N, N, Y, Y, Y, Y, N, N, N],
    ['Reports', N, N, Y, Y, Y, Y, N, N, R],
    ['Reopen / edit a submitted SOI', N, N, Y, N, N, Y, Y, N, N],
    ['ETE watchlist and export', N, N, Y, N, N, Y, N, Y, R],
    ['Audit log', N, N, N, N, N, Y, N, N, R],
  ], { colW: [3.3, 0.98, 0.98, 0.98, 0.98, 0.98, 0.98, 0.98, 0.98, 0.98], fontSize: 13, headSize: 12, h: 5.3 });

  s = contentSlide('10. Completion', 'Frequently asked questions', 'FAQ', 'faq');
  const faq = [
    ['I registered. Why can\'t I sign in?', 'New accounts are Pending until an HR administrator approves them in Self-registrations.'],
    ['I made a mistake after submitting my SOI.', 'Contact your Adjutant or HR Unit Admin. They can correct it for you or reopen it so you can.'],
    ['Why can\'t I change a document that was accepted?', 'Accepted documents stay locked. Only items the checker returns, empty boxes and expired clearances open.'],
    ['Who sees my uploaded files?', 'Your unit checker, M1 checkers and authorised HR staff. Files are encrypted and every opening is logged.'],
    ['How does a unit get a checker?', 'The HR admin or super admin creates an account in Unit accounts for that unit.'],
    ['Can re-enlistment be approved in the system?', 'No. The module lists non-officers by ETE and shows whether their form (SOI) is in; decisions happen outside it.'],
  ];
  faq.forEach(([q, a], i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.55 + Math.floor(i / 2) * 1.75;
    card(s, x, y, 5.95, 1.55, COL.card, 'FAQ ' + (i + 1));
    s.addText(q, { x: x + 0.25, y: y + 0.18, w: 5.45, h: 0.4, fontSize: 15, bold: true, color: COL.navy, margin: 0, isTextBox: true });
    s.addText(a, { x: x + 0.25, y: y + 0.62, w: 5.45, h: 0.8, fontSize: 13, color: COL.muted, valign: 'top', margin: 0, isTextBox: true });
  });

  s = contentSlide('10. Completion', 'Final checklist', 'Before you finish', 'checklist');
  const ck = [
    ['Applicant', ['Account approved and signed in', 'SOI: all six steps saved, then submitted', 'Required documents uploaded with valid-until dates', '201 files uploaded', 'Returned items answered']],
    ['Unit checker', ['Waiting queue at zero', 'Every return has a comment', '201 file queue reviewed']],
    ['Administrators', ['Pending self-registrations decided', 'New units added under the right parent', 'Each unit has a checker account', 'Submitted registrations decided', 'Pending 201 documents verified', 'ETE list exported for the next 3 months']],
  ];
  ck.forEach(([who, items], i) => {
    const x = 0.6 + i * 4.1;
    card(s, x, 1.55, 3.9, 0.95 + items.length * 0.62, COL.card, 'Checklist ' + who);
    s.addText(who, { x: x + 0.25, y: 1.75, w: 3.4, h: 0.4, fontSize: 17, bold: true, color: COL.navy, margin: 0, isTextBox: true });
    items.forEach((t, j) => {
      const y = 2.35 + j * 0.62;
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.25, y: y + 0.04, w: 0.26, h: 0.26, rectRadius: 0.04, fill: { color: 'FFFFFF' }, line: { color: COL.cyanDark, width: 1.5 } });
      s.addText(t, { x: x + 0.65, y, w: 3.05, h: 0.5, fontSize: 13, color: COL.ink, valign: 'top', margin: 0, isTextBox: true });
    });
  });

  s = add('ROAP_COVER', '10. Completion');
  s.addImage({ data: logo, x: 0.8, y: 0.7, w: 0.75, h: 0.75, altText: 'Philippine Coast Guard seal' });
  kicker(s, 'Thank you', 1.85, COL.gold300, 0.8);
  s.addText([{ text: 'That is PCG HRMIS,', options: { breakLine: true } }, { text: 'end to end.', options: { color: COL.gold300 } }], { placeholder: 'title' });
  s.addText('Questions about your records: use Contact on the portal. When a screen changes, this deck is rebuilt from the live system (README.md: one capture run, one build).',
    { x: 0.8, y: 4.35, w: 6.3, h: 1.3, fontSize: 17, color: 'FBE4E3', valign: 'top', margin: 0, isTextBox: true });
  s.addShape(pres.shapes.RECTANGLE, { x: 7.55, y: 1.6, w: 5.3, h: 2.65 + 0.1, fill: { color: COL.navy }, line: { color: COL.frameGold, width: 2 }, shadow: { type: 'outer', color: '000000', opacity: 0.45, blur: 16, offset: 5, angle: 90 } });
  s.addImage({ path: bannerPath, x: 7.6, y: 1.65, w: 5.2, h: 2.6, altText: 'Official PCG banner of its three functional commands' });
  s.addNotes(SCRIPT.closing);

  await pres.writeFile({ fileName: DECK });
  if (SKILL_THEME) {
    const { applyTheme } = require(SKILL_THEME);
    await applyTheme(DECK, THEME);
  }
  fs.writeFileSync(path.join(OUT, 'slide_map.json'), JSON.stringify(slideOf, null, 1));
  fs.writeFileSync(path.join(OUT, 'deck_steps.json'), JSON.stringify(steps.concat(phone).map(x => ({ id: x.id, slide: slideOf[x.id], n: x.n, total: x.total, image: x.image, crop: x.crop || null, zoomedFile: x.zoomedFile || null, boxes: x.boxes })), null, 1));
  console.log('wrote', DECK, 'slides', slideNo);
})();
