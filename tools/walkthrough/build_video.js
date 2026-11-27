// Cut the walkthrough recordings into one film (MP4, 1920x1080, 30 fps) with a title
// card before every workflow, and write its chapters.
//
//   WALKTHROUGH_VIDEO=1 node tools/walkthrough/capture.js OUT_DIR
//   node tools/walkthrough/build_video.js OUT_DIR
//
// capture.js records every browser page and notes when each step ends (after its caption
// was on screen) and which long waits to drop. Each step's footage runs from the end of
// the previous step to the end of this one, taken from the page the step was on. Needs
// ffmpeg and ffprobe; the title cards use the DejaVu fonts.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const OUT = path.resolve(process.argv[2] || 'walkthrough-out');
const DIR = path.join(OUT, 'video');
const T = JSON.parse(fs.readFileSync(path.join(DIR, 'timeline.json'), 'utf8'));
const WORK = path.join(DIR, 'parts');
fs.rmSync(WORK, { recursive: true, force: true });
fs.mkdirSync(WORK, { recursive: true });

const W = 1920, H = 1080, FPS = 30, BG = '0x070b18';
const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
const BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
const ENC = ['-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22', '-pix_fmt', 'yuv420p', '-r', String(FPS), '-an'];

const ff = args => execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { stdio: 'inherit' });
const duration = f => parseFloat(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f]).toString());

// keep [from, to] minus the long waits
function minusPauses(from, to) {
  let parts = [[from, to]];
  for (const [a, b] of T.pauses) {
    parts = parts.flatMap(([x, y]) => (b <= x || a >= y) ? [[x, y]] : [[x, Math.min(a, y)], [Math.max(b, x), y]].filter(([p, q]) => q - p > 300));
  }
  return parts;
}

// 1. footage pieces, one or more per step
const pages = Object.fromEntries(T.pages.map(p => [p.idx, p]));
const marks = [...T.marks].sort((a, b) => a.t - b.t);
const items = [];   // { type: 'card'|'clip', ... }
let prevEnd = 0, prevGroup = '';
marks.forEach((m, i) => {
  const pg = pages[m.page];
  if (!pg || !pg.path || !fs.existsSync(pg.path)) return;
  const group = `${m.section}|${m.workflow}`;
  if (group !== prevGroup) {
    items.push({ type: 'card', section: m.section, workflow: m.workflow, role: m.role });
    prevGroup = group;
  }
  const from = Math.max(prevEnd, pg.start);
  for (const [a, b] of minusPauses(from, m.t)) {
    const last = items[items.length - 1];
    if (last.type === 'clip' && last.page === m.page && Math.abs(last.to - a) < 50) last.to = b;
    else items.push({ type: 'clip', page: m.page, from: a, to: b, step: m.id });
  }
  prevEnd = m.t;
});

// 2. render
function card(file, title, sub, note, seconds = 3.2) {
  const t1 = path.join(WORK, path.basename(file) + '.t1'), t2 = t1.replace('.t1', '.t2'), t3 = t1.replace('.t1', '.t3');
  fs.writeFileSync(t1, title); fs.writeFileSync(t2, sub); fs.writeFileSync(t3, note);
  const vf = [
    `drawbox=x=0:y=ih-14:w=iw*0.46:h=14:color=0x0038a8:t=fill`,
    `drawbox=x=iw*0.46:y=ih-14:w=iw*0.46:h=14:color=0xce1126:t=fill`,
    `drawbox=x=iw*0.92:y=ih-14:w=iw*0.08:h=14:color=0xfcd116:t=fill`,
    `drawtext=fontfile=${FONT}:textfile=${t3}:fontcolor=0xfcd116:fontsize=30:x=(w-text_w)/2:y=h/2-150`,
    `drawtext=fontfile=${BOLD}:textfile=${t1}:fontcolor=white:fontsize=72:x=(w-text_w)/2:y=h/2-80`,
    `drawtext=fontfile=${FONT}:textfile=${t2}:fontcolor=0xb3bdd0:fontsize=34:x=(w-text_w)/2:y=h/2+30`,
    `fade=t=in:st=0:d=0.4`, `fade=t=out:st=${seconds - 0.4}:d=0.4`,
  ].join(',');
  ff(['-f', 'lavfi', '-i', `color=c=${BG}:s=${W}x${H}:d=${seconds}:r=${FPS}`, '-vf', vf, ...ENC, file]);
}

const files = [];
const chapters = [];
let clock = 0, n = 0;
const add = (file, label) => { const d = duration(file); if (label) chapters.push({ start: clock, title: label }); files.push(file); clock += d; };

const open = path.join(WORK, `p${String(n++).padStart(3, '0')}.mp4`);
card(open, 'PCG HRMIS end-to-end walkthrough', 'From the landing page and registration to review, approval and re-enlistment', 'MEPCOM HRMIS · development copy with sample data', 4.5);
add(open, 'Introduction');

for (const it of items) {
  const file = path.join(WORK, `p${String(n++).padStart(3, '0')}.mp4`);
  if (it.type === 'card') {
    const label = `${it.workflow}`;
    card(file, label, `Signed in as: ${it.role || 'visitor'}`, it.section === 'M' ? 'On a phone' : `Part ${it.section}`);
    add(file, label);
  } else {
    const pg = pages[it.page];
    const from = Math.max(0, (it.from - pg.start) / 1000), to = (it.to - pg.start) / 1000;
    if (to - from < 0.3) continue;
    ff(['-ss', from.toFixed(3), '-to', to.toFixed(3), '-i', pg.path,
      '-vf', `fps=${FPS},scale=${W}:${H}:force_original_aspect_ratio=decrease,pad=${W}:${H}:(ow-iw)/2:(oh-ih)/2:color=${BG},setsar=1`, ...ENC, file]);
    add(file, null);
  }
}

const close = path.join(WORK, `p${String(n++).padStart(3, '0')}.mp4`);
card(close, 'End of the walkthrough', 'Every step above was performed live in the HRMIS, nothing was staged', 'MEPCOM HRMIS', 4);
add(close, 'End');

// 3. join, with chapters in the file
const list = path.join(WORK, 'list.txt');
fs.writeFileSync(list, files.map(f => `file '${f}'`).join('\n'));
const meta = path.join(WORK, 'chapters.ffmeta');
fs.writeFileSync(meta, ';FFMETADATA1\ntitle=PCG HRMIS end-to-end walkthrough\n' + chapters.map((c, i) => {
  const end = i + 1 < chapters.length ? chapters[i + 1].start : clock;
  return `[CHAPTER]\nTIMEBASE=1/1000\nSTART=${Math.round(c.start * 1000)}\nEND=${Math.round(end * 1000)}\ntitle=${c.title.replace(/[=;#\\\n]/g, ' ')}`;
}).join('\n') + '\n');
const final = path.join(OUT, 'PCG_HRMIS_End_to_End_Walkthrough.mp4');
ff(['-f', 'concat', '-safe', '0', '-i', list, '-i', meta, '-map_metadata', '1', '-map_chapters', '1', '-c', 'copy', '-movflags', '+faststart', final]);

const mmss = s => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
const txt = chapters.map(c => `${mmss(c.start)}  ${c.title}`).join('\n') + `\n\nLength ${mmss(clock)}\n`;
fs.writeFileSync(path.join(OUT, 'PCG_HRMIS_Walkthrough_chapters.txt'), txt);
console.log(final + '\n' + txt);
