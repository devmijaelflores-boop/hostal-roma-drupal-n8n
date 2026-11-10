import puppeteer from 'npm:puppeteer-core@25.10.0';
const root = new URL('../', import.meta.url).pathname;
const base = 'http://localhost:8080';
const out = root + 'docs/capturas';
await Deno.mkdir(out, {recursive:true});
const profile = await Deno.makeTempDir({prefix:'roma-browser-'});
const browser = await puppeteer.launch({executablePath:Deno.env.get('ROMA_BROWSER') || '/opt/brave-bin/brave',headless:true,args:['--no-sandbox','--disable-dev-shm-usage'],userDataDir:profile});
const results=[],errors=[];
function check(condition,message){if(!condition)throw new Error(message);results.push(message);console.log('OK',message);}
try {
 const page=await browser.newPage();
 page.on('pageerror',e=>errors.push(e.message));
 page.on('response',r=>{if(r.status()>=400 && new URL(r.url()).origin===base)errors.push(`${r.status()} ${r.url()}`)});
 for (const width of [1440,1024,768,390,360]) {
  await page.setViewport({width,height:width<620?844:1000});
  const response=await page.goto(base,{waitUntil:'networkidle0'});
  await page.evaluate(()=>document.fonts.ready);
  await page.evaluate(async()=>{await Promise.all([...document.images].map(image=>{image.loading='eager';return image.decode().catch(()=>{});}));});
  check(response.status()===200,`Portada HTTP 200 a ${width}px`);
  const audit=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,images:[...document.images].filter(i=>!i.complete||!i.naturalWidth).map(i=>i.src),h1:document.querySelectorAll('h1').length,theme:!!document.querySelector('.hero'),rooms:document.querySelectorAll('.room-card').length}));
  check(audit.overflow<=1,`Sin desbordamiento horizontal a ${width}px`);
  check(!audit.images.length,`Imágenes cargadas a ${width}px`);
  check(audit.h1===1&&audit.theme&&audit.rooms===3,`Tema Drupal y tres categorías a ${width}px`);
  if(width===1440){await page.screenshot({path:out+'/escritorio.png',fullPage:true});await page.screenshot({path:out+'/portada.png'});}
  if(width===390)await page.screenshot({path:out+'/movil.png',fullPage:true});
 }
 await page.setViewport({width:390,height:844});
 await page.click('.menu-toggle');
 check(await page.$eval('.menu-toggle',e=>e.getAttribute('aria-expanded')==='true'),'Menú móvil abre');
 await page.click('#main-navigation a[href$="#habitaciones"]');
 check(await page.$eval('.menu-toggle',e=>e.getAttribute('aria-expanded')==='false'),'Menú móvil cierra al navegar');
 await page.click('[data-room-filter="1"]');
 check(await page.$$eval('.room-card',cards=>cards.filter(c=>!c.hidden).length===1),'Filtro individual muestra una categoría');
 await page.click('[data-room-filter="2"]');
 check(await page.$$eval('.room-card',cards=>cards.filter(c=>!c.hidden).length===2),'Filtro para dos muestra dos categorías');
 await page.click('[data-room-filter="all"]');
 await page.click('.room-details');
 check(await page.$$eval('.room-dialog',dialogs=>dialogs.some(d=>d.open)),'Detalle de habitación abre');
 await page.keyboard.press('Escape');
 check(await page.$$eval('.room-dialog',dialogs=>dialogs.every(d=>!d.open)),'Detalle cierra con Escape');
 await page.evaluate(()=>{document.querySelector('#arrival').value='2027-01-10';document.querySelector('#arrival').dispatchEvent(new Event('change'));document.querySelector('#departure').value='2027-01-13';document.querySelector('#departure').dispatchEvent(new Event('change'));});
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('.planner-submit')]);
 check(page.url().includes('/disponibilidad')&&!!await page.$('table'),'Planificador consulta disponibilidad en MariaDB');
 await page.goto(base,{waitUntil:'networkidle0'});
 await page.evaluate(()=>{document.querySelector('#arrival').value='2027-01-10';document.querySelector('#arrival').dispatchEvent(new Event('change'));});
 await page.evaluate(()=>{document.querySelector('#departure').value='2027-01-10';document.querySelector('#departure').dispatchEvent(new Event('change'));});
 check(await page.$eval('#departure',e=>!e.checkValidity()),'Planificador rechaza salida igual a llegada');
 await page.waitForSelector('.chat-window-toggle');
 await page.click('.chat-window-toggle');
 check(!!await page.$('#n8n-chat textarea'),'Widget oficial del asistente abre');
 await page.click('.chat-window-toggle');
 for(const viewport of [{width:1440,height:1000},{width:390,height:844}]){
  await page.setViewport(viewport);
  const response=await page.goto(base+'/user/login',{waitUntil:'networkidle0'});
  check(response.status()===200&&!!await page.$('#user-login-form'),`Formulario Drupal disponible a ${viewport.width}px`);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Login sin desbordamiento a ${viewport.width}px`);
 }
 check(!errors.length,'Sin errores JavaScript ni recursos HTTP fallidos');
 await Deno.writeTextFile(root+'docs/verificacion-fase-2.json',JSON.stringify({date:new Date().toISOString(),results,errors,screenshots:['capturas/escritorio.png','capturas/movil.png']},null,2));
}catch(error){console.error(error);console.error(errors);Deno.exitCode=1;}finally{await browser.close();await Deno.remove(profile,{recursive:true});}
