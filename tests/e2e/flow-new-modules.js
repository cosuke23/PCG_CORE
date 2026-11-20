// End-to-end checks for the 2026-11-20 modules (dev DB only, after `php artisan db:seed`):
// unit checker sign-in code, clearance card, deployments -> RNR -> readiness, leave summary and
// CSV, member notifications (bell, page, Android poll), admin unit check, audit "Who" filter,
// privacy notice and consent, /health. Run: node tests/e2e/flow-new-modules.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { adminLogin, autoConfirm, passUnitCode } = require('./lib');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || 'mariadb -uroot'} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const results = [];
const check = (name, ok, detail = '') => results.push([ok ? 'PASS' : 'FAIL', name, detail]);
const step = async (name, fn) => { try { await fn(); } catch (e) { check(name, false, 'crashed: ' + e.message.split('\n')[0]); } };
const ymd = d => d.toISOString().slice(0, 10);
const today = new Date(Date.now() + 8 * 3600 * 1000);          // Asia/Manila
const plus = n => ymd(new Date(today.getTime() + n * 86400000));

async function signIn(browser, path, email, pass, opts = {}) {
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true, ...opts });
  const page = await ctx.newPage();
  page.jsErrors = [];
  page.on('pageerror', e => page.jsErrors.push(e.message.split('\n')[0]));
  await autoConfirm(page);
  await page.goto(BASE + path);
  await page.fill('#email', email); await page.fill('#password', pass);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return page;
}

