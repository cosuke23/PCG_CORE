// Walkthrough capture: drives the real app (dev database, seeded demo accounts)
// through every documented workflow, saves a screenshot per step and records the
// on-screen box of each control the slide points at (steps.json). Nothing here
// bypasses sign-in: admins pass the real second step with the dev-only
// authenticator key from the seeder. Never point this at the real pcg_core.
//
//   node tools/walkthrough/capture.js OUT_DIR
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const lib = require('../../tests/e2e/lib');

const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const OUT = path.resolve(process.argv[2] || 'walkthrough-out');
const ORIG = path.join(OUT, 'ROAP_Screenshot_Evidence', 'original');
fs.mkdirSync(ORIG, { recursive: true });
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || 'mariadb -uroot'} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const PASS = 'Demo@12345';
const DESK = { width: 1920, height: 1080 };
const PHONE = { width: 390, height: 844 };
const PDF = path.join(OUT, 'sample.pdf');
fs.writeFileSync(PDF, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

const STEPS = [];
const WARN = [];
let seq = 0;

async function settle(page, ms = 500) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(ms);
  // no screenshot of a loading state: wait for skeleton placeholders to go
  await page.waitForFunction(() => ![...document.querySelectorAll('.pcg-skeleton')].some(e => e.offsetParent), null, { timeout: 6000 }).catch(() => {});
}

// targets: [{ sel | loc, kind: click|field|result|caution|error, label }]
async function cap(page, m) {
  seq += 1;
  const id = m.id;
  await settle(page, m.wait ?? 400);
  const boxes = [];
  let first = true;
  for (const t of m.targets || []) {
    const loc = t.loc || page.locator(t.sel + ' >> visible=true').first();
    try {
      if (first && !m.noScroll) await loc.scrollIntoViewIfNeeded({ timeout: 3000 });
      first = false;
      const b = await loc.boundingBox({ timeout: 3000 });
      if (!b || b.width < 2) throw new Error('not visible');
      boxes.push({ x: b.x, y: b.y, w: b.width, h: b.height, kind: t.kind || 'click', label: t.label || '' });
    } catch (e) {
      WARN.push(`${id}: target ${t.sel || t.label} not found (${e.message.split('\n')[0]})`);
    }
  }
  if (m.after) await m.after();
  await page.waitForTimeout(150);
  const vp = page.viewportSize();
  let clip = null;
  if (m.zoom && boxes.length) {
    // a real crop around the targets (for dense screens), 16:9, inside the viewport
    const pad = m.zoomPad || 140;
    let x0 = Math.min(...boxes.map(b => b.x)) - pad, y0 = Math.min(...boxes.map(b => b.y)) - pad;
    let x1 = Math.max(...boxes.map(b => b.x + b.w)) + pad, y1 = Math.max(...boxes.map(b => b.y + b.h)) + pad;
    let w = Math.max(x1 - x0, 960), h = Math.max(y1 - y0, 540);
    if (w / h > 16 / 9) h = w * 9 / 16; else w = h * 16 / 9;
    w = Math.min(w, vp.width); h = Math.min(h, vp.height);
    const cx = (x0 + x1) / 2, cy = (y0 + y1) / 2;
    x0 = Math.max(0, Math.min(cx - w / 2, vp.width - w)); y0 = Math.max(0, Math.min(cy - h / 2, vp.height - h));
    clip = { x: Math.round(x0), y: Math.round(y0), width: Math.round(w), height: Math.round(h) };
  }
  const file = `${id}.png`;
  await page.screenshot({ path: path.join(ORIG, file), clip: clip || undefined });
  const health = await page.evaluate(() => {
    const t = document.body ? document.body.innerText : '';
    return {
      error: /Server Error|Whoops|Page Expired|419 |Too Many Requests|Forbidden|Not Found/i.test(t.slice(0, 3000)),
      loading: !!document.querySelector('.pcg-skeleton:not([hidden]), [aria-busy="true"]'),
      labels: [...document.querySelectorAll('label, button, h1, h2, h3, a.pcg-btn')].filter(e => e.offsetParent).map(e => e.innerText.trim().replace(/\s+/g, ' ')).filter(Boolean).slice(0, 80),
    };
  });
  STEPS.push({
    id, seq, section: m.section, workflow: m.workflow, role: m.role, title: m.title,
    action: m.action, expected: m.expected, kind: m.kind || (boxes[0] && boxes[0].kind) || 'result',
    warning: m.warning || '', verified: m.verified || 'Verified in the browser', note: m.note || '',
    file, url: page.url().replace(BASE, '') || '/', viewport: `${vp.width}x${vp.height}`,
    image: clip ? { w: clip.width, h: clip.height } : { w: vp.width, h: vp.height },
    zoom: !!clip, device: vp.width < 600 ? 'mobile' : 'desktop',
    boxes: boxes.map(b => clip ? { ...b, x: b.x - clip.x, y: b.y - clip.y } : b),
    time: new Date().toISOString(), health: { error: health.error, loading: health.loading }, visibleLabels: health.labels,
  });
  if (health.error) WARN.push(`${id}: page shows an error text`);
  console.log(`${id}  ${boxes.length}/${(m.targets || []).length} targets  ${page.url().replace(BASE, '')}`);
}

async function newPage(browser, viewport = DESK, opts = {}) {
  const ctx = await browser.newContext({ viewport, acceptDownloads: true, ...opts });
  const p = await ctx.newPage();
  p.jsErrors = [];
  p.on('pageerror', e => p.jsErrors.push(e.message.split('\n')[0]));
  return p;
}

async function signIn(p, url, email, pass) {
  await p.goto(BASE + url);
  await p.fill('#email', email); await p.fill('#password', pass);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}

const set = (p, sel, val) => p.evaluate(([s, v]) => {
  const el = document.querySelector(s); el.value = v;
  el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
}, [sel, val]);
const pickFirst = (p, sel) => p.evaluate(s => {
  const el = document.querySelector(s); const opt = [...el.options].find(o => o.value);
  if (!opt) return null; el.value = opt.value;
  el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); return opt.value;
}, sel);
const freshStep = () => new Promise(r => setTimeout(r, 31000 - (Date.now() % 30000)));

