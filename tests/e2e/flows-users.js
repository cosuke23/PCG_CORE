// End-to-end flows for unit checkers, personnel and registration (dev DB).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const S = require('os').tmpdir();
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || "mariadb -uroot"} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const results = [];
const check = (name, ok, detail = '') => results.push([ok ? 'PASS' : 'FAIL', name, detail]);
const PDF = `${S}/sample.pdf`;
fs.writeFileSync(PDF, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

async function login(browser, url, email, pass, opts = {}) {
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, ...opts });
  const page = await ctx.newPage();
  page.jsErrors = [];
  page.on('pageerror', e => page.jsErrors.push(e.message.split('\n')[0]));
  page.dialogs = [];
  page.on('dialog', d => { page.dialogs.push(d.message()); d.accept(); });
  if (url) {
    await page.goto(BASE + url);
    await page.fill('#email', email); await page.fill('#password', pass);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }
  return page;
}

async function step(name, fn) {
  try { await fn(); } catch (e) { check(name, false, 'crashed: ' + e.message.split('\n')[0]); }
}

(async () => {
  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });

  // ---------------- unit checker: reject + approve documents ----------------
  const unit = await login(browser, '/unit/login', 'unit@demo.test', 'Demo@12345');
  await step('Unit checker rejects a document with a comment', async () => {
    const serial = sql("select pd.serial_number from personnel_data pd join users u on u.serial_number=pd.serial_number where u.mother_unit='MEPLEF' order by pd.id limit 1");
    await unit.goto(BASE + '/unit/dashboard');
    await unit.locator('tr', { hasText: serial }).first().dblclick();
    await unit.waitForSelector('#doc_box input[name="docs[1][checked]"]', { state: 'visible', timeout: 5000 });
    await unit.check('#doc_box input[name="docs[1][checked]"]');
    await unit.fill('#doc_box [name="docs[1][comment]"]', 'Flow test: NBI clearance is blurred');
    await unit.click('button:has-text("❌ REJECT")');
    await unit.click('.swal2-confirm').catch(() => {});
    await unit.waitForLoadState('networkidle'); await unit.waitForTimeout(500);
    const r = sql(`select concat(doc_type,'|',status) from rejected_documents where serial_number='${serial}' and comment like 'Flow test%'`);
    check('Unit checker rejects a document with a comment', r === 'NBI Clearance|rejected', r || 'no row');
  });
  await step('Unit checker approves documents', async () => {
    const serial = sql("select pd.serial_number from personnel_data pd join users u on u.serial_number=pd.serial_number where u.mother_unit='MEPLEF' order by pd.id desc limit 1");
    await unit.goto(BASE + '/unit/dashboard');
    await unit.locator('tr', { hasText: serial }).first().dblclick();
    await unit.click('button:has-text("✔ ACCEPT")');
    await unit.click('.swal2-confirm').catch(() => {});
    await unit.waitForLoadState('networkidle'); await unit.waitForTimeout(500);
    const st = sql(`select status from personnel_data where serial_number='${serial}'`);
    check('Unit checker approves documents', st.toLowerCase() === 'approved', st);
  });
  check('No JS errors (unit)', unit.jsErrors.length === 0, unit.jsErrors.slice(0, 2).join(' | '));

  // ---------------- M1: disciplinary upload ----------------
  const m1 = await login(browser, '/unit/loginM1', 'm1@demo.test', 'Demo@12345');
  await step('M1 uploads a disciplinary record', async () => {
    await m1.goto(BASE + '/unit/dashboardM1');
    await m1.fill('#serialInput', 'DEMO-0003');
    await m1.locator('#serialInput ~ button, #serialInput + * button, button:near(#serialInput)').first().click();
    await m1.waitForSelector('#serialModal:not(.hidden)', { timeout: 5000 });
    await m1.setInputFiles('#serialModal input[name="disciplinary_files[]"]', PDF);
    await Promise.all([m1.waitForNavigation(), m1.locator('#serialModal button[type=submit]').click()]);
    const n = sql("select count(*) from reinlist_disciplinary_actions where serial_number='DEMO-0003'");
    check('M1 uploads a disciplinary record', n === '1', `rows=${n}`);
  });
  check('No JS errors (M1)', m1.jsErrors.length === 0, m1.jsErrors.slice(0, 2).join(' | '));

  // ---------------- personnel ----------------
  const me = await login(browser, '/login', 'member@demo.test', 'Demo@12345');
  await step('Personnel uploads a required document', async () => {
    await me.goto(BASE + '/upload-documents');
    await me.setInputFiles('input[name=ombudsman_clearance]', PDF);
    await Promise.all([me.waitForNavigation({ timeout: 15000 }), me.locator('#uploadForm [type=submit]').last().click()]);
    await me.click('.swal2-confirm').catch(() => {});
    const v = sql("select ombudsman_clearance from personnel_data pd join users u on u.id=pd.personnel_id where u.email='member@demo.test'");
    check('Personnel uploads a required document', /^documents\/\d+_sample\.pdf$/.test(v), v);
    check('Upload success message shown', (await me.content()).includes('Documents uploaded successfully'));
    const res = await me.request.get(BASE + '/files/documents_toupload/' + v);
    check('Personnel can open own uploaded file', res.status() === 200);
  });
  await step('Personnel cannot upload a non-PDF as a clearance', async () => {
    const bad = `${S}/evil.php`; fs.writeFileSync(bad, '<?php echo 1;');
    await me.goto(BASE + '/upload-documents');
    await me.setInputFiles('input[name=ombudsman_clearance]', bad);
    await Promise.all([me.waitForNavigation({ timeout: 15000 }), me.locator('#uploadForm [type=submit]').last().click()]).catch(() => {});
    const html = await me.content();
    const blocked = html.includes('must be a file of type') || me.dialogs.some(m => /pdf/i.test(m)) || (await me.locator('.swal2-popup').count()) > 0;
    const v = sql("select ombudsman_clearance from personnel_data pd join users u on u.id=pd.personnel_id where u.email='member@demo.test'");
    check('Non-PDF clearance is refused (client or server)', blocked && !/evil/.test(v), 'dialogs=' + me.dialogs.join(' / ').slice(0, 80));
  });
  await step('Personnel uploads a 201 file', async () => {
    await me.goto(BASE + '/upload-201');
    await me.setInputFiles('input[name=appointment]', PDF);
    await Promise.all([me.waitForNavigation({ timeout: 15000 }), me.locator('form[action$="/upload-201"] [type=submit]').first().click()]);
    const v = sql("select appointment from personnel_201_files p join users u on u.id=p.personnel_id where u.email='member@demo.test'");
    check('Personnel uploads a 201 file', v.startsWith('201_files/appointment/'), v);
  });
  await step('Personnel saves SOI step 1', async () => {
    await me.goto(BASE + '/profile');
    await me.waitForTimeout(500);
    const name = 'Flowtest';
    await me.evaluate(n => { const el = document.querySelector('[x-model="basic.first_name"]'); el.value = n; el.dispatchEvent(new Event('input')); }, name);
    await me.locator('button[x-show="step === 1"]').click();
    await me.waitForTimeout(1500);
    const v = sql("select first_name from reinlist_basic_info where serial_number='DEMO-0001'");
    check('Personnel saves SOI step 1', v === name, v);
  });
  await step('Personnel applies for training', async () => {
    await me.goto(BASE + '/applicant/operational-training');
    const before = sql("select count(*) from training_applications where serial_number='DEMO-0001'");
    await me.locator('text=Operational: Sample Leadership Course').first().click();
    await Promise.all([me.waitForNavigation({ timeout: 10000 }), me.locator('form[action$="/applicant/training/apply"] [type=submit]').click()]);
    const after = sql("select count(*) from training_applications where serial_number='DEMO-0001'");
    check('Personnel applies for training', Number(after) >= Number(before), `before=${before} after=${after}`);
  });
  check('No JS errors (personnel)', me.jsErrors.length === 0, me.jsErrors.slice(0, 3).join(' | '));

  // ---------------- guest registration ----------------
  const g = await login(browser, null);
  await step('Guest registers (non-officer)', async () => {
    await g.goto(BASE + '/register?type=non_officer');
    await g.waitForTimeout(500);
    fs.writeFileSync(`${S}/register-form.html`, await g.content());
    check('Registration page loads', true);
  });

  await browser.close();
  for (const r of results) console.log(r.join('  '));
})().catch(e => { console.error('CRASH', e.message); for (const r of results) console.log(r.join('  ')); process.exit(1); });
