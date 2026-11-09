// Read-only probe: visits pages per role on the dev app and dumps the visible
// headings, buttons, links and field labels (to verify labels before capture).
const { chromium } = require('playwright');
const lib = require('../../tests/e2e/lib');
const fs = require('fs');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const OUT = process.argv[2] || '.';
const PLAN = [
  ['guest', null, ['/', '/about', '/institutes/meptdi', '/contact', '/login', '/register', '/register?type=officer', '/register?type=non_officer', '/unit/login', '/admin/login', '/reenlistment/login']],
  ['member', ['/login', 'member@demo.test', 'Demo@12345'], ['/dashboard', '/profile', '/profile?edit=1', '/upload-documents', '/upload-201', '/applicant/operational-training', '/applicant/administrative-training']],
  ['unit', ['/unit/login', 'unit@demo.test', 'Demo@12345'], ['/unit/dashboard', '/unit/201-files']],
  ['m1', ['/unit/loginM1', 'm1@demo.test', 'Demo@12345'], ['/unit/dashboardM1']],
  ['admin', 'admin', ['/admin/dashboard', '/admin/hrmis', '/admin/personnel', '/admin/registrations', '/admin/registrations/create', '/admin/documents', '/admin/reports', '/admin/organization', '/admin/soi', '/admin/adjutant', '/admin/reenlistment', '/admin/unit-users', '/admin/accounts', '/admin/hr-analytics']],
  ['reenlistment', ['/reenlistment/login', 'reenlistment@demo.test', lib.ADMIN_PASSWORD], ['/admin/reenlistment']],
];
(async () => {
  const b = await chromium.launch();
  const dump = {};
  for (const [role, login, urls] of PLAN) {
    const ctx = await b.newContext({ viewport: { width: 1920, height: 1080 } });
    const p = await ctx.newPage();
    if (login === 'admin') await lib.adminLogin(p, BASE);
    else if (login) { await p.goto(BASE + login[0]); await p.fill('#email', login[1]); await p.fill('#password', login[2]); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]); }
    for (const u of urls) {
      const r = await p.goto(BASE + u, { waitUntil: 'networkidle' });
      const info = await p.evaluate(() => {
        const vis = e => !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
        const t = s => [...document.querySelectorAll(s)].filter(vis).map(e => e.innerText.trim().replace(/\s+/g, ' ')).filter(Boolean).slice(0, 60);
        return { title: document.title, h: t('h1,h2,h3'), buttons: t('button, input[type=submit]'), links: t('main a, .pcg-console__content a, .pcg-card a'), labels: t('label') };
      });
      dump[role + ' ' + u] = { status: r.status(), final: p.url().replace(BASE, ''), ...info };
      await p.screenshot({ path: `${OUT}/probe_${role}_${u.replace(/[^a-z0-9]+/gi, '_')}.png` });
    }
    await ctx.close();
  }
  fs.writeFileSync(`${OUT}/probe.json`, JSON.stringify(dump, null, 1));
  await b.close();
})();