(async () => {
  const browser = await chromium.launch();
  const member = sql("select concat(id,'|',serial_number) from users where email='member@demo.test'").split('|');
  const [memberId, serial] = [member[0], member[1]];

  // ------------------------------------------------ guest
  await step('Health and privacy are public', async () => {
    const page = await (await browser.newContext()).newPage();
    const res = await page.goto(BASE + '/health');
    check('/health answers {"status":"ok"} without details', res.status() === 200 && (await res.text()) === '{"status":"ok"}');
    await page.goto(BASE + '/privacy');
    check('Privacy notice names the Data Privacy Act', (await page.textContent('body')).includes('Data Privacy Act of 2012'));
    await page.goto(BASE + '/register?type=non_officer');
    check('Registration asks for privacy consent', await page.locator('input[name=privacy_consent]').count() === 1);
  });

  // ------------------------------------------------ unit checker: emailed code
  // a clearance expiring in 5 days for the clearance card
  sql(`update personnel_data set nbi_clearance_expiration='${plus(5)}' where serial_number='${serial}' and nbi_clearance is not null and nbi_clearance<>''`);
  const unit = await signIn(browser, '/unit/login', 'unit@demo.test', 'Demo@12345');
  check('Unit sign-in asks for the emailed code', unit.url().includes('/unit/two-factor'), unit.url());
  await passUnitCode(unit, 'unit@demo.test');
  check('The emailed code signs the checker in', unit.url().endsWith('/unit/dashboard'), unit.url());

  await step('Clearance card', async () => {
    const card = unit.locator('#uc-clear-title');
    check('Dashboard shows clearances expiring within 30 days', await card.count() === 1 && /expire/.test(await card.textContent()),
      await card.count() ? await card.textContent() : 'no card (does the demo member have an NBI file?)');
  });

  await step('Deployments -> RNR -> readiness', async () => {
    await unit.goto(BASE + '/unit/deployments');
    await unit.selectOption('#dp-member', memberId);
    await unit.fill('#dp-where', 'E2E patrol, BRP Gabriela Silang');
    await unit.fill('#dp-from', plus(-10));
    await Promise.all([unit.waitForNavigation(), unit.click('button:has-text("Record deployment")')]);
    check('Recording a deployment shows it as deployed now', (await unit.textContent('body')).includes('E2E patrol') && (await unit.textContent('h2')).includes('deployed today'));
    await unit.goto(BASE + '/unit/leave/readiness');
    check('Readiness counts the member as deployed', (await unit.textContent('body')).includes('E2E patrol'));
    await unit.goto(BASE + '/unit/deployments');
    const row = unit.locator('tr', { hasText: 'E2E patrol' }).first();
    await row.locator('summary').click();
    await row.locator('input[name=rnr_days]').fill('3');
    await Promise.all([unit.waitForNavigation(), row.locator('button:has-text("Save")').click()]);
    const rnr = sql(`select days from leave_credit_entries where serial_number='${serial}' and pool='rnr' and note like '%E2E patrol%' order by id desc limit 1`);
    check('Ending it with RNR adds the credit', rnr === '3.00', rnr || 'no entry');
  });

  await step('Leave summary and CSV', async () => {
    await unit.goto(BASE + '/unit/leave/summary');
    check('Unit leave summary opens', (await unit.textContent('h2')).includes('leave'));
    const [dl] = await Promise.all([unit.waitForEvent('download'), unit.click('button:has-text("Download CSV")')]);
    const csv = require('fs').readFileSync(await dl.path(), 'utf8');
    check('CSV export has the header and no reasons column', csv.includes('Reference,Serial number') && !csv.includes('Reason'));
  });

  await step('Leave decision notifies the member', async () => {
    sql(`insert into leave_requests (user_id, serial_number, unit_name, leave_type, start_date, end_date, days, reason, status, created_at, updated_at)
         values (${memberId}, '${serial}', 'MEPLEF', 'vacation', '${plus(20)}', '${plus(21)}', 2, 'e2e', 'pending', now(), now())`);
    await unit.goto(BASE + '/unit/leave');
    const form = unit.locator('form.lc-review').first();
    await Promise.all([unit.waitForNavigation(), form.locator('button[value=approve]').click()]);
    const n = sql(`select count(*) from member_notifications where user_id=${memberId} and kind='leave.approved'`);
    check('Approving leave adds a member notification', Number(n) >= 1, n);
  });

  // ------------------------------------------------ member: bell, page, Android poll
  await step('Member notifications', async () => {
    const m = await signIn(browser, '/login', 'member@demo.test', 'Demo@12345', { userAgent: 'Mozilla/5.0 (Linux; Android 14) PCGHRMIS-Android/1.0.3' });
    await m.goto(BASE + '/dashboard');
    check('The bell shows a count', await m.locator('.pcg-bell__count').count() === 1);
    const meta = await m.getAttribute('meta[name=pcg-app-notify]', 'content');
    check('The Android app gets its notify token', /^\d+:[0-9a-f]{64}$/.test(meta || ''));
    const [u, t] = (meta || ':').split(':');
    const poll = await (await browser.newContext()).request.get(`${BASE}/api/app/notifications?u=${u}&t=${t}&after=0`);
    const json = await poll.json();
    check('The app poll returns the notification without a session', poll.status() === 200 && json.items.length >= 1 && !poll.headers()['set-cookie'], JSON.stringify(json).slice(0, 80));
    await m.goto(BASE + '/notifications');
    check('Notifications page lists the leave decision', (await m.textContent('body')).includes('is approved'));
    await m.goto(BASE + '/dashboard');
    check('Opening the page clears the bell', await m.locator('.pcg-bell__count').count() === 0);
    check('No JavaScript errors for the member', m.jsErrors.length === 0, m.jsErrors.join('; '));
  });

  // ------------------------------------------------ admin
  await step('Admin pages', async () => {
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true });
    const a = await ctx.newPage();
    await autoConfirm(a);
    await adminLogin(a, BASE);
    await a.goto(BASE + '/admin/accounts/audit-log?actor=unit');
    check('Audit log filters unit checker actions', (await a.textContent('body')).includes('unit: unit@demo.test'));
    await a.goto(BASE + '/admin/deployments');
    check('Admin deployments list shows the record', (await a.textContent('body')).includes('E2E patrol'));
    await a.goto(BASE + '/admin/leave/summary');
    check('Admin leave summary opens', (await a.textContent('h2')).includes('leave'));
    await a.goto(BASE + '/admin/unit-check');
    const head = await a.textContent('h2');
    check('Unit check opens', /units (disagree|agree)/.test(head), head);
    const fix = a.locator('button:has-text("Set sign-up unit to")').first();
    if (await fix.count()) {
      await Promise.all([a.waitForNavigation(), fix.click()]);
      check('A one-click fix is applied', (await a.textContent('.pcg-console__flash')).includes('sign-up unit is now'));
    }
  });

  for (const [s, n, d] of results) console.log(`${s}  ${n}${d ? '  (' + d + ')' : ''}`);
  const failed = results.filter(r => r[0] === 'FAIL').length;
  console.log(`\n${results.length - failed} passed, ${failed} failed`);
  await browser.close();
  process.exit(failed ? 1 : 0);
})();
