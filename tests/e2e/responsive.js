// Checks every page at phone, tablet and desktop sizes (development database only).
// usage: node tests/e2e/responsive.js [outdir-for-screenshots]
// Reports, per page and size: horizontal page scroll, the elements sticking out
// past the screen edge (scrollable tables are fine), form fields under 16px
// on phones (iOS zooms into them) and tap targets under 24px (WCAG 2.2 AA) below 1024px.
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const out = process.argv[2];
const SIZES = [[320, 640], [375, 812], [414, 896], [768, 1024], [1024, 768], [1280, 800], [1920, 1080]];
const SHOT_SIZES = ['375x812', '768x1024'];
const roles = {
  guest: { pages: ['/', '/about', '/contact', '/institutes/meptdi', '/login', '/register?type=officer', '/register?type=non_officer', '/admin/login', '/reenlistment/login', '/unit/login', '/unit/loginM1', '/osaert/verify', '/osaert/verify/1'] },
  member: { login: ['/login', 'member@demo.test', 'Demo@12345'], pages: ['/dashboard', '/profile', '/upload-documents', '/documents/upload', '/upload-201', '/generate-soi', '/applicant/administrative-training', '/applicant/operational-training'] },
  unit: { login: ['/unit/login', 'unit@demo.test', 'Demo@12345'], pages: ['/unit/dashboard'] },
  m1: { login: ['/unit/loginM1', 'm1@demo.test', 'Demo@12345'], pages: ['/unit/dashboardM1', '/unit/search-serial/DEMO-0001', '/unit/201-files', '/unit/201-files/DEMO-0001'] },
  reenlist: { adminLogin: 'reenlistment@demo.test', pages: ['/admin/reenlistment', '/admin/reenlistment?window=overdue', '/admin/reenlistment?window=no_ete', '/admin/reenlistment?window=all&status=all', '/admin/reenlistment/2'] },
  admin: { adminLogin: 'admin@demo.test', pages: ['/admin/reenlistment', '/admin/dashboard', '/admin/unit-users', '/admin/hr-analytics', '/administrative-tracks', '/personnel-administrative-training', '/operation-tracks', '/personnel/operational-training', '/admin/training-applications', '/admin/training-courses', '/admin/training-courses/create', '/admin/training-courses/1/edit', '/training-courses/1/students', '/training-courses/1/students/create', '/admin/osaert', '/admin/osaert/certificates', '/admin/accounts', '/admin/accounts/audit-log', '/admin/messages', '/admin/hrmis', '/admin/personnel', '/admin/personnel/1', '/admin/personnel/1/edit', '/admin/registrations', '/admin/registrations/create', '/admin/documents', '/admin/reports', '/admin/reports?type=roster', '/admin/reports?type=by_unit', '/admin/organization'] },
};

function measure([touch, phone]) {
  const vw = document.documentElement.clientWidth;
  const scrollsX = el => { for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) { const s = getComputedStyle(p); if (/(auto|scroll|hidden|clip)/.test(s.overflowX)) return true; } return false; };
  const visible = el => { const s = getComputedStyle(el); const r = el.getBoundingClientRect(); return s.visibility !== 'hidden' && s.display !== 'none' && r.width > 0 && r.height > 0; };
  const name = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
  const over = [];
  for (const el of document.body.querySelectorAll('*')) {
    if (!visible(el) || getComputedStyle(el).position === 'fixed') continue;
    const r = el.getBoundingClientRect();
    if (r.right > vw + 1 && !scrollsX(el)) over.push(name(el) + ` (right ${Math.round(r.right)})`);
  }
  // keep only the outermost offenders
  const small = touch ? [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [data-on-click]')].filter(visible).filter(el => { const r = el.getBoundingClientRect(); return (r.height < 24 || r.width < 24) && !el.closest('p, li, td, label, small'); }).map(name) : [];
  const zoomFields = phone ? [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file]), select, textarea')].filter(visible).filter(el => parseFloat(getComputedStyle(el).fontSize) < 16).map(name) : [];
  return {
    pageScroll: document.documentElement.scrollWidth - vw,
    viewportMeta: !!document.querySelector('meta[name=viewport]'),
    over: [...new Set(over)].slice(0, 6), overCount: over.length,
    small: [...new Set(small)].slice(0, 5), smallCount: small.length,
    zoom: [...new Set(zoomFields)].slice(0, 4), zoomCount: zoomFields.length,
  };
}

(async () => {
  const browser = await chromium.launch();
  const rows = [];
  for (const [role, cfg] of Object.entries(roles)) {
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 800 }, acceptDownloads: true });
    const page = await ctx.newPage();
    if (cfg.adminLogin) await require('./lib').adminLogin(page, BASE, cfg.adminLogin);
    if (cfg.login) {
      await page.goto(BASE + cfg.login[0]);
      await page.fill('#email', cfg.login[1]); await page.fill('#password', cfg.login[2]);
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    }
    for (const url of cfg.pages) {
      for (const [w, h] of SIZES) {
        await page.setViewportSize({ width: w, height: h });
        let status = 'ERR';
        try { const r = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 20000 }); status = r ? r.status() : 'ERR'; } catch (e) { status = e.message.split('\n')[0]; }
        await page.waitForTimeout(150);
        const m = await page.evaluate(measure, [w < 1024, w < 768]).catch(e => ({ error: e.message }));
        rows.push({ role, url, size: `${w}x${h}`, status, ...m });
        if (out && SHOT_SIZES.includes(`${w}x${h}`)) await page.screenshot({ path: `${out}/${role}${url.replace(/[^a-z0-9]+/gi, '_')}_${w}.png`, fullPage: true }).catch(() => {});
      }
    }
    await ctx.close();
  }
  await browser.close();
  let problems = 0;
  for (const r of rows) {
    const issues = [];
    if (r.status !== 200) issues.push(`HTTP ${r.status}`);
    if (r.error) issues.push(r.error);
    if (r.viewportMeta === false) issues.push('no viewport meta');
    if (r.pageScroll > 1) issues.push(`page scrolls sideways by ${r.pageScroll}px: ${r.over.join(', ')}`);
    else if (r.overCount) issues.push(`content cut off at the edge: ${r.over.join(', ')}`);
    if (r.zoomCount) issues.push(`${r.zoomCount} field(s) under 16px (iOS zoom): ${r.zoom.join(', ')}`);
    if (r.smallCount) issues.push(`${r.smallCount} small tap target(s): ${r.small.join(', ')}`);
    if (issues.length) { problems++; console.log(`XX [${r.role}] ${r.url} @${r.size}\n     ${issues.join('\n     ')}`); }
  }
  console.log(`\n${rows.length} page/size checks, ${problems} with findings`);
  if (process.env.JSON_OUT) fs.writeFileSync(process.env.JSON_OUT, JSON.stringify(rows, null, 1));
})();
