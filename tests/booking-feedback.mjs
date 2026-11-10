import puppeteer from 'npm:puppeteer-core@25.10.0';

const root = new URL('../', import.meta.url).pathname;
const base = 'http://localhost:8080';
const email = `feedback-${crypto.randomUUID()}@example.invalid`;
const results = [];
const check = (ok, message) => {
  if (!ok) throw Error(message);
  results.push(message);
  console.log('OK', message);
};
async function php(code) {
  const result = await new Deno.Command('docker', {
    cwd: root,
    args: ['compose', 'exec', '-T', 'drupal', 'vendor/bin/drush', 'php:eval', code],
    stdout: 'piped', stderr: 'piped',
  }).output();
  if (!result.success) throw Error(new TextDecoder().decode(result.stderr));
  return JSON.parse(new TextDecoder().decode(result.stdout));
}
const rows = () => php(`echo json_encode(\\Drupal::database()->select('roma_reservation', 'b')->fields('b', ['id', 'status', 'phone'])->condition('email', '${email}')->execute()->fetchAll(\\PDO::FETCH_ASSOC));`);
const fixture = await php("$h=\\Drupal::service('roma_booking.hotel'); $a=(new \\DateTimeImmutable($h->today()))->modify('+90 days')->format('Y-m-d'); $d=(new \\DateTimeImmutable($a))->modify('+4 days')->format('Y-m-d'); $r=$h->availability($a,$d,2); if (!$r) throw new \\RuntimeException('No hay habitación para esta prueba'); echo json_encode(['arrival'=>$a,'departure'=>$d,'room'=>$r[0]['id'],'capacity'=>(int)$r[0]['capacity']]);");
const profile = await Deno.makeTempDir({ prefix: 'roma-feedback-' });
const browser = await puppeteer.launch({
  executablePath: '/opt/brave-bin/brave', headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'], userDataDir: profile,
});
const errors = [];
const page = await browser.newPage();
page.on('pageerror', (error) => errors.push(error.message));
const fill = async (name, value) => page.$eval(`[name="${name}"]`, (el, text) => { el.value = text; }, value);
const submit = async () => {
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.click('#roma-booking-form [type="submit"]'),
  ]);
};
const visibleFocus = async (selector) => {
  await page.waitForFunction((s) => document.activeElement === document.querySelector(s), {}, selector);
  return page.$eval(selector, (el) => {
    const rect = el.getBoundingClientRect();
    return rect.top >= 0 && rect.bottom <= innerHeight;
  });
};
try {
  await page.setViewport({ width: 1440, height: 1000 });
  await page.goto(`${base}/reservar?room=${fixture.room}&arrival=${fixture.arrival}&departure=${fixture.departure}&guests=2`, { waitUntil: 'networkidle0' });
  await fill('customer_name', 'Cliente ficticio revisión de formulario');
  await fill('email', email);
  await fill('phone', 'gaaaaaaaaa');
  await page.click('[name="demo_consent"]');
  await submit();
  check(await page.$eval('.form-item-phone .form-item--error-message', (el) => el.textContent.includes('no admite letras')), 'Teléfono inválido muestra explicación junto al campo');
  check(await page.$eval('[name="phone"]', (el) => el.getAttribute('aria-invalid') === 'true') && !await page.$eval('[name="room_id"]', (el) => el.getAttribute('aria-invalid') === 'true'), 'El error señala el teléfono y conserva la habitación válida');
  check(await visibleFocus('[name="phone"]'), 'El teléfono recibe el foco y queda visible tras enviar desde el final');
  check(await page.$eval('[name="customer_name"]', (el) => el.value === 'Cliente ficticio revisión de formulario'), 'Los datos ingresados se conservan después del rechazo');
  check((await rows()).length === 0, 'El teléfono inválido no crea solicitudes en MariaDB');
  await Deno.mkdir(root + 'docs/capturas', { recursive: true });
  await page.screenshot({ path: root + 'docs/capturas/reserva-error-telefono.png' });
  await page.setViewport({ width: 390, height: 844 });
  await fill('phone', '');
  await fill('departure', fixture.arrival);
  await fill('guests', String(fixture.capacity + 1));
  await fill('customer_name', 'A');
  await submit();
  for (const name of ['departure', 'guests', 'customer_name']) {
    check(await page.$eval(`[name="${name}"]`, (el) => el.getAttribute('aria-invalid') === 'true' && !!el.closest('.form-item').querySelector('.form-item--error-message')), `Validación del servidor asociada a ${name}`);
  }
  check(await visibleFocus('[name="departure"]'), 'En móvil se enfoca y muestra el primer campo con error');
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Los errores se adaptan a 390 píxeles sin desbordamiento');
  check((await rows()).length === 0, 'Fechas, nombre y ocupación inválidos no crean solicitudes');
  await fill('departure', fixture.departure);
  await fill('guests', '2');
  await fill('customer_name', 'Cliente ficticio revisión de formulario');
  await submit();
  check(await page.$eval('.hotel-receipt', (el) => el.textContent.includes('Solicitud de prueba registrada') && el.textContent.includes('Estado: pendiente')), 'Al corregir los campos aparece el recibo de solicitud pendiente');
  check(await visibleFocus('.hotel-receipt'), 'El recibo recibe el foco y queda visible en móvil');
  check(await page.$eval('.hotel-receipt', (el) => /ROMA-[A-F0-9]{12}/.test(el.textContent) && /\b[a-f0-9]{48}\b/.test(el.textContent)), 'El recibo entrega código y clave privada sin publicarlos en la evidencia');
  const saved = await rows();
  check(saved.length === 1 && saved[0].status === 'pending' && saved[0].phone === '', 'MariaDB contiene una sola solicitud pendiente con teléfono opcional vacío');
  check(errors.length === 0, 'Sin errores JavaScript durante el recorrido');
  await Deno.writeTextFile(root + 'docs/verificacion-reserva-feedback.json', JSON.stringify({ date: new Date().toISOString(), results, javascriptErrors: errors, fixture: 'Una solicitud ficticia registrada y cancelada después de la comprobación; no se modifican solicitudes ajenas.' }, null, 2));
} finally {
  // Cancel only the fixture generated by this execution, preserving its audit.
  for (const row of await rows()) {
    if (row.status === 'pending') await php(`\\Drupal::service('roma_booking.hotel')->transition(${Number(row.id)}, 'cancelled', 0); echo 'true';`);
  }
  await browser.close();
  await Deno.remove(profile, { recursive: true });
}
