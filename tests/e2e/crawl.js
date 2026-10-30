// Logs in as each role and visits every reachable GET page.
// usage: node crawl.js <outdir> [shots]
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const LOG = require('path').resolve(__dirname, '../../storage/logs/laravel.log');
const out = process.argv[2];
const shots = process.argv[3] === 'shots';
const roles = {
  guest: { pages: ['/', '/about', '/contact', '/institutes/meptdi', '/login', '/register?type=officer', '/register?type=non_officer', '/admin/login', '/unit/login', '/unit/loginM1', '/osaert/verify', '/osaert/verify/1', '/osaert/verify?certificate=OSAERT-2025-000001'] },
  member: { login: ['/login', 'member@demo.test', 'Demo@12345'], pages: ['/dashboard', '/profile', '/upload-documents', '/documents/upload', '/upload-201', '/generate-soi', '/applicant/administrative-training', '/applicant/operational-training', '/files/documents_toupload/documents/demo_DEMO-0001_nbi_clearance.pdf', '/files/201_files/soi/demo_DEMO-0001_soi.pdf'] },
  unit: { login: ['/unit/login', 'unit@demo.test', 'Demo@12345'], pages: ['/unit/dashboard', '/files/documents_toupload/documents/demo_DEMO-0001_nbi_clearance.pdf'] },
  m1: { login: ['/unit/loginM1', 'm1@demo.test', 'Demo@12345'], pages: ['/unit/dashboardM1', '/unit/search-serial/DEMO-0001', '/unit/201-files', '/unit/201-files/DEMO-0001', '/files/disciplinary_actions/disciplinary_actions/demo_case.pdf'] },
  admin: { adminLogin: 'admin@demo.test', pages: ['/admin/dashboard', '/admin/unit-users', '/admin/hr-analytics', '/administrative-tracks', '/personnel-administrative-training', '/operation-tracks', '/personnel/operational-training', '/admin/training-applications', '/admin/training-courses', '/admin/training-courses/create', '/admin/training-courses/1/edit', '/training-courses/1/students', '/training-courses/1/students/create', '/training-courses/1/students/csv-template', '/admin/osaert', '/admin/osaert/template', '/admin/osaert/certificates', '/admin/accounts', '/admin/accounts/audit-log', '/admin/messages'] },
  lmsadmin: { adminLogin: 'lms@demo.test', pages: ['/admin/training-courses', '/admin/osaert', '/admin/training-applications'] },
};
const only = process.env.ROLES ? process.env.ROLES.split(',') : null;
(async () => {
  const browser = await chromium.launch();
  const results = [];
  for (const [role, cfg] of Object.entries(roles)) {
    if (only && !only.includes(role)) continue;
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 800 }, acceptDownloads: true });
    const page = await ctx.newPage();
    let jsErrors = [];
    page.on('pageerror', e => jsErrors.push(e.message.split('\n')[0]));
    page.on('console', m => { if (m.type() === 'error') jsErrors.push(m.text().split('\n')[0]); });
    if (cfg.adminLogin) {
      await require('./lib').adminLogin(page, BASE, cfg.adminLogin);
      console.log(`[${role}] login -> ${page.url().replace(BASE, '')}`);
    }
    if (cfg.login) {
      await page.goto(BASE + cfg.login[0]);
      await page.fill('#email', cfg.login[1]); await page.fill('#password', cfg.login[2]);
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
      console.log(`[${role}] login -> ${page.url().replace(BASE, '')}`);
    }
    for (const url of cfg.pages) {
      fs.writeFileSync(LOG, '');
      jsErrors = [];
      let status = 'ERR', err = '';
      try {
        const resp = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 20000 });
        status = resp ? resp.status() : 'download';
        await page.waitForTimeout(300);
      } catch (e) { err = e.message.split('\n')[0]; if (/Download is starting/.test(err)) { status = 'download'; err = ''; } }
      const log = fs.readFileSync(LOG, 'utf8');
      const phpErr = (log.match(/local\.ERROR: ([^\n]{0,240})/) || [])[1] || '';
      const finalUrl = page.url().replace(BASE, '');
      results.push({ role, url, status, finalUrl, phpErr, js: [...new Set(jsErrors)].filter(e => !/status of 404/.test(e)).slice(0, 3), err });
      if (shots && status === 200) await page.screenshot({ path: `${out}/${role}${url.replace(/[^a-z0-9]+/gi, '_')}.png` }).catch(() => {});
    }
    await ctx.close();
  }
  await browser.close();
  for (const r of results) {
    const bad = (r.status !== 200 && r.status !== 'download') || r.phpErr || r.js.length || r.err;
    console.log(`${bad ? 'XX' : 'ok'} [${r.role}] ${r.url} -> ${r.status}${r.finalUrl !== r.url ? ' (' + r.finalUrl + ')' : ''}${r.phpErr ? '\n     PHP: ' + r.phpErr : ''}${r.js.length ? '\n     JS: ' + r.js.join(' | ') : ''}${r.err ? '\n     ' + r.err : ''}`);
  }
})();
