// End-to-end admin flows against the dev DB. Each step acts through the UI,
// then checks the database.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const S = require('os').tmpdir();
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || "mariadb -uroot"} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const results = [];
const check = (name, ok, detail = '') => { results.push([ok ? 'PASS' : 'FAIL', name, detail]); };

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
  const jsErrors = [];
  page.on('pageerror', e => jsErrors.push(e.message.split('\n')[0]));
  page.on('dialog', d => d.accept());

  await require('./lib').adminLogin(page, BASE);

  // 1-2. Approve and reject pending registrations (double-click row -> dialog)
  const pending = sql("select id,email from users where status='Pending' order by id limit 2").split('\n').map(l => l.split('\t'));
  for (const [idx, action] of [[0, 'Approve'], [1, 'Reject']]) {
    const [id, email] = pending[idx];
    await page.goto(BASE + '/admin/dashboard', { waitUntil: 'networkidle' });
    await page.waitForSelector('.dt-search input[type=search]');  // DataTables initialised
    await page.fill('.dt-search input[type=search]', email);
    await page.locator('#personnelTable tbody tr', { hasText: email }).first().waitFor();
    await page.locator('#personnelTable tbody tr', { hasText: email }).first().dblclick();
    await page.click(`.swal2-popup button:has-text("${action}")`);
    await page.waitForSelector('.swal2-popup:has-text("' + (action === 'Approve' ? 'Approved!' : 'Rejected') + '")', { timeout: 5000 }).catch(() => {});
    const status = sql(`select status from users where id=${id}`);
    check(`${action} pending registration`, status === (action === 'Approve' ? 'Approved' : 'Rejected'), `status=${status}`);
  }

  // 3. Unit account: create, set password, deactivate
  await page.goto(BASE + '/admin/unit-users');
  await page.fill('#full_name', 'Flow Test Checker'); await page.fill('#email', 'flow.checker@demo.test');
  await page.selectOption('#unit_name', 'NOCOP'); await page.fill('#new_password', 'FlowPass123');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Create account")')]);
  const created = sql("select concat(unit_name,'|',is_active,'|',password like '\\$2y\\$%') from unit_users where email='flow.checker@demo.test'");
  check('Create unit account', created === 'NOCOP|1|1', created);
  check('Success message shown after create', await page.isVisible('.pcg-alert--success'));
  const uid = sql("select id from unit_users where email='flow.checker@demo.test'");
  const before = sql(`select password from unit_users where id=${uid}`);
  await page.fill(`#pw-${uid}`, 'ChangedPass456');
  await Promise.all([page.waitForNavigation(), page.locator('tr', { hasText: 'flow.checker@demo.test' }).locator('button:has-text("Set password")').click()]);
  check('Set unit account password', sql(`select password from unit_users where id=${uid}`) !== before);
  await Promise.all([page.waitForNavigation(), page.locator('tr', { hasText: 'flow.checker@demo.test' }).locator('button:has-text("Deactivate")').click()]);
  check('Deactivate unit account', sql(`select is_active from unit_users where id=${uid}`) === '0');

  // 4. Training course create / edit / delete
  await page.goto(BASE + '/admin/training-courses/create');
  await page.fill('[name=course_name]', 'Flow Test Course'); await page.fill('[name=description]', 'Created by flow test');
  await page.selectOption('[name=status]', { index: 1 }).catch(() => {});
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/admin/training-courses"] button[type=submit]')]);
  const courseId = sql("select id from training_courses where course_name='Flow Test Course'");
  check('Create training course', !!courseId, `id=${courseId}`);
  await page.goto(BASE + `/admin/training-courses/${courseId}/edit`);
  await page.fill('[name=course_name]', 'Flow Test Course (edited)');
  await Promise.all([page.waitForNavigation(), page.click(`form[action$="/admin/training-courses/${courseId}"] button[type=submit]`)]);
  check('Edit training course', sql(`select course_name from training_courses where id=${courseId}`) === 'Flow Test Course (edited)');

  // 5. Add student, CSV import, remove student
  await page.goto(BASE + `/training-courses/${courseId}/students/create`);
  const serial = sql("select serial_number from users where email='member@demo.test'");
  await page.selectOption('#serial_number', serial);
  await Promise.all([page.waitForNavigation(), page.locator(`form[action$="/training-courses/${courseId}/students"] button[type=submit]`).first().click()]);
  const enrolled = sql(`select concat(serial_number,'|',full_name,'|',ifnull(user_id,'NULL')) from course_students where course_id=${courseId}`);
  check('Add student manually (saves serial, name, user)', /^DEMO-0001\|Juan Santos\|\d+$/.test(enrolled), enrolled);
  check('Student list shows name', (await page.content()).includes('Juan Santos'));

  const csv = `${S}/students.csv`;
  const u2 = sql("select concat(serial_number,',',name) from users where email like 'maria%'");
  fs.writeFileSync(csv, `serial_number,full_name\n${u2}\n`);
  await page.goto(BASE + `/training-courses/${courseId}/students/create`);
  await page.click('button:has-text("Upload CSV")');
  await page.setInputFiles('input[name=csv_file]', csv);
  await Promise.all([page.waitForNavigation(), page.locator(`form[action$="/students/import-csv"] button[type=submit]`).click()]);
  const count = sql(`select count(*) from course_students where course_id=${courseId}`);
  check('Import students from CSV', count === '2', `enrolled=${count}`);

  await page.goto(BASE + `/training-courses/${courseId}/students`);
  await page.locator('form.remove-student-form button[type=submit]').first().click();
  await page.click('.swal2-confirm').catch(() => {});
  await page.waitForLoadState('networkidle');
  check('Remove student', sql(`select count(*) from course_students where course_id=${courseId}`) === '1');

  // delete course (with confirmation dialog)
  await page.goto(BASE + '/admin/training-courses');
  await page.locator(`form[action$="/admin/training-courses/${courseId}"] button[type=submit]`).click();
  await page.click('.swal2-confirm').catch(() => {});
  await page.waitForLoadState('networkidle'); await page.waitForTimeout(500);
  check('Delete training course (with confirmation)', sql(`select count(*) from training_courses where id=${courseId}`) === '0');

  // 6. OSAERT bulk upload
  const ocsv = `${S}/osaert.csv`;
  fs.writeFileSync(ocsv, 'name,company,date_attended,date_ended\nFlow Trainee One,Flow Co,20/09/2026,22/09/2026\n');
  await page.goto(BASE + '/admin/osaert');
  await page.setInputFiles('form[action$="/admin/osaert/bulk-upload"] input[name=file]', ocsv);
  await Promise.all([page.waitForNavigation(), page.locator('form[action$="/admin/osaert/bulk-upload"] button[type=submit]').click()]);
  const tr = sql("select concat(date_attended,'|',date_ended) from osaert_trainees where name='Flow Trainee One'");
  check('OSAERT bulk upload', !!tr, tr);
  await page.goto(BASE + '/admin/osaert?ended_year=2026');
  check('OSAERT year filter finds uploaded row', (await page.content()).includes('Flow Trainee One'));
  await page.goto(BASE + '/admin/osaert/certificates');
  const content = await page.content();
  check('Certificate number well-formed', /OSAERT-2026-\d{6}/.test(content) && !/OSAERT-\d-\d\d-/.test(content));

  // 7. Training application status
  const appId = sql("select id from training_applications where status='Pending' limit 1");
  await page.goto(BASE + '/admin/training-applications');
  const form = page.locator(`form[action$="/admin/training-applications/${appId}/status"]`);
  await form.locator('select[name=status]').selectOption('Approved');
  if (await form.locator('button[type=submit]').count()) {
    await Promise.all([page.waitForNavigation(), form.locator('button[type=submit]').click()]);
  } else { await page.waitForNavigation({ timeout: 5000 }).catch(() => {}); }
  check('Update training application status', sql(`select status from training_applications where id=${appId}`) === 'Approved');

  // 8. Admin accounts: create an HR admin, who must enrol two-factor sign-in
  await page.goto(BASE + '/admin/accounts');
  await page.fill('#name', 'Flow HR Admin'); await page.fill('#email', 'flow.hr@demo.test');
  await page.check('input[name=role][value=hr_admin]'); await page.fill('#password', 'FlowAdminPass123');
  await Promise.all([page.waitForNavigation(), page.click('button:has-text("Create account")')]);
  check('Create admin account', sql("select role from admin_users where email='flow.hr@demo.test'") === 'hr_admin');

  const ctx2 = await browser.newContext({ viewport: { width: 1366, height: 900 } });
  const p2 = await ctx2.newPage();
  p2.on('pageerror', e => jsErrors.push(e.message.split('\n')[0]));
  await p2.goto(BASE + '/admin/login');
  await p2.fill('#email', 'flow.hr@demo.test'); await p2.fill('#password', 'FlowAdminPass123');
  await Promise.all([p2.waitForNavigation(), p2.click('button[type=submit]')]);
  check('New admin is sent to two-factor setup', p2.url().endsWith('/admin/two-factor/setup'));
  await p2.waitForTimeout(500);
  check('QR code rendered for authenticator app', (await p2.locator('[data-pcg-qr] canvas, [data-pcg-qr] img').count()) > 0);
  const key = (await p2.textContent('.pcg-code')).replace(/\s+/g, '');
  await p2.fill('#code', require('./lib').totp(key));
  await Promise.all([p2.waitForNavigation(), p2.click('button[type=submit]')]);
  check('Recovery codes shown after enrolment', (await p2.locator('.pcg-codes li').count()) === 8);
  await Promise.all([p2.waitForNavigation(), p2.click('a:has-text("continue")')]);
  check('HR admin lands on dashboard; no LMS/admin-account menu', p2.url().endsWith('/admin/dashboard')
    && (await p2.locator('.pcg-nav a:has-text("Training courses")').count()) === 0
    && (await p2.locator('.pcg-nav a:has-text("Admin accounts")').count()) === 0);
  const r = await p2.goto(BASE + '/admin/osaert');
  check('HR admin cannot open OSAERT (403)', r.status() === 403);
  await ctx2.close();

  await page.goto(BASE + '/admin/accounts/audit-log');
  const audit = await page.content();
  check('Audit log shows approvals and account changes', audit.includes('admin.accounts.store') && /approve/.test(audit));

  check('No JavaScript errors during admin flows', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '));
  await browser.close();
  for (const r of results) console.log(r.join('  '));
})().catch(e => { console.error('FLOW CRASH', e.message); for (const r of results) console.log(r.join('  ')); process.exit(1); });
