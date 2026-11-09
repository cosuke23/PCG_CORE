// End-to-end: an admin corrects a member's submitted SOI (/admin/soi -> Edit SOI),
// steps 1 to 6, and the in-page confirmation dialog on Reopen. Run on a re-seeded
// pcg_core_dev with the app at PCG_E2E_URL (default http://127.0.0.1:8123).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const SHOTS = process.env.PCG_E2E_SHOTS || require('os').tmpdir();
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || "mariadb -uroot"} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const results = [];
const check = (name, ok, detail = '') => results.push([ok ? 'PASS' : 'FAIL', name, detail]);
const SERIAL = 'DEMO-0002';

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message.split('\n')[0]));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', async r => { if (r.status() === 422) console.log('422', r.url(), (await r.text()).slice(0, 300)); });
  try {
    const uid = sql(`select id from users where serial_number='${SERIAL}'`);
    sql(`update users set soi_submitted_at=now() where id=${uid}`);
    const audits = () => Number(sql(`select count(*) from admin_audit_logs where action='soi.edited' and subject='users:${uid}'`));
    const before = audits();

    await require('./lib').adminLogin(page, BASE);
    await page.goto(`${BASE}/admin/soi?show=all&q=${SERIAL}`);
    await Promise.all([page.waitForNavigation(), page.click('a:has-text("Edit SOI")')]);
    await page.waitForTimeout(800);
    check('Edit SOI opens the member form in the console', page.url().endsWith(`/admin/soi/${uid}/edit`)
      && (await page.textContent('h2')).includes('Editing the SOI of'));
    check('Admin form is not locked', (await page.locator('#soi-root.soi--locked').count()) === 0
      && await page.isEditable('[x-model="basic.first_name"]'));
    await page.screenshot({ path: `${SHOTS}/soi-admin-edit.png` });

    const name = 'Mariadmin';
    await page.fill('[x-model="basic.first_name"]', name);
    for (let n = 1; n <= 5; n++) {
      if (n === 3 && !(await page.inputValue('[x-model="dep.name"] >> nth=0'))) { // the sample member has no dependent yet
        await page.fill('[x-model="dep.name"] >> nth=0', 'Ana Reyes');
        await page.selectOption('[x-model="dep.relationship"] >> nth=0', { index: 1 });
        await page.fill('[x-model="dep.bday"] >> nth=0', '2015-04-02');
      }
      await page.locator(`button[x-show="step === ${n}"]`).click();
      await page.waitForFunction(k => window.Alpine && document.querySelector('#soi-root')._x_dataStack[0].step === k, n + 1, { timeout: 8000 })
        .catch(() => {});
      await page.click('.swal2-confirm', { timeout: 500 }).catch(() => {}); // a validation message, if any
    }
    check('Steps 1-5 saved for the member', sql(`select first_name from reinlist_basic_info where serial_number='${SERIAL}'`) === name);
    await page.locator('button[x-show="step === 6"]:has-text("Save changes")').click();
    await page.waitForSelector('.swal2-popup', { timeout: 8000 });
    const msg = await page.textContent('.swal2-popup');
    check('Step 6 saves with "Changes saved" and no submit prompt', /Changes saved/.test(msg) && !/correct\?/.test(msg), msg.slice(0, 80));
    await page.screenshot({ path: `${SHOTS}/soi-admin-saved.png` });
    await page.click('.swal2-confirm');
    check('Each saved step audited (soi.edited)', audits() - before === 6, `audited=${audits() - before}`);
    check('The SOI stays submitted for the member', sql(`select soi_submitted_at is not null from users where id=${uid}`) === '1');

    // the in-page confirmation (no browser confirm): Cancel keeps it, Reopen SOI reopens
    await page.goto(`${BASE}/admin/soi?show=all&q=${SERIAL}`);
    await page.fill('input[name=reason]', 'E2E: wrong date of appointment');
    await page.click('button:has-text("Reopen")');
    await page.waitForSelector('dialog.pcg-confirm[open]');
    await page.click('dialog.pcg-confirm button:has-text("Cancel")');
    await page.waitForTimeout(300);
    check('Cancel in the dialog keeps the SOI submitted', sql(`select soi_submitted_at is not null from users where id=${uid}`) === '1'
      && (await page.locator('dialog.pcg-confirm').count()) === 0);
    await page.click('button:has-text("Reopen")');
    await page.waitForSelector('dialog.pcg-confirm[open]');
    await Promise.all([page.waitForNavigation(), page.click('dialog.pcg-confirm button:has-text("Reopen SOI")')]);
    check('Reopen SOI in the dialog reopens it', sql(`select soi_submitted_at is null from users where id=${uid}`) === '1');

    // phone width
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE}/admin/soi/${uid}/edit`);
    await page.waitForTimeout(600);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check('Admin SOI editor fits a phone', overflow <= 1, `overflow=${overflow}px`);
    await page.screenshot({ path: `${SHOTS}/soi-admin-phone.png` });
  } catch (e) {
    check('flow crashed', false, e.message.split('\n')[0]);
  }
  check('No JS or CSP errors', errors.length === 0, errors.slice(0, 3).join(' | '));
  await browser.close();
  for (const r of results) console.log(r.join('  '));
})();
