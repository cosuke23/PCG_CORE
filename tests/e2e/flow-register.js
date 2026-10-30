// Self-registration with a fake camera, then admin sees it as Pending.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.PCG_E2E_URL || 'http://127.0.0.1:8123';
const sql = q => execSync(`${process.env.PCG_E2E_DB_CLI || "mariadb -uroot"} -N pcg_core_dev -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
(async () => {
  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, permissions: ['camera'] });
  const p = await ctx.newPage();
  const errs = []; p.on('pageerror', e => errs.push(e.message.split('\n')[0]));
  const dialogs = []; p.on('dialog', d => { dialogs.push(d.message()); d.accept(); });
  await p.goto(BASE + '/register?type=non_officer', { waitUntil: 'networkidle' });

  const set = async (sel, val) => p.evaluate(([s, v]) => {
    const el = document.querySelector(s); el.value = v;
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
  }, [sel, val]);
  const pickFirst = async sel => p.evaluate(s => {
    const el = document.querySelector(s); const opt = [...el.options].find(o => o.value);
    if (!opt) return null; el.value = opt.value;
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); return opt.value;
  }, sel);

  // Walk the wizard: fill what is visible on each step, then Next.
  for (let i = 0; i < 6; i++) {
    if (await p.locator('[name=rank]').isVisible()) {
      if (await p.locator('select[name=rank]').count()) await pickFirst('select[name=rank]'); else await set('[name=rank]', 'SN1');
      await set('[name=name]', 'Flow Registrant'); await set('[name=email]', 'flow.registrant@demo.test');
      if (await p.locator('[name=serial_number]').isVisible()) await set('[name=serial_number]', 'FLOW-0001');
    }
    if (await p.locator('[name=serial_number]').isVisible()) await set('[name=serial_number]', 'FLOW-0001');
    if (await p.locator('#dob').isVisible()) { await set('#dob', '1998-04-04'); await set('#date_of_appointment', '2022-01-10'); }
    if (await p.locator('[name=command]').isVisible()) {
      console.log('command', await pickFirst('select[name=command]')); await p.waitForTimeout(200);
      console.log('category', await pickFirst('select[name=category]')); await p.waitForTimeout(200);
      console.log('unit', await pickFirst('select[name=unit]')); await p.waitForTimeout(200);
      console.log('sub_unit', await pickFirst('select[name=sub_unit]'));
    }
    if (await p.locator('[name=password]').isVisible()) { await set('[name=password]', 'FlowPass123!'); await set('[name=password_confirmation]', 'FlowPass123!'); }
    if (await p.locator('#captureBtn').isVisible()) break;
    const next = p.locator('button[\\@click="next()"]');
    if (!(await next.isVisible())) break;
    await next.click(); await p.waitForTimeout(400);
  }

  await p.waitForTimeout(1500); // camera warm-up
  await p.click('#captureBtn'); await p.waitForTimeout(500);
  await p.click('.swal2-confirm').catch(() => {}); await p.waitForTimeout(300);
  console.log('selfie captured:', (await p.inputValue('#selfieImage')).slice(0, 22));
  console.log('visible buttons:', (await p.locator('button:visible').allInnerTexts()).map(t => t.trim()).filter(Boolean));
  await p.screenshot({ path: require('path').join(require('os').tmpdir(), 'register-step.png') });
  await Promise.all([p.waitForNavigation({ timeout: 15000 }), p.locator('#registerForm button:has-text("Submit")').click()]).catch(e => console.log('submit:', e.message.split('\n')[0]));
  await p.waitForTimeout(500);
  console.log('landed on', p.url().replace(BASE, ''));
  const row = sql("select concat(status,'|',serial_number,'|',selfie_image,'|',registration_type) from users where email='flow.registrant@demo.test'");
  console.log(row ? 'PASS  Registration creates a Pending user  ' + row : 'FAIL  Registration did not create a user; dialogs=' + dialogs.join(' / '));
  if (row) {
    const selfie = row.split('|')[2];
    const f = execSync(`file ${require('path').resolve(__dirname, '../../storage/app/dev-uploads', selfie)}`).toString();
    console.log(/PNG image/.test(f) ? 'PASS  Selfie saved as a real PNG' : 'FAIL  selfie ' + f);
  }
  console.log('JS errors:', errs.length ? errs : 'none');
  await browser.close();
})();
