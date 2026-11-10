import puppeteer from 'npm:puppeteer-core@25.10.0';

const root = new URL('../', import.meta.url).pathname;
const base = 'http://localhost:8080';
const env = Object.fromEntries((await Deno.readTextFile(root + '.env')).split('\n').filter((line) => line && !line.startsWith('#') && line.includes('=')).map((line) => [line.slice(0, line.indexOf('=')), line.slice(line.indexOf('=') + 1)]));
const results = [], errors = [];
const check = (ok, name) => { if (!ok) throw Error(name); results.push(name); console.log('OK', name); };
async function php(code) {
  const result = await new Deno.Command('docker', { cwd: root, args: ['compose', 'exec', '-T', 'drupal', 'vendor/bin/drush', 'php:eval', code], stdout: 'piped', stderr: 'piped' }).output();
  if (!result.success) throw Error(new TextDecoder().decode(result.stderr));
  return JSON.parse(new TextDecoder().decode(result.stdout));
}
const fixture = await php("$h=\\Drupal::service('roma_booking.hotel');$a=(new \\DateTimeImmutable($h->today()))->modify('+75 days')->format('Y-m-d');$d=(new \\DateTimeImmutable($a))->modify('+2 days')->format('Y-m-d');echo json_encode(['arrival'=>$a,'departure'=>$d,'rooms'=>array_slice($h->availability($a,$d,1),0,2)]);");
const profile = await Deno.makeTempDir({ prefix: 'roma-commerce-browser-' });
const browser = await puppeteer.launch({ executablePath: '/opt/brave-bin/brave', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'], userDataDir: profile });
const context = await browser.createBrowserContext(), page = await context.newPage();
page.on('pageerror', (e) => errors.push(e.message));
const go = async (path) => (await page.goto(base + path, { waitUntil: 'networkidle0' })).status();
const text = () => page.evaluate(() => document.body.innerText);
const fill = async (name, value) => page.$eval(`[name="${name}"]`, (el, v) => { el.value = v; }, value);
const submit = async (selector = 'form input[type="submit"]') => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click(selector)]);
const next = () => submit('form[id^="commerce-checkout-flow"] .form-actions input[type="submit"]');
const add = async (room) => {
  await go(`/tienda/agregar?room=${room.id}&arrival=${fixture.arrival}&departure=${fixture.departure}&guests=1`);
  await submit('#roma-add-stay-form input[type="submit"]');
};
let orderId;
const stored = () => php(`$o=\\Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged(${Number(orderId)});$l=\\Drupal::database()->select('roma_shop_booking','l')->fields('l')->condition('order_id',${Number(orderId)})->execute()->fetchAll(\\PDO::FETCH_ASSOC);echo json_encode(['state'=>$o->getState()->getId(),'payments'=>(int)\\Drupal::database()->select('commerce_payment','p')->condition('order_id',${Number(orderId)})->countQuery()->execute()->fetchField(),'links'=>count($l),'booking_states'=>array_map(fn($r)=>\\Drupal::service('roma_booking.hotel')->reservation((int)$r['reservation_id'])['status'],$l)]);`);
async function tick() {
  const response = await fetch('http://localhost:5678/webhook/roma-automation', { method: 'POST', headers: { 'X-ROMA-Webhook': env.ROMA_N8N_WEBHOOK_TOKEN, 'Content-Type': 'application/json' }, body: '{}' });
  const body = await response.json();
  check(response.ok && !body.error, 'Workflow n8n procesa avisos y sincroniza pedidos');
}
try {
  await page.setViewport({ width: 1440, height: 1000 });
  check(await go('/tienda') === 200 && (await page.$$('.shop-room-card')).length >= 2, 'Catálogo de tienda muestra habitaciones del inventario');
  await Deno.mkdir(root + 'docs/capturas', { recursive: true });
  await page.screenshot({ path: root + 'docs/capturas/tienda-commerce.png', fullPage: true });
  await go(`/tienda/agregar?room=${fixture.rooms[0].id}&arrival=${fixture.arrival}&departure=${fixture.departure}&guests=1`);
  await fill('_roma_csrf', 'MANIPULADO'); await submit('#roma-add-stay-form input[type="submit"]');
  check((await text()).includes('La sesión del formulario no es válida'), 'Agregar al carrito rechaza CSRF manipulado');
  await add(fixture.rooms[0]);
  check(page.url().includes('/carrito') && (await page.$$('.shop-cart-item')).length === 1, 'Formulario agrega una estadía al carrito de Commerce');
  await add(fixture.rooms[0]);
  check((await text()).includes('ya está en tu carrito'), 'La misma habitación no se duplica en fechas superpuestas');
  await add(fixture.rooms[1]);
  check((await page.$$('.shop-cart-item')).length === 2, 'Carrito admite varias habitaciones en un pedido');
  check((await text()).includes(fixture.arrival.split('-').reverse().join('/')) && (await text()).includes('huésped(es)'), 'Carrito muestra fechas peruanas y huéspedes de cada estadía');
  await go('/cart');
  check(page.url().endsWith('/carrito') && (await page.$$('.shop-cart-item')).length === 2, 'La ruta alternativa de Commerce dirige al carrito protegido con fechas');
  const remove = await page.$('.shop-cart-item input[type="submit"]');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), remove.click()]);
  check((await page.$$('.shop-cart-item')).length === 1, 'Quitar estadía usa POST y actualiza el carrito');
  await add(fixture.rooms[0]);
  await page.setViewport({ width: 390, height: 844 });
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Carrito usable a 390 píxeles sin desbordamiento');
  await page.screenshot({ path: root + 'docs/capturas/carrito-commerce-movil.png', fullPage: true });
  await page.setViewport({ width: 1440, height: 1000 });
  await submit('#roma-cart-form .form-actions input[type="submit"]');
  orderId = Number(page.url().match(/\/checkout\/(\d+)/)?.[1]);
  check(!!orderId && page.url().includes('/order_information'), 'Checkout oficial de Drupal Commerce abre el paso de datos');
  const stranger = await browser.createBrowserContext(), privatePage = await stranger.newPage();
  const forbidden = await privatePage.goto(`${base}/checkout/${orderId}/complete`, { waitUntil: 'networkidle0' });
  check(forbidden.status() === 403, 'Otra sesión no puede acceder al pedido ni al recibo');
  await privatePage.goto(base + '/carrito', { waitUntil: 'networkidle0' });
  check((await privatePage.evaluate(() => document.body.innerText)).includes('Tu carrito está vacío'), 'El carrito está aislado entre sesiones');
  await go(`/checkout/${orderId}/complete`);
  check(page.url().includes('/order_information') && (await stored()).payments === 0, 'No se puede saltar al recibo sin completar el checkout');
  await fill('roma_guest[name]', 'Cliente ficticio tienda académica');
  await fill('roma_guest[email]', 'shop-browser@example.invalid');
  await fill('roma_guest[phone]', 'letras'); await page.click('[name="roma_guest[consent]"]');
  await next();
  check((await text()).includes('no admite letras'), 'Checkout muestra un teléfono inválido junto al campo');
  await fill('roma_guest[phone]', ''); await next();
  check(page.url().includes('/review'), 'Datos válidos llevan a revisar el pedido');
  await next();
  check(page.url().includes('/payment'), 'La revisión lleva al pago local de prueba');
  for (const [outcome, message] of [['declined', 'Pago de prueba rechazado'], ['cancelled', 'Operación de prueba cancelada']]) {
    await page.click(`[name="roma_payment[outcome]"][value="${outcome}"]`); await next();
    const state = await stored();
    check((await text()).includes(message) && state.payments === 0 && state.links === 0, `${outcome}: aviso visible sin pagos ni reservas creadas`);
  }
  await page.click('[name="roma_payment[outcome]"][value="approved"]'); await next();
  check(page.url().includes('/complete') && (await text()).includes('Pedido de demostración registrado'), 'Aprobación de prueba completa el pedido y muestra el recibo');
  let state = await stored();
  check(state.payments === 1 && state.links === 2 && state.booking_states.every((s) => s === 'pending') && state.state === 'awaiting_confirmation', 'MariaDB guarda un pago test y dos solicitudes pendientes, sin confirmar habitaciones');
  const references = await page.$eval('.hotel-receipt', (el) => el.textContent.match(/ROMA-[A-F0-9]{12}/g) || []);
  check(references.length === 2 && await page.$eval('.hotel-receipt', (el) => (el.textContent.match(/\b[a-f0-9]{48}\b/g) || []).length === 2), 'Recibo privado entrega un código y una clave por solicitud');
  await page.reload({ waitUntil: 'networkidle0' });
  state = await stored(); check(state.payments === 1 && state.links === 2, 'Recargar el recibo no duplica el pedido');
  // Comprueba las páginas reales antes de cancelar: un pedido cancelado
  // bloquea el checkout y no sirve para evaluar su diseño responsive.
  for (const width of [1440, 768, 390]) {
    await page.setViewport({ width, height: 1000 });
    for (const path of ['/tienda', '/carrito', `/checkout/${orderId}/complete`]) {
      const status = await go(path);
      check(status === 200 && await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${path.split('/')[1]} sin desbordamiento a ${width}`);
    }
  }

  const adminContext = await browser.createBrowserContext(), admin = await adminContext.newPage();
  await admin.goto(base + '/user/login', { waitUntil: 'networkidle0' });
  await admin.type('[name="name"]', env.DRUPAL_ADMIN_USER); await admin.type('[name="pass"]', env.DRUPAL_ADMIN_PASSWORD);
  await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle0' }), admin.click('#user-login-form [type="submit"]')]);
  check((await admin.goto(base + '/admin/roma/pedidos', { waitUntil: 'networkidle0' })).status() === 200, 'Administrador accede al panel protegido de pedidos');
  for (const action of ['confirm', 'cancel']) {
    for (const reference of references) {
      await admin.goto(base + '/admin/roma/reservas', { waitUntil: 'networkidle0' });
      const url = await admin.evaluate(({ ref, act }) => [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(ref))?.querySelector(`a[href$="/${act}"]`)?.getAttribute('href'), { ref: reference, act: action });
      if (!url) throw Error('Acción administrativa no encontrada');
      await admin.goto(base + url, { waitUntil: 'networkidle0' });
      await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle0' }), admin.click('form [type="submit"]')]);
    }
    await tick();
    check((await stored()).state === (action === 'confirm' ? 'completed' : 'canceled'), `n8n refleja ${action} de las reservas en el pedido Commerce`);
  }
  check(errors.length === 0, 'Sin errores JavaScript en tienda, carrito y checkout');
  await Deno.writeTextFile(root + 'docs/verificacion-commerce-browser.json', JSON.stringify({ date: new Date().toISOString(), checks: results, errors, fixture: 'Pedido de prueba cancelado y un pago en modo test; no se publican claves de consulta.' }, null, 2));
} finally {
  if (orderId) await php(`$db=\\Drupal::database();$ids=$db->select('roma_shop_booking','l')->fields('l',['reservation_id'])->condition('order_id',${Number(orderId)})->execute()->fetchCol();foreach($ids as $id){$h=\\Drupal::service('roma_booking.hotel');if($h->reservation((int)$id)['status']!=='cancelled')$h->transition((int)$id,'cancelled',1);}\\Drupal::service('roma_shop.store')->reconcile();echo 'true';`);
  await browser.close(); await Deno.remove(profile, { recursive: true });
}
