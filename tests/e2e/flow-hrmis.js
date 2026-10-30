// End-to-end HRMIS workflow against the dev DB (run after pcg:dev-seed and
// pcg:personnel-sync --apply --assign-to=MEPCOM): registration -> approval by
// another admin -> document upload -> verification -> promotion -> reports,
// plus MEPCOM-scope and auditor checks. Each step acts through the UI, then
// checks the database.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { adminLogin } = require('./lib');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || "mariadb -uroot"} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const results = [];
const check = (name, ok, detail = '') => results.push([ok ? 'PASS' : 'FAIL', name, detail]);
const serial = 'E2E-' + Date.now().toString().slice(-6);

async function session(browser, email) {
  const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true })).newPage();
  page.errors = [];
  page.on('pageerror', e => page.errors.push(e.message.split('\n')[0]));
  page.on('dialog', d => d.accept());
  await adminLogin(page, BASE, email);
  return page;
}

(async () => {
  const browser = await chromium.launch();
  try {
    // 1. HR prepares and submits a registration
    const hr = await session(browser, 'hr@demo.test');
    await hr.goto(BASE + '/admin/registrations/create');
    await hr.fill('#serial_number', serial);
    await hr.selectOption('#rank_id', { label: 'Ensign (ENS)' });
    await hr.fill('#last_name', 'Testrecord' + serial.slice(-4)); await hr.fill('#first_name', 'Elena');
    await hr.selectOption('#org_unit_id', await hr.locator('#org_unit_id option', { hasText: 'MEPCOM ·' }).getAttribute('value'));
    await hr.fill('#assignment_start_date', '2026-01-15');
    await Promise.all([hr.waitForNavigation(), hr.click('button[value=submit]')]);
    const regId = sql(`select id from personnel_registrations where serial_number='${serial}'`);
    check('HR submits a registration', sql(`select status from personnel_registrations where id=${regId}`) === 'submitted');
    check('No personnel record before approval', sql(`select count(*) from personnel where serial_number='${serial}'`) === '0');
    check('Author sees no decision buttons', await hr.locator('button[value=approve]').count() === 0);

    // 2. Approver approves
    const ap = await session(browser, 'approver@demo.test');
    await ap.goto(`${BASE}/admin/registrations/${regId}`);
    await Promise.all([ap.waitForNavigation(), ap.click('button[value=approve]')]);
    const pid = sql(`select id from personnel where serial_number='${serial}'`);
    check('Approver approves; record and assignment created', pid !== '' && sql(`select count(*) from personnel_assignments where personnel_id=${pid} and end_date is null`) === '1');
    check('History has created/submitted/approved', sql(`select group_concat(action order by id) from registration_events where registration_id=${regId}`) === 'created,submitted,approved');

    // 3. HR uploads a 201 document
    const pdf = path.join(require('os').tmpdir(), 'e2e-order.pdf');
    fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
    await hr.goto(`${BASE}/admin/personnel/${pid}`);
    await hr.click('summary:has-text("Upload document")');
    await hr.selectOption('#category_id', { label: 'Appointment and service documents' });
    await hr.fill('#doc_title', 'Appointment order');
    await hr.setInputFiles('#doc_file', pdf);
    await Promise.all([hr.waitForNavigation(), hr.click('button:has-text("Upload")')]);
    const docId = sql(`select id from personnel_documents where personnel_id=${pid}`);
    check('HR uploads a document (pending)', docId !== '' && sql(`select status from personnel_documents where id=${docId}`) === 'pending');
    check('File is not under public/', !sql(`select stored_path from personnel_documents where id=${docId}`).includes('public'));

    // 4. Another admin verifies; download is logged
    await ap.goto(`${BASE}/admin/documents?status=pending`);
    await Promise.all([ap.waitForNavigation(), ap.locator('tr', { hasText: serial }).locator('button[value=verified]').click()]);
    check('Approver verifies the document', sql(`select status from personnel_documents where id=${docId}`) === 'verified');
    await ap.goto(`${BASE}/admin/documents?status=verified`);
    const [dl] = await Promise.all([ap.waitForEvent('download'), ap.locator('tr', { hasText: serial }).locator('a:has-text("Download")').click()]);
    check('Download works and is logged', (await dl.suggestedFilename()).endsWith('.pdf') && sql(`select count(*) from document_access_logs where document_id=${docId} and action='download'`) === '1');

    // 5. Promotion through the UI
    await hr.goto(`${BASE}/admin/personnel/${pid}`);
    await hr.click('summary:has-text("Record a promotion")');
    await hr.selectOption('#to_rank_id', { label: 'Lieutenant Junior Grade (LTJG)' });
    await hr.fill('#effective_date', '2026-06-01');
    await Promise.all([hr.waitForNavigation(), hr.click('button:has-text("Record promotion")')]);
    check('Promotion recorded, rank updated', sql(`select r.code from personnel p join ranks r on r.id=p.rank_id where p.id=${pid}`) === 'LTJG' && sql(`select count(*) from promotions where personnel_id=${pid}`) === '1');

    // 6. Reports: roster shows the person; CSV downloads
    await hr.goto(`${BASE}/admin/reports?type=roster`);
    check('Roster report lists the person', await hr.locator('td', { hasText: serial }).count() === 1);
    const [csv] = await Promise.all([hr.waitForEvent('download'), hr.click('a:has-text("Export CSV")')]);
    const csvText = fs.readFileSync(await csv.path(), 'utf8');
    check('CSV export contains the person', csvText.includes(serial));

    // 7. MEPCOM-scoped admin sees the MEPCOM person; auditor cannot change anything
    const mep = await session(browser, 'mepcom-hr@demo.test');
    await mep.goto(`${BASE}/admin/personnel?q=${serial}`);
    check('MEPCOM HR sees the MEPCOM person', await mep.locator('td', { hasText: serial }).count() === 1);
    const au = await session(browser, 'auditor@demo.test');
    await au.goto(`${BASE}/admin/personnel/${pid}`);
    check('Auditor sees the record without change forms', await au.locator('text=Record an assignment').count() === 0 && await au.locator('text=Upload document').count() === 0);

    for (const [name, p] of [['hr', hr], ['approver', ap], ['mepcom', mep], ['auditor', au]]) {
      check(`No JavaScript errors (${name})`, p.errors.length === 0, p.errors.join(' | '));
    }
  } catch (e) {
    check('flow crashed', false, e.message.split('\n')[0]);
  } finally {
    await browser.close();
    for (const r of results) console.log(r.join('  '));
    process.exit(results.every(r => r[0] === 'PASS') ? 0 : 1);
  }
})();