(async () => {
  // full Chromium (new headless): unlike the headless shell it has the PDF viewer the review dialog uses
  const browser = await chromium.launch({ channel: 'chromium', args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const S = (section, workflow, role) => ({ section, workflow, role });
  try {
    // ================= SECTION 2: landing page (guest) =================
    const g = await newPage(browser);
    let s = S('2', 'Landing page', 'Public visitor');
    await g.goto(BASE + '/mepcom', { waitUntil: 'networkidle' }); await g.waitForTimeout(2500);
    await cap(g, { ...s, id: 'S02-01', title: 'Open the portal', action: 'Open the portal address in a web browser.',
      expected: 'The MEPCOM HRMIS home page opens with the navigation bar at the top.', targets: [{ sel: 'header', kind: 'result', label: 'Navigation bar' }], noScroll: true });
    await cap(g, { ...s, id: 'S02-02', title: 'Know the navigation bar', action: 'Use Home, About, Services, Units, MEPTDI and Contact to move around the public site.',
      expected: 'Each link scrolls to, or opens, that part of the site.', zoom: true, zoomPad: 60, noScroll: true,
      targets: [{ sel: 'header nav', kind: 'field', label: 'Public links' }, { sel: 'header a:has-text("Sign in")', kind: 'click', label: 'Sign in' }] });
    await g.locator('header a:has-text("Services")').first().click(); await g.waitForTimeout(1500);
    await cap(g, { ...s, id: 'S02-03', title: 'See the public services', action: 'Select Services in the navigation bar.',
      expected: 'The "Find the right service, sooner." section lists sign-in, registration, unit checkers, MEPTDI and certificate check.',
      targets: [{ sel: 'h2:has-text("Find the right service")', kind: 'result', label: 'Services' }], noScroll: true });
    const regCard = g.locator('a:has-text("Non-officer")').first();
    await cap(g, { ...s, id: 'S02-04', title: 'Begin registration or sign in', action: 'Under "New personnel registration", select Officer or Non-officer. Members with an account select "Sign in to the portal".',
      expected: 'The registration form for that type opens (or the sign-in page).',
      targets: [{ loc: regCard, kind: 'click', label: 'Non-officer' }, { sel: 'a:has-text("Officer") >> nth=0', kind: 'click', label: 'Officer' }, { sel: 'a:has-text("Sign in to the portal")', kind: 'click', label: 'Sign in to the portal' }] });
    await g.goto(BASE + '/login');
    await cap(g, { ...s, id: 'S02-05', title: 'The personnel sign-in page', action: 'Members sign in with their email address and password. New personnel choose Officer or Non-Officer under "No account yet? Register as:".',
      expected: 'The registration form opens for the type you chose.',
      targets: [{ sel: 'a:has-text("Non-Officer")', kind: 'click', label: 'Non-Officer' }, { sel: 'a:has-text("Officer Commissioned")', kind: 'click', label: 'Officer' }] });

    // ================= SECTION 3: registration (new non-officer, fake camera) =================
    s = S('3', 'Applicant registration', 'Applicant');
    const n = await newPage(browser, DESK, { permissions: ['camera'] });
    await n.goto(BASE + '/register?type=non_officer', { waitUntil: 'networkidle' });
    await cap(n, { ...s, id: 'S03-01', title: 'Open the registration page', action: 'Open "Register as a non-officer" (or officer). The form has four steps: Basic, Service, Unit, Verify.',
      expected: 'Step 1 "Who you are" is shown.', targets: [{ sel: 'h2:has-text("Register as")', kind: 'result', label: 'Form' }, { sel: 'button:has-text("1 Basic")', kind: 'field', label: 'Steps' }] });
    if (await n.locator('select[name=rank]').count()) await pickFirst(n, 'select[name=rank]'); else await set(n, '[name=rank]', 'SN1');
    await set(n, '[name=serial_number]', 'DEMO-0101');
    await set(n, '[name=name]', 'Sample Registrant'); await set(n, '[name=email]', 'sample.registrant@demo.test');
    await cap(n, { ...s, id: 'S03-02', title: 'Enter who you are', action: 'Choose your Rank, then type your Serial number, Full name and Email address. Select Next.',
      expected: 'Step 2 "Service" opens.', zoom: true,
      targets: [{ sel: '[name=rank]', kind: 'field', label: 'Rank' }, { sel: '[name=serial_number]', kind: 'field', label: 'Serial number' }, { sel: '[name=name]', kind: 'field', label: 'Full name' }, { sel: '[name=email]', kind: 'field', label: 'Email address' }, { sel: 'button[\\@click="next()"]', kind: 'click', label: 'Next' }] });
    const next = () => n.locator('button[\\@click="next()"]').click().then(() => n.waitForTimeout(500));
    await next();
    if (await n.locator('#dob').isVisible()) { await set(n, '#dob', '1998-04-04'); await set(n, '#date_of_appointment', '2022-01-10'); }
    await cap(n, { ...s, id: 'S03-03', title: 'Enter your service dates', action: 'Fill in the service fields shown (birth date and date of appointment). Select Next.',
      expected: 'Step 3 "Unit" opens.', zoom: true,
      targets: [{ sel: '#dob', kind: 'field', label: 'Birth date' }, { sel: '#date_of_appointment', kind: 'field', label: 'Date of appointment' }, { sel: 'button[\\@click="next()"]', kind: 'click', label: 'Next' }] });
    await next();
    for (const f of ['command', 'category', 'unit', 'sub_unit']) { if (await n.locator(`select[name=${f}]`).isVisible()) { await pickFirst(n, `select[name=${f}]`); await n.waitForTimeout(250); } }
    await cap(n, { ...s, id: 'S03-04', title: 'Choose your unit', action: 'Choose your Command, then the category, unit and sub-unit lists that follow. Select Next.',
      expected: 'Step 4 "Verify" opens.', zoom: true,
      targets: [{ sel: 'select[name=command]', kind: 'field', label: 'Command' }, { sel: 'select[name=unit]', kind: 'field', label: 'Unit' }, { sel: 'button[\\@click="next()"]', kind: 'click', label: 'Next' }] });
    await next();
    if (await n.locator('[name=password]').isVisible()) { await set(n, '[name=password]', 'SamplePass123!'); await set(n, '[name=password_confirmation]', 'SamplePass123!'); }
    await n.waitForTimeout(1500);
    await cap(n, { ...s, id: 'S03-05', title: 'Set a password and take your photo', action: 'Type and confirm a password, allow the camera, then select "Capture selfie" to take your verification photo.',
      expected: 'Your photo appears; "Submit registration" can be used.', kind: 'caution', warning: 'A working camera is required: the photo is the identity check the HR admin compares.',
      targets: [{ sel: '[name=password]', kind: 'field', label: 'Password' }, { sel: '#captureBtn', kind: 'caution', label: 'Capture' }] });
    await n.click('#captureBtn'); await n.waitForTimeout(600);
    await n.click('.swal2-confirm', { timeout: 1500 }).catch(() => {}); await n.waitForTimeout(300);
    await cap(n, { ...s, id: 'S03-06', title: 'Submit the registration', action: 'Check your photo ("Capture again" retakes it), then select "Submit registration".',
      expected: 'You return to the sign-in page with "Your account is pending admin approval."',
      targets: [{ sel: '#registerForm button:has-text("Submit")', kind: 'click', label: 'Submit' }] });
    await Promise.all([n.waitForNavigation({ timeout: 15000 }), n.locator('#registerForm button:has-text("Submit")').click()]);
    await cap(n, { ...s, id: 'S03-07', title: 'Registration received', action: 'Read the message at the top of the sign-in page.',
      expected: 'The account exists with status Pending. You cannot sign in until an HR administrator approves it.', kind: 'result',
      targets: [{ sel: '.pcg-alert, [role=status], .pcg-flash', kind: 'result', label: 'Pending message' }] });
    await n.fill('#email', 'sample.registrant@demo.test'); await n.fill('#password', 'SamplePass123!');
    await Promise.all([n.waitForNavigation(), n.click('button[type=submit]')]);
    await cap(n, { ...s, id: 'S03-08', title: 'Signing in before approval', action: 'If you try to sign in while the account is pending, the page tells you so.',
      expected: '"Your account is waiting for admin approval." Wait for the HR administrator (Section 7, Self-registrations).', kind: 'error',
      targets: [{ sel: '.pcg-alert, .pcg-error, [role=alert], .pcg-field__error', kind: 'error', label: 'Pending' }] });

    // ================= SECTION 5: administrator access =================
    s = S('5', 'Administrator access', 'Administrator (super admin)');
    const a = await newPage(browser);
    await a.goto(BASE + '/admin/login');
    await a.fill('#email', 'admin@demo.test'); await a.fill('#password', lib.ADMIN_PASSWORD);
    await cap(a, { ...s, id: 'S05-01', title: 'Sign in as an administrator', action: 'Open /admin/login, type your Email address and Password, then select Sign in.',
      expected: 'The second sign-in step asks for a code.', note: 'The password is masked; the account used is a dev sample account.',
      targets: [{ sel: '#email', kind: 'field', label: 'Email address' }, { sel: '#password', kind: 'field', label: 'Password' }, { sel: 'button[type=submit]', kind: 'click', label: 'Sign in' }] });
    await freshStep();
    await Promise.all([a.waitForNavigation(), a.click('button[type=submit]')]);
    await cap(a, { ...s, id: 'S05-02', title: 'Enter the second-step code', action: 'Type the six-digit code (sent by email, or from your authenticator app if your account uses one) and select Verify.',
      expected: 'The administration console opens on the page for your role.', kind: 'caution', warning: 'Every admin role except the re-enlistment admin must pass this step. Codes expire; request a new one if needed.',
      targets: [{ sel: '#code', kind: 'field', label: 'Code' }, { sel: 'button[type=submit]', kind: 'click', label: 'Confirm' }] });
    await a.fill('#code', lib.totp(lib.ADMIN_TOTP_SECRET));
    await Promise.all([a.waitForNavigation(), a.click('button[type=submit]')]);
    await settle(a, 1200);
    await cap(a, { ...s, id: 'S05-03', title: 'The administration console', action: 'Use the sidebar to open a module. The sections you see depend on your role.',
      expected: 'The super admin lands on Self-registrations (MEPCOM registrations).', noScroll: true,
      targets: [{ sel: '#sidebar', kind: 'field', label: 'Sidebar' }, { sel: '.pcg-console__content h2, .pcg-console__content h1', kind: 'result', label: 'Page' }] });
    await cap(a, { ...s, id: 'S05-04', title: 'Find a page quickly', action: 'Type in "Go to a page..." (or press /) to jump to any page in the sidebar. The account menu on the right holds Sign out.',
      expected: 'Matching pages are listed as you type.', zoom: true, zoomPad: 40, noScroll: true,
      targets: [{ sel: 'input[placeholder^="Go to a page"], .pcg-navbar input[type=search]', kind: 'click', label: 'Go to a page' }, { sel: 'button:has-text("Account menu"), [aria-label*="Account menu"]', kind: 'click', label: 'Account menu' }] });
    await a.goto(BASE + '/admin/hrmis'); await settle(a, 1200);
    await cap(a, { ...s, id: 'S05-05', title: 'Personnel database overview', action: 'Open HRMIS > Overview. Select a tile, bar or rank to filter the people list below.',
      expected: 'The counts and the list change together; "Export ... to CSV" exports the visible people.', noScroll: true,
      targets: [{ sel: 'a:has-text("Overview")', kind: 'click', label: 'Overview' }, { sel: 'button:has-text("Export")', kind: 'click', label: 'Export' }] });
    await a.goto(BASE + '/admin/personnel');
    await cap(a, { ...s, id: 'S05-06', title: 'Search the personnel master', action: 'Open HRMIS > Personnel. Use Name or serial number, Unit, Classification and Status, then select Filter.',
      expected: 'The table lists matching records; select a name to open the record.',
      targets: [{ sel: 'a.pcg-nav__link:has-text("Personnel"), #sidebar a:has-text("Personnel")', kind: 'click', label: 'Personnel' }, { sel: 'form[role=search], .pcg-filters', kind: 'field', label: 'Filters' }, { sel: 'button:has-text("Filter")', kind: 'click', label: 'Filter' }], noScroll: true });

    // ================= SECTION 7: self-registration approval =================
    s = S('7', 'Approve a self-registration', 'HR administrator / super admin');
    await a.goto(BASE + '/admin/dashboard'); await settle(a, 1200);
    await cap(a, { ...s, id: 'S07-01', title: 'Open the pending registrations', action: 'Open MEPCOM registrations > Self-registrations, then select "Review ... pending".',
      expected: 'The table below shows only the Pending registrations.', noScroll: true,
      targets: [{ sel: '#sidebar a:has-text("Self-registrations")', kind: 'click', label: 'Self-registrations' }, { sel: 'button.ad-head__cta', kind: 'click', label: 'Review pending' }] });
    await a.click('button.ad-head__cta'); await a.waitForTimeout(800);
    const uid = sql("select id from users where email='sample.registrant@demo.test'");
    const revBtn = a.locator(`[data-ad-review="${uid}"]`).last();
    await cap(a, { ...s, id: 'S07-02', title: 'Review the new registration', action: 'Find the person in the table and select Review (or double-click the row).',
      expected: 'A window shows the photo, serial, rank, unit and email with Approve, Reject and Cancel.',
      targets: [{ loc: revBtn, kind: 'click', label: 'Review' }] });
    await revBtn.click(); await a.waitForSelector('.swal2-popup'); await a.waitForTimeout(600);
    await cap(a, { ...s, id: 'S07-03', title: 'Approve or reject', action: 'Compare the photo and details with the person\'s records. Select Approve (or Reject).',
      expected: '"Approved! The member can now sign in."', noScroll: true,
      targets: [{ sel: '.swal2-confirm', kind: 'click', label: 'Approve' }, { sel: '.swal2-deny', kind: 'caution', label: 'Reject' }] });
    await a.click('.swal2-confirm'); await a.waitForTimeout(1200);
    await cap(a, { ...s, id: 'S07-04', title: 'Registration approved', action: 'Close the confirmation.',
      expected: 'The account status is Approved; the person can sign in at /login.', kind: 'result', noScroll: true,
      targets: [{ sel: '.swal2-popup', kind: 'result', label: 'Approved' }] });
    await a.click('.swal2-confirm').catch(() => {});
    console.log('status after approve:', sql(`select status from users where id=${uid}`));

    // the new member signs in
    s = S('3', 'Applicant registration', 'Applicant');
    await n.goto(BASE + '/login');
    await n.fill('#email', 'sample.registrant@demo.test'); await n.fill('#password', 'SamplePass123!');
    await cap(n, { ...s, id: 'S03-09', title: 'Sign in after approval', action: 'Once approved, type your Email address and Password and select Sign in.',
      expected: 'Your dashboard opens.', targets: [{ sel: '#email', kind: 'field', label: 'Email' }, { sel: '#password', kind: 'field', label: 'Password' }, { sel: 'button[type=submit]:has-text("Sign in")', kind: 'click', label: 'Sign in' }] });
    await Promise.all([n.waitForNavigation(), n.click('button[type=submit]:has-text("Sign in")')]);
    await cap(n, { ...s, id: 'S03-10', title: 'Your dashboard', action: 'Start from "What would you like to do?": update the SOI, upload documents and 201 files, training, print the SOI.',
      expected: 'Each card opens that task.', kind: 'result', noScroll: true,
      targets: [{ sel: '#tasks-heading', kind: 'result', label: 'Tasks' }] });

    // ================= SECTION 7: unit checker review (returns a document) =================
    s = S('7', 'Review required documents (unit checker)', 'Unit checker');
    const u = await newPage(browser);
    await u.goto(BASE + '/unit/login');
    await u.fill('#email', 'unit@demo.test'); await u.fill('#password', PASS);
    await cap(u, { ...s, id: 'S07-05', title: 'Sign in as a unit checker', action: 'Open /unit/login (Unit sign in on the home page), type your Email address and Password, select Sign in.',
      expected: 'The Document review queue for your unit opens.', targets: [{ sel: '#email', kind: 'field' }, { sel: '#password', kind: 'field' }, { sel: 'button[type=submit]', kind: 'click', label: 'Sign in' }] });
    await Promise.all([u.waitForNavigation(), u.click('button[type=submit]')]);
    const juan = sql("select serial_number from users where email='member@demo.test'");
    const juanRow = u.locator('.uc-table tr', { hasText: juan }).first();
    await cap(u, { ...s, id: 'S07-06', title: 'Pick a record to review', action: 'The headline says how many records wait. Use the Waiting / Returned / Approved / All filters or search, then select Review.',
      expected: 'The review window opens with every document.', noScroll: true,
      targets: [{ sel: '.uc-filter', kind: 'field', label: 'Filters' }, { loc: juanRow.getByRole('button', { name: /Review/ }), kind: 'click', label: 'Review' }] });
    await juanRow.getByRole('button', { name: /Review/ }).click(); await u.waitForSelector('#uc-dialog[open] #uc-items .uc-item'); await u.waitForTimeout(600);
    await u.locator('#uc-items .uc-item', { hasText: '2×2' }).first().click().catch(() => WARN.push('2x2 item'));
    await u.waitForTimeout(800);
    await cap(u, { ...s, id: 'S07-07', title: 'Check each document', action: 'Select a document in the list on the left to view it on the right.',
      expected: 'The file opens in the viewer; "Open in a new tab" shows it full size.', noScroll: true,
      targets: [{ sel: '#uc-items', kind: 'field', label: 'Documents' }, { sel: '#uc-v-frame', kind: 'result', label: 'Viewer' }] });
    await u.locator('#uc-items .uc-item', { hasText: 'NBI' }).first().click().catch(() => {});
    await u.check('#uc-ret-nbi_clearance'); await u.waitForTimeout(200);
    await u.click('#uc-return-btn'); await u.waitForTimeout(300);
    await cap(u, { ...s, id: 'S07-08', title: 'A return needs a comment', action: 'Tick Return on an item that must be uploaded again. Returning without a comment is refused.',
      expected: 'The comment box is marked; the dialog stays open until you write what to fix.', kind: 'error',
      targets: [{ sel: '#uc-com-nbi_clearance', kind: 'error', label: 'Comment' }, { sel: '#uc-return-btn', kind: 'caution', label: 'Return 1 item' }] });
    await u.fill('#uc-com-nbi_clearance', 'The NBI clearance is blurred. Upload a clear scan.');
    await cap(u, { ...s, id: 'S07-09', title: 'Return the item', action: 'Type what the member must fix, then select "Return 1 item".',
      expected: 'The record moves to Returned; the member sees your comment on the dashboard.',
      targets: [{ sel: '#uc-com-nbi_clearance', kind: 'field', label: 'Comment' }, { sel: '#uc-return-btn', kind: 'click', label: 'Return 1 item' }] });
    await Promise.all([u.waitForNavigation(), u.click('#uc-return-btn')]);
    await u.click('[data-uc-filter="all"]').catch(() => {});
    await cap(u, { ...s, id: 'S07-10', title: 'Record returned', action: 'Check the status column.',
      expected: 'The record shows Returned; the queue headline updates.', kind: 'result', noScroll: true,
      targets: [{ loc: u.locator('.uc-table tr', { hasText: juan }).first(), kind: 'result', label: 'Returned' }] });
    // approve another record
    const other = sql("select pd.serial_number from personnel_data pd join users u on u.serial_number=pd.serial_number where u.mother_unit='MEPLEF' and pd.serial_number<>'" + juan + "' order by pd.id limit 1");
    if (other) {
      await u.locator('.uc-table tr', { hasText: other }).first().getByRole('button', { name: /Review/ }).click();
      await u.waitForSelector('#uc-dialog[open] #uc-items .uc-item'); await u.waitForTimeout(500);
      await cap(u, { ...s, id: 'S07-11', title: 'Approve a complete record', action: 'When every document is correct, select "Approve record".',
        expected: 'The record moves to Approved and its documents lock for the member.', noScroll: true,
        targets: [{ sel: '#uc-approve-btn', kind: 'click', label: 'Approve record' }] });
      await Promise.all([u.waitForNavigation(), u.click('#uc-approve-btn')]);
    }
    // 201 review (approve Juan's 201 so a section opens for the member)
    s = S('7', 'Review 201 files (unit checker)', 'Unit checker');
    await u.goto(BASE + '/unit/201-files');
    await cap(u, { ...s, id: 'S07-12', title: 'Review 201 files', action: 'Select "201 file review" in the sidebar. It works like Document review: Review, check each section, then approve or return.',
      expected: 'The 201 queue for your unit is listed.', noScroll: true,
      targets: [{ sel: '#sidebar a:has-text("201 file review")', kind: 'click', label: '201 file review' }, { loc: u.locator('.uc-table tr', { hasText: juan }).first().getByRole('button', { name: /Review/ }), kind: 'click', label: 'Review' }] });
    await u.locator('.uc-table tr', { hasText: juan }).first().getByRole('button', { name: /Review/ }).click();
    await u.waitForSelector('#uc-dialog[open]'); await u.waitForTimeout(400);
    await Promise.all([u.waitForNavigation(), u.click('#uc-approve-btn')]);

    // ================= SECTION 4: member application workflow =================
    s = S('4', 'Applicant application workflow', 'Applicant');
    const m = await newPage(browser);
    await signIn(m, '/login', 'member@demo.test', PASS);
    await settle(m, 800);
    await cap(m, { ...s, id: 'S04-01', title: 'Open your dashboard', action: 'Sign in. The dashboard lists your tasks and any documents the reviewer returned.',
      expected: 'Returned items show the reviewer\'s comment.', kind: 'caution', noScroll: true,
      targets: [{ sel: '#rejected-heading', kind: 'caution', label: 'Returned documents' }, { sel: 'a:has-text("Update my SOI profile")', kind: 'click', label: 'Update my SOI profile' }] });
    await m.click('a:has-text("Update my SOI profile")'); await settle(m, 800);
    await cap(m, { ...s, id: 'S04-02', title: 'Open the SOI', action: 'The Statement of Information (SOI) opens as a summary. Select Edit SOI.',
      expected: 'The six-step SOI form opens.', targets: [{ sel: 'a:has-text("Edit SOI")', kind: 'click', label: 'Edit SOI' }] });
    await m.click('a:has-text("Edit SOI")'); await settle(m, 1000);
    await cap(m, { ...s, id: 'S04-03', title: 'Step 1: basic information', action: 'Check your name, rank, class, birth date and place. Correct anything wrong.',
      expected: 'Fields keep what you type until you save the step.', noScroll: true,
      targets: [{ sel: '.soi-stepper, [aria-label*="steps"], nav:has(button:has-text("1 Basic information"))', kind: 'field', label: 'Steps' }, { sel: '[x-model="basic.first_name"]', kind: 'field', label: 'First name' }] });
    await cap(m, { ...s, id: 'S04-04', title: 'Save the step', action: 'Select Next step. The step is saved before the next one opens.',
      expected: 'Step 2 "Personal information" opens. You can open any step again from the list.', noScroll: true,
      targets: [{ sel: 'button[x-show="step === 1"]', kind: 'click', label: 'Next step' }] });
    await m.locator('button[x-show="step === 1"]').click(); await m.waitForTimeout(1500);
    await cap(m, { ...s, id: 'S04-05', title: 'Steps 2 to 5', action: 'Complete Personal information, Dependents, Education and training, Appointments and duties. Use Add to add rows; select Next step after each.',
      expected: 'Each saved step is marked done in the step list.', noScroll: true,
      targets: [{ sel: 'button:has-text("2 Personal information")', kind: 'result', label: 'Step 2' }, { sel: 'button[x-show="step === 2"]', kind: 'click', label: 'Next step' }] });
    for (let k = 2; k <= 5; k++) {
      await m.locator(`button[x-show="step === ${k}"]`).click(); await m.waitForTimeout(1400);
      await m.click('.swal2-confirm', { timeout: 400 }).catch(() => {});
    }
    await cap(m, { ...s, id: 'S04-06', title: 'Step 6: awards and career, then submit', action: 'Complete Awards and career. Select "Save and submit SOI".',
      expected: 'A prompt asks whether the data you entered is correct.', kind: 'caution', warning: 'After you submit, the whole SOI becomes read-only. Only your Adjutant or HR Unit Admin can change or reopen it.', noScroll: true,
      targets: [{ sel: 'button[x-show="step === 6"]:has-text("Save and submit")', kind: 'click', label: 'Save and submit SOI' }] });
    await m.locator('button[x-show="step === 6"]:has-text("Save and submit")').click();
    await m.waitForSelector('.swal2-popup'); await m.waitForTimeout(500);
    await cap(m, { ...s, id: 'S04-07', title: 'Confirm the submission', action: 'Read the prompt. Select "Yes, submit my SOI" if everything is correct, or "No, return to my SOI" to keep editing.',
      expected: 'Yes saves step 6 and submits the SOI.', kind: 'caution', noScroll: true,
      targets: [{ sel: '.swal2-confirm', kind: 'click', label: 'Yes, submit my SOI' }, { sel: '.swal2-cancel, .swal2-deny', kind: 'caution', label: 'No, return' }] });
    await m.click('.swal2-confirm'); await m.waitForTimeout(2500);
    await m.click('.swal2-confirm', { timeout: 1500 }).catch(() => {});
    await settle(m, 1200);
    await m.locator('[x-model="basic.first_name"]').click().catch(() => {}); await m.keyboard.type('x').catch(() => {}); await m.waitForTimeout(500);
    await cap(m, { ...s, id: 'S04-08', title: 'A submitted SOI is read-only', action: 'Trying to type, choose or add anything shows the lock message.',
      expected: '"Unable to edit... contact your Adjutant or HR Unit Admin." The SOI is marked Submitted.', kind: 'error', noScroll: true,
      targets: [{ sel: '.swal2-popup, .soi-submitted-note, .pcg-toast', kind: 'error', label: 'Locked' }] });
    await m.click('.swal2-confirm', { timeout: 800 }).catch(() => {});

    // required documents: respond to the returned item
    s = S('4', 'Upload documents and respond to returns', 'Applicant');
    await m.goto(BASE + '/upload-documents'); await settle(m, 1000);
    await cap(m, { ...s, id: 'S04-09', title: 'Open Required documents', action: 'From the dashboard, open "Upload required documents". Accepted documents are locked; a returned item opens with the reviewer\'s comment.',
      expected: 'Only returned items, empty boxes and expired clearances can take a new file.', kind: 'caution',
      targets: [{ sel: 'article.ud-doc:has(#lbl-nbi_clearance)', kind: 'caution', label: 'Returned item' }] });
    await m.evaluate(() => { const d = document.querySelector('[name=nbi_clearance_expiration]'); if (d) { d.value = ''; d.dispatchEvent(new Event('change', { bubbles: true })); } });
    await m.setInputFiles('input[name=nbi_clearance]', PDF);
    await m.click('button:has-text("Save and upload")'); await m.waitForTimeout(800);
    await cap(m, { ...s, id: 'S04-10', title: 'A clearance needs its valid-until date', action: 'Uploading an NBI, Ombudsman or Sandiganbayan clearance without "Valid until" is refused.',
      expected: 'The date field is flagged. Enter today\'s date or later.', kind: 'error',
      targets: [{ sel: 'article.ud-doc:has(#lbl-nbi_clearance)', kind: 'error', label: 'Valid until' }] });
    await m.click('.swal2-confirm', { timeout: 600 }).catch(() => {});
    console.log('nbi after refused upload:', sql("select ifnull(nbi_clearance,'(empty)') from personnel_data where serial_number='DEMO-0001'"));
    const d = new Date(Date.now() + 200 * 864e5).toISOString().slice(0, 10);
    await m.fill('[name=nbi_clearance_expiration]', d);
    await cap(m, { ...s, id: 'S04-11', title: 'Upload the corrected file', action: 'Choose the new file, set Valid until, then select "Save and upload".',
      expected: 'The record goes back to the unit checker; every box locks while it waits.',
      targets: [{ sel: 'article.ud-doc:has(#lbl-nbi_clearance)', kind: 'field', label: 'File' }, { sel: '[name=nbi_clearance_expiration]', kind: 'field', label: 'Valid until' }, { sel: 'button:has-text("Save and upload")', kind: 'click', label: 'Save and upload' }] });
    await Promise.all([m.waitForNavigation({ timeout: 15000 }), m.click('button:has-text("Save and upload")')]).catch(() => {});
    await settle(m, 1000);
    await cap(m, { ...s, id: 'S04-12', title: 'Waiting for the unit checker', action: 'Read the status at the top of the page.',
      expected: 'All boxes and "Save and upload" are locked until the checker decides.', kind: 'result', noScroll: true,
      targets: [{ sel: '.pcg-alert, [role=status]', kind: 'result', label: 'Status' }, { sel: 'button:has-text("Save and upload")', kind: 'caution', label: 'Locked' }] });
    // 201 files
    await m.goto(BASE + '/upload-201'); await settle(m, 800);
    await m.setInputFiles('input[name=appointment]', PDF).catch(e => WARN.push('201 appointment: ' + e.message.split('\n')[0]));
    await cap(m, { ...s, id: 'S04-13', title: 'Upload 201 files', action: 'Open "Upload 201 files". Choose a file for an open section (here B. Appointment), then select "Upload files".',
      expected: 'The 201 record goes to the unit checker; sections lock while it waits.',
      targets: [{ sel: '*:has(> input[name=appointment]), label[for=appointment]', kind: 'field', label: 'B. Appointment' }, { sel: 'button:has-text("Upload files")', kind: 'click', label: 'Upload files' }] });
    await Promise.all([m.waitForNavigation({ timeout: 15000 }), m.locator('form[action$="/upload-201"] [type=submit]').first().click()]).catch(() => {});
    // training
    s = S('4', 'Apply for training', 'Applicant');
    await m.goto(BASE + '/applicant/operational-training'); await settle(m, 800);
    await cap(m, { ...s, id: 'S04-14', title: 'Open a training track', action: 'Open "Operational training" (or Administrative training). Choose your time in grade: 1 year, 2 years, More than 2 years.',
      expected: 'The courses for your rank and time in grade are listed.',
      targets: [{ sel: 'a:has-text("1 year")', kind: 'click', label: 'Time in grade' }] });
    const course = m.locator('text=Operational: Sample Leadership Course').first();
    if (await course.count()) {
      await course.click(); await m.waitForTimeout(600);
      await cap(m, { ...s, id: 'S04-15', title: 'Apply for a course', action: 'Select "View and apply" on a course package, check the details, then select "Apply for this training".',
        expected: 'The application is recorded for the training office.', noScroll: true,
        targets: [{ sel: 'form[action$="/applicant/training/apply"] [type=submit]', kind: 'click', label: 'Apply' }] });
      await Promise.all([m.waitForNavigation({ timeout: 10000 }), m.locator('form[action$="/applicant/training/apply"] [type=submit]').click()]).catch(() => {});
    } else WARN.push('training course not found');

    // ================= SECTION 6: unit management =================
    s = S('6', 'Unit management', 'Super admin');
    await a.goto(BASE + '/admin/organization'); await settle(a, 800);
    await cap(a, { ...s, id: 'S06-01', title: 'Open Organization', action: 'Select HRMIS > Organization in the sidebar.',
      expected: 'The hierarchy of commands and units is shown, with "Add a unit" on the right.', noScroll: true,
      targets: [{ sel: '#sidebar a:has-text("Organization")', kind: 'click', label: 'Organization' }, { sel: '.pcg-tree', kind: 'result', label: 'Hierarchy' }] });
    await a.locator('.pcg-tree a', { hasText: 'MEPCOM' }).first().click(); await settle(a, 800);
    await cap(a, { ...s, id: 'S06-02', title: 'Select the parent unit', action: 'Select the command or unit the new unit belongs under (here MEPCOM), then select "New unit" under "Add a unit under MEPCOM".',
      expected: 'The new-unit form opens with MEPCOM as the parent unit.', noScroll: true,
      targets: [{ loc: a.locator('.pcg-tree a', { hasText: 'MEPCOM' }).first(), kind: 'click', label: 'MEPCOM' }, { sel: 'summary:has-text("New unit")', kind: 'click', label: 'New unit' }] });
    await a.click('summary:has-text("New unit")'); await a.waitForTimeout(300);
    await a.fill('#add_code', 'MEPSAM');
    await a.fill('#add_name', 'Marine Environmental Protection Sample Unit');
    await a.selectOption('#add_type', { label: 'Unit' }).catch(async () => a.selectOption('#add_type', { index: 1 }));
    await a.fill('#add_effective_from', new Date().toISOString().slice(0, 10));
    await cap(a, { ...s, id: 'S06-03', title: 'Enter the unit details', action: 'Check Parent unit, then type the Code and Name, choose the Level and the Effective from date. Command group, Effective until, Display order and Description are optional.',
      expected: 'The form is ready to save.', zoom: true,
      targets: [{ sel: '#add_parent_id', kind: 'field', label: 'Parent unit' }, { sel: '#add_code', kind: 'field', label: 'Code' }, { sel: '#add_name', kind: 'field', label: 'Name' }, { sel: '#add_type', kind: 'field', label: 'Level' }, { sel: '#add_effective_from', kind: 'field', label: 'Effective from' }] });
    await cap(a, { ...s, id: 'S06-04', title: 'Save the new unit', action: 'Select "Add unit".',
      expected: 'The unit is created under MEPCOM and the change is recorded in the audit log.',
      targets: [{ sel: 'form:has(#add_code) button[type=submit]', kind: 'click', label: 'Add unit' }] });
    await Promise.all([a.waitForNavigation(), a.click('form:has(#add_code) button[type=submit]')]);
    await settle(a, 800);
    await cap(a, { ...s, id: 'S06-05', title: 'Confirm the unit was created', action: 'Read the confirmation and find the new code in the hierarchy.',
      expected: 'MEPSAM appears under MEPCOM.', kind: 'result', noScroll: true,
      targets: [{ sel: '.pcg-alert, [role=status]', kind: 'result', label: 'Saved' }, { loc: a.locator('.pcg-tree a', { hasText: 'MEPSAM' }).first(), kind: 'result', label: 'MEPSAM' }] });
    await a.locator('.pcg-tree a', { hasText: 'MEPSAM' }).first().click(); await settle(a, 800);
    await a.click('summary:has-text("Edit details")'); await a.waitForTimeout(300);
    await a.fill('#edit_description', 'Sample unit created for the user walkthrough.');
    await cap(a, { ...s, id: 'S06-06', title: 'Edit an existing unit', action: 'Select the unit in the hierarchy, then "Edit details" under "Edit MEPSAM". Change the fields and select "Save changes".',
      expected: 'The details update; history stays in the audit log.',
      targets: [{ sel: 'form:has(#edit_code) button[type=submit]', kind: 'click', label: 'Save changes' }, { sel: '#edit_description', kind: 'field', label: 'Description' }, { sel: '#edit_name', kind: 'field', label: 'Name' }] });
    await Promise.all([a.waitForNavigation(), a.click('form:has(#edit_code) button[type=submit]')]);
    await settle(a, 600);
    await a.click('button:has-text("Deactivate unit")'); await a.waitForSelector('dialog.pcg-confirm[open]'); await a.waitForTimeout(300);
    await cap(a, { ...s, id: 'S06-07', title: 'Deactivate a unit (optional)', action: 'Select "Deactivate unit". A confirmation explains it stays in the records and can be activated again.',
      expected: 'Cancel keeps it active; Deactivate unit deactivates it.', kind: 'caution', noScroll: true,
      targets: [{ sel: 'dialog.pcg-confirm .pcg-confirm__box', kind: 'caution', label: 'Confirm' }] });
    await a.click('dialog.pcg-confirm button:has-text("Cancel")');
    await a.goto(BASE + '/admin/personnel');
    const unitSel = a.getByLabel('Unit (incl. subordinate units)');
    await unitSel.selectOption({ label: await a.evaluate(() => { const o = [...document.querySelectorAll('select option')].find(o => /MEPCOM/.test(o.textContent)); return o ? o.textContent : ''; }) }).catch(e => WARN.push('unit filter: ' + e.message.split('\n')[0]));
    await Promise.all([a.waitForNavigation(), a.click('button:has-text("Filter")')]);
    await cap(a, { ...s, id: 'S06-08', title: 'View the personnel of a unit', action: 'On Personnel, choose the unit in "Unit (incl. subordinate units)" and select Filter.',
      expected: 'Only people assigned to that unit or its sub-units are listed.',
      targets: [{ loc: a.getByLabel('Unit (incl. subordinate units)'), kind: 'field', label: 'Unit' }, { sel: '.pcg-table', kind: 'result', label: 'Results' }] });
    await a.goto(BASE + '/admin/unit-users'); await settle(a, 600);
    await cap(a, { ...s, id: 'S06-09', title: 'Give a unit its checker account', action: 'Open MEPCOM registrations > Unit accounts. Under "Create account" enter Full name, Email, Unit and Password, then select "Create account".',
      expected: 'The checker can sign in at /unit/login and review that unit\'s members. "Grant M1 access" lets a checker review all units.', zoom: true,
      targets: [{ sel: 'h3:has-text("Create account")', kind: 'result', label: 'Create account' }, { sel: 'form:has(input[name=password]) button[type=submit]:has-text("Create account")', kind: 'click', label: 'Create account' }] });

    // ================= SECTION 7/8: HR registrations (draft -> submitted -> endorsed -> approved) =================
    s = S('8', 'Personnel registration processing', 'HR administrator');
    const h = await newPage(browser);
    await lib.adminLogin(h, BASE, 'hr@demo.test');
    await h.goto(BASE + '/admin/registrations'); await settle(h, 600);
    await cap(h, { ...s, id: 'S08-01', title: 'Open Registrations', action: 'Select HRMIS > Registrations. The tabs show each stage: Draft, Submitted, Returned for correction, Approved, Rejected.',
      expected: 'Select "New registration" to start one.', noScroll: true,
      targets: [{ sel: 'nav.pcg-tabs, .pcg-tabs', kind: 'field', label: 'Stages' }, { sel: 'a:has-text("New registration")', kind: 'click', label: 'New registration' }] });
    await h.click('a:has-text("New registration")'); await settle(h, 600);
    await h.fill('#serial_number', 'DEMO-0202');
    await h.selectOption('#rank_id', await h.evaluate(() => { const o = [...document.querySelectorAll('#rank_id option')].find(o => /\(SN1\)/.test(o.textContent)); return o ? o.value : document.querySelectorAll('#rank_id option')[1].value; }));
    await h.fill('#last_name', 'Sample'); await h.fill('#first_name', 'Recruit');
    await h.selectOption('#org_unit_id', await h.locator('#org_unit_id option', { hasText: 'MEPCOM ·' }).first().getAttribute('value'));
    await h.fill('#assignment_start_date', new Date().toISOString().slice(0, 10));
    await cap(h, { ...s, id: 'S08-02', title: 'Fill in the registration', action: 'Enter Serial number, Rank, Last name and First name under "Identity and service", then the Unit and Effective date under "Proposed assignment".',
      expected: 'Optional fields can stay empty.', zoom: true,
      targets: [{ sel: '#serial_number', kind: 'field', label: 'Serial number' }, { sel: '#rank_id', kind: 'field', label: 'Rank' }, { sel: '#last_name', kind: 'field', label: 'Last name' }, { sel: '#first_name', kind: 'field', label: 'First name' }] });
    await cap(h, { ...s, id: 'S08-03', title: 'Save and submit', action: 'Check the Unit and Effective date, then select "Save and submit" (or "Save draft" to finish later). If a similar record exists, the page asks you to tick "I checked that this person has no record yet...".',
      expected: 'The registration is Submitted and waits for a reviewer.',
      targets: [{ sel: '#org_unit_id', kind: 'field', label: 'Unit' }, { sel: '#assignment_start_date', kind: 'field', label: 'Effective date' }, { sel: 'button:has-text("Save and submit")', kind: 'click', label: 'Save and submit' }, { sel: 'button:has-text("Save draft")', kind: 'click', label: 'Save draft' }] });
    await Promise.all([h.waitForNavigation(), h.click('button[value=submit]')]);
    console.log('registration status:', sql("select status from personnel_registrations where serial_number='DEMO-0202'"));
    await settle(h, 600);
    await cap(h, { ...s, id: 'S08-04', title: 'Registration submitted', action: 'Check the status and the History card.',
      expected: 'Status Submitted. The author cannot approve their own registration.', kind: 'result', noScroll: true,
      targets: [{ sel: '.pcg-alert, [role=status]', kind: 'result', label: 'Submitted' }, { sel: '#h-heading', kind: 'result', label: 'History' }] });
    const regUrl = h.url();
    s = S('8', 'Personnel registration processing', 'Records reviewer');
    const r = await newPage(browser);
    await lib.adminLogin(r, BASE, 'reviewer@demo.test');
    await r.goto(regUrl); await settle(r, 600);
    await cap(r, { ...s, id: 'S08-05', title: 'Review: endorse or return', action: 'The records reviewer opens the registration. Add remarks if needed, then select Endorse, or "Return for correction" to send it back to HR.',
      expected: 'Endorsed registrations wait for the approver.',
      targets: [{ sel: '#r-heading', kind: 'result', label: 'Review' }, { sel: 'button[value=endorse]', kind: 'click', label: 'Endorse' }, { sel: 'button[value=return]', kind: 'caution', label: 'Return for correction' }] });
    await lib.autoConfirm(r);
    await Promise.all([r.waitForNavigation(), r.click('button[value=endorse]')]).catch(e => WARN.push('endorse: ' + e.message.split('\n')[0]));
    s = S('8', 'Personnel registration processing', 'Records approver');
    const p = await newPage(browser);
    await lib.adminLogin(p, BASE, 'approver@demo.test');
    await p.goto(regUrl); await settle(p, 600);
    await cap(p, { ...s, id: 'S08-06', title: 'Decide: approve or reject', action: 'The approver reads Details and History, adds Remarks (required to reject), then selects Approve.',
      expected: 'A confirmation asks "Approve and create the personnel record?"',
      targets: [{ sel: '#a-heading', kind: 'result', label: 'Decision' }, { sel: 'button[value=approve]', kind: 'click', label: 'Approve' }, { sel: 'button[value=reject]', kind: 'caution', label: 'Reject' }] });
    await p.click('button[value=approve]'); await p.waitForSelector('dialog.pcg-confirm[open]'); await p.waitForTimeout(300);
    await cap(p, { ...s, id: 'S08-07', title: 'Confirm the approval', action: 'Select Approve in the confirmation (Cancel keeps it waiting).',
      expected: 'The registration is Approved and a personnel record with its assignment is created.', noScroll: true,
      targets: [{ sel: 'dialog.pcg-confirm .pcg-btn--cta', kind: 'click', label: 'Approve' }] });
    await Promise.all([p.waitForNavigation(), p.click('dialog.pcg-confirm .pcg-btn--cta')]);
    await settle(p, 600);
    await cap(p, { ...s, id: 'S08-08', title: 'Registration approved', action: 'Read the confirmation at the top of the page.',
      expected: 'The new personnel record opens with its first assignment; the registration history reads created, submitted, endorsed, approved.', kind: 'result', noScroll: true,
      targets: [{ sel: '.pcg-alert, [role=status]', kind: 'result', label: 'Approved' }, { sel: '.pcg-console__content h2', kind: 'result', label: 'Personnel record' }] });

    // 201 documents verify, reports
    s = S('8', 'Verify 201 documents and run reports', 'Records approver');
    await p.goto(BASE + '/admin/documents'); await settle(p, 600);
    await cap(p, { ...s, id: 'S08-09', title: 'Verify 201 documents', action: 'Open HRMIS > 201 documents, tab "Pending verification". Download to check a file, then select Verify, or Reject with remarks.',
      expected: 'Verified documents move to the Verified tab.',
      targets: [{ sel: 'a:has-text("Pending verification")', kind: 'click', label: 'Pending verification' }, { sel: 'button:has-text("Verify")', kind: 'click', label: 'Verify' }, { sel: 'button:has-text("Reject")', kind: 'caution', label: 'Reject' }] });
    await p.goto(BASE + '/admin/reports'); await settle(p, 600);
    await p.selectOption('#type', { index: 1 });
    await cap(p, { ...s, id: 'S08-10', title: 'Run an HR report', action: 'Open HRMIS > Reports. Choose the Report and Unit (and dates for dated reports), then select "Show report".',
      expected: 'The report is listed on the page; it can be exported.', zoom: true,
      targets: [{ sel: '#type', kind: 'field', label: 'Report' }, { sel: '#unit', kind: 'field', label: 'Unit' }, { sel: 'button:has-text("Show report")', kind: 'click', label: 'Show report' }] });
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Show report")')]).catch(() => {});
    await settle(p, 600);
    await cap(p, { ...s, id: 'S08-11', title: 'Report results and export', action: 'Read the results; use the export button to download a CSV.',
      expected: 'The download contains only records inside your access scope; exports are audited.', kind: 'result', noScroll: true,
      targets: [{ sel: '.pcg-table, table', kind: 'result', label: 'Results' }, { sel: 'a:has-text("CSV"), button:has-text("CSV"), a:has-text("Export")', kind: 'click', label: 'Export' }] });

    // SOI submissions (admin)
    s = S('7', 'SOI submissions (Adjutant / HR admin)', 'Adjutant / HR admin / super admin');
    await a.goto(BASE + '/admin/soi'); await settle(a, 600);
    await a.fill('input[name=reason]', 'Wrong date of appointment').catch(() => {});
    await cap(a, { ...s, id: 'S07-13', title: 'Submitted SOIs', action: 'Open Statement of Information > SOI submissions. Each submitted SOI offers "Edit SOI" and Reopen (with a reason).',
      expected: 'Edit SOI corrects it yourself (it stays submitted); Reopen lets the member correct it and submit again.',
      targets: [{ sel: 'a:has-text("Edit SOI")', kind: 'click', label: 'Edit SOI' }, { sel: 'input[name=reason]', kind: 'field', label: 'Reason' }, { sel: 'button:has-text("Reopen")', kind: 'caution', label: 'Reopen' }] });
    await a.click('button:has-text("Reopen")'); await a.waitForSelector('dialog.pcg-confirm[open]'); await a.waitForTimeout(300);
    await cap(a, { ...s, id: 'S07-14', title: 'Confirm the reopening', action: 'Read the confirmation, then select "Reopen SOI" (or Cancel).',
      expected: 'The member can edit and must submit again; the reason is in the audit log.', kind: 'caution', noScroll: true,
      targets: [{ sel: 'dialog.pcg-confirm .pcg-btn--cta', kind: 'click', label: 'Reopen SOI' }] });
    await a.click('dialog.pcg-confirm button:has-text("Cancel")');
    await a.click('a:has-text("Edit SOI")'); await settle(a, 1200);
    await cap(a, { ...s, id: 'S07-15', title: 'Edit a member\'s SOI', action: 'Edit SOI opens the member\'s six steps in the console. Each Next step saves that step; step 6 is "Save changes".',
      expected: 'Every saved step is recorded in the audit log; the member\'s SOI stays submitted.', noScroll: true,
      targets: [{ sel: '.pcg-page__header h2', kind: 'result', label: 'Editing' }, { sel: 'button[x-show="step === 1"]', kind: 'click', label: 'Next step' }] });

    // ================= SECTION 9: re-enlistment =================
    s = S('9', 'Re-enlistment', 'Re-enlistment admin');
    const e = await newPage(browser);
    await e.goto(BASE + '/reenlistment/login');
    await e.fill('#email', 'reenlistment@demo.test'); await e.fill('#password', lib.ADMIN_PASSWORD);
    await cap(e, { ...s, id: 'S09-01', title: 'Sign in to re-enlistment', action: 'Open /reenlistment/login. Type your Email address and Password, then select Sign in.',
      expected: 'The ETE watchlist opens (this role signs in with the password only).',
      targets: [{ sel: '#email', kind: 'field' }, { sel: '#password', kind: 'field' }, { sel: 'button[type=submit]', kind: 'click', label: 'Sign in' }] });
    await Promise.all([e.waitForNavigation(), e.click('button[type=submit]')]);
    await settle(e, 800);
    await cap(e, { ...s, id: 'S09-02', title: 'The ETE watchlist', action: 'Read the tiles: within 3, 6 and 12 months, ETE passed. The default list shows non-officers whose end of term of enlistment (ETE) is within 12 months.',
      expected: 'Select a tile to list those people.', noScroll: true,
      targets: [{ sel: 'a:has-text("WITHIN 3 MONTHS"), a:has-text("Within 3 months")', kind: 'caution', label: 'Act now' }, { sel: 'h3:has-text("ETE within"), h2:has-text("ETE watchlist")', kind: 'result', label: 'List' }] });
    await cap(e, { ...s, id: 'S09-03', title: 'Filter the list', action: 'Set "ETE within", "Command / unit", Account and Search, then select Apply.',
      expected: 'The list and its count update.', zoom: true,
      targets: [{ sel: '#months', kind: 'field', label: 'ETE within' }, { sel: '#unit', kind: 'field', label: 'Command / unit' }, { sel: '#status', kind: 'field', label: 'Account' }, { sel: 'button:has-text("Apply")', kind: 'click', label: 'Apply' }] });
    const firstPerson = e.locator('.pcg-table a, table a').first();
    await cap(e, { ...s, id: 'S09-04', title: 'Open a member', action: 'Select a name in the list.',
      expected: 'The member\'s term of enlistment and re-enlistment form status open.', targets: [{ loc: firstPerson, kind: 'click', label: 'Member' }] });
    await Promise.all([e.waitForNavigation(), firstPerson.click()]);
    await settle(e, 600);
    await cap(e, { ...s, id: 'S09-05', title: 'Check the term and the form', action: 'Read "Term of enlistment" (ETE) and "Account and re-enlistment form": Submitted means the member has saved their SOI.',
      expected: 'Print prints this page; "Open personnel record" opens the master record when one exists.', kind: 'result', noScroll: true,
      targets: [{ sel: '#term-heading', kind: 'result', label: 'Term of enlistment' }, { sel: '#account-heading', kind: 'result', label: 'Re-enlistment form' }, { sel: 'button:has-text("Print")', kind: 'click', label: 'Print' }] });
    await e.goto(BASE + '/admin/reenlistment'); await settle(e, 600);
    await cap(e, { ...s, id: 'S09-06', title: 'Export the list', action: 'Select "Export CSV" to download the list as shown (filters applied).',
      expected: 'A CSV downloads; every view and export is recorded in the audit log.', noScroll: true,
      targets: [{ sel: 'a:has-text("Export CSV")', kind: 'click', label: 'Export CSV' }] });

    // ================= mobile (390x844) =================
    const ph = await newPage(browser, PHONE, { isMobile: true, hasTouch: true, deviceScaleFactor: 2, permissions: ['camera'] });
    s = S('M', 'Phone screens', 'All roles');
    await ph.goto(BASE + '/mepcom', { waitUntil: 'networkidle' }); await ph.waitForTimeout(2000);
    await cap(ph, { ...s, id: 'M-01', title: 'Home page on a phone', action: 'Open the menu button for the navigation links.', expected: 'The links open in a menu.', noScroll: true, targets: [{ sel: 'summary[aria-label="Open menu"]', kind: 'click', label: 'Menu' }] });
    await ph.goto(BASE + '/login');
    await cap(ph, { ...s, id: 'M-02', title: 'Sign in on a phone', action: 'Type your Email address and Password, then select Sign in.', expected: 'The dashboard opens.', targets: [{ sel: 'button[type=submit]', kind: 'click', label: 'Sign in' }] });
    await ph.goto(BASE + '/register?type=non_officer'); await settle(ph, 600);
    await cap(ph, { ...s, id: 'M-03', title: 'Registration on a phone', action: 'The same four steps fit the phone screen.', expected: 'Next moves to the following step.', targets: [{ sel: 'button[\\@click="next()"]', kind: 'click', label: 'Next' }] });
    await signIn(ph, '/login', 'sample.registrant@demo.test', 'SamplePass123!'); await settle(ph, 600);
    await cap(ph, { ...s, id: 'M-04', title: 'Member dashboard on a phone', action: 'Scroll to "What would you like to do?" and select a task.', expected: 'The task opens.', kind: 'result', noScroll: true, targets: [] });
    const uph = await newPage(browser, PHONE, { isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await signIn(uph, '/unit/login', 'unit@demo.test', PASS); await settle(uph, 600);
    await cap(uph, { ...s, id: 'M-05', title: 'Unit review queue on a phone', action: 'Filter and select Review as on a computer.', expected: 'The review window opens full screen.', noScroll: true, targets: [{ sel: '.uc-filter', kind: 'field', label: 'Filters' }] });
    await e.setViewportSize(PHONE); await e.goto(BASE + '/admin/reenlistment'); await settle(e, 600);
    await cap(e, { ...s, id: 'M-06', title: 'ETE watchlist on a phone', action: 'Open the menu for the sidebar; wide tables scroll sideways inside their frame.', expected: 'The page fits the phone width.', noScroll: true, targets: [] });

    const errs = [g, n, a, u, m, h, r, p, e, ph, uph].flatMap(x => x.jsErrors);
    if (errs.length) WARN.push('JS errors: ' + [...new Set(errs)].slice(0, 5).join(' | '));
  } catch (err) {
    WARN.push('CRASH: ' + err.message.split('\n')[0]);
    console.error(err);
  }
  fs.writeFileSync(path.join(OUT, 'steps.json'), JSON.stringify({ base: BASE, captured: new Date().toISOString(), steps: STEPS, warnings: WARN }, null, 1));
  console.log('steps', STEPS.length, 'warnings', WARN.length); WARN.forEach(w => console.log('WARN', w));
  await browser.close();
})();
