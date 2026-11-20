// Shared helpers for the e2e scripts.
const crypto = require('crypto');

// Dev-only values from app/Console/Commands/DevSeed.php
const ADMIN_PASSWORD = 'Admin@Demo12345';
const ADMIN_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

function base32Decode(s) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const c of s.replace(/=+$/, '')) bits += alphabet.indexOf(c).toString(2).padStart(5, '0');
  const bytes = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
}

function totp(secret, step = Math.floor(Date.now() / 1000 / 30)) {
  const buf = Buffer.alloc(8);
  buf.writeBigUInt64BE(BigInt(step));
  const h = crypto.createHmac('sha1', base32Decode(secret)).update(buf).digest();
  const o = h[19] & 0xf;
  const v = ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];
  return String(v % 1e6).padStart(6, '0');
}

// Codes cannot be reused: each sign-in takes the next unused time step.
let lastStep = 0;
async function adminLogin(page, base, email = 'admin@demo.test') {
  await page.goto(base + '/admin/login');
  await page.fill('#email', email);
  await page.fill('#password', ADMIN_PASSWORD);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  // password-only roles (re-enlistment admin) are signed in already
  if (!page.url().includes('/admin/two-factor/')) return;
  const now = Math.floor(Date.now() / 1000 / 30);
  const step = Math.max(now, lastStep + 1);
  if (step > now + 1) await page.waitForTimeout((step - 1 - now) * 30000 + 1000);
  lastStep = step;
  await page.fill('#code', totp(ADMIN_TOTP_SECRET, step));
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

// The in-page confirmation (pcg-ui.js pcgConfirm, <dialog class="pcg-confirm">) replaced
// the browser's confirm(): flows that accepted every native dialog accept these too.
async function autoConfirm(page) {
  await page.addInitScript(() => {
    new MutationObserver(() => {
      const ok = document.querySelector('dialog.pcg-confirm .pcg-confirm__actions button:last-child');
      if (ok && !ok.dataset.e2eClicked) { ok.dataset.e2eClicked = '1'; setTimeout(() => ok.click(), 50); }
    }).observe(document, { childList: true, subtree: true }); // documentElement may not exist yet
  });
}

// The log mailer writes to storage/logs/laravel.log, or laravel-YYYY-MM-DD.log with LOG_CHANNEL=daily: read the newest.
function mailLog() {
  const fs = require('fs'), path = require('path');
  const dir = path.resolve(__dirname, '../../storage/logs');
  const files = fs.readdirSync(dir).filter(f => /^(laravel|mail).*\.log$/.test(f))
    .map(f => [f, fs.statSync(path.join(dir, f)).mtimeMs]).sort((a, b) => b[1] - a[1]);
  return files.length ? fs.readFileSync(path.join(dir, files[0][0]), 'utf8') : '';
}

// Dev copy only (MAIL_MAILER=log): the latest temporary password emailed to an address,
// read from storage/logs/laravel.log, where the log mailer writes each email.
function emailedPassword(email) {
  const log = mailLog();
  const at = log.lastIndexOf('To: ' + email);
  if (at < 0) return null;
  const m = /Temporary password: ([A-Za-z0-9]+)/.exec(log.slice(at));
  return m ? m[1] : null;
}

// Unit and M1 checkers get an emailed sign-in code after the password (since 2026-11-20).
// Dev copy only (MAIL_MAILER=log): read the newest code sent to the address from laravel.log.
function emailedUnitCode(email) {
  const log = mailLog();
  const at = log.lastIndexOf('To: ' + email);
  if (at < 0) return null;
  const m = /Your sign-in code: (\d{6})/.exec(log.slice(at));
  return m ? m[1] : null;
}

// After the password form: if the second step is asked, enter the emailed code.
async function passUnitCode(page, email) {
  if (!page.url().includes('/unit/two-factor')) return;
  const code = emailedUnitCode(email);
  if (!code) throw new Error('no unit sign-in code in storage/logs/laravel.log for ' + email + ' (MAIL_MAILER=log?)');
  await page.fill('#code', code);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

module.exports = { totp, adminLogin, autoConfirm, emailedPassword, emailedUnitCode, passUnitCode, ADMIN_PASSWORD, ADMIN_TOTP_SECRET };
