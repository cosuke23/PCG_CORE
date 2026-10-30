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
  const now = Math.floor(Date.now() / 1000 / 30);
  const step = Math.max(now, lastStep + 1);
  if (step > now + 1) await page.waitForTimeout((step - 1 - now) * 30000 + 1000);
  lastStep = step;
  await page.fill('#code', totp(ADMIN_TOTP_SECRET, step));
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

module.exports = { totp, adminLogin, ADMIN_PASSWORD, ADMIN_TOTP_SECRET };
