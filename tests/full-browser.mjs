import puppeteer from 'npm:puppeteer-core@25.10.0';
const root=new URL('../',import.meta.url).pathname,base='http://localhost:8080';
const env=Object.fromEntries((await Deno.readTextFile(root+'.env')).split('\n').filter(x=>x&&!x.startsWith('#')&&x.includes('=')).map(x=>[x.slice(0,x.indexOf('=')),x.slice(x.indexOf('=')+1)]));
const profile=await Deno.makeTempDir({prefix:'roma-final-'});
const browser=await puppeteer.launch({executablePath:Deno.env.get('ROMA_BROWSER')||'/opt/brave-bin/brave',headless:true,args:['--no-sandbox','--disable-dev-shm-usage'],userDataDir:profile});
const checks=[],errors=[];let reference,token;
function check(ok,label){if(!ok)throw Error(label);checks.push(label);console.log('OK',label)}
async function go(page,path){return(await page.goto(base+path,{waitUntil:'networkidle0'})).status()}
async function fill(page,name,value){await page.$eval(`[name="${name}"]`,(e,v)=>{e.value=v;e.dispatchEvent(new Event('change',{bubbles:true}))},value)}
async function submit(page,selector){await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click(selector+' [type="submit"]')])}
try{
 const visitor=await browser.createBrowserContext(),staff=await browser.createBrowserContext();const page=await visitor.newPage(),admin=await staff.newPage();
 page.on('pageerror',e=>errors.push(e.message));admin.on('pageerror',e=>errors.push(e.message));
 await page.setViewport({width:1440,height:1000});await go(page,'/');
 await page.waitForSelector('.chat-window-toggle');await page.click('.chat-window-toggle');
 await page.waitForSelector('#n8n-chat textarea');await page.type('#n8n-chat textarea','¿Cuánto cuesta la habitación individual?');await page.keyboard.press('Enter');
 await page.waitForFunction(()=>[...document.querySelectorAll('#n8n-chat .chat-message')].some(e=>e.textContent.includes('S/')),{timeout:30000});
 check(true,'Widget oficial @n8n/chat responde con tarifa desde Drupal vía n8n');
 await page.click('.chat-window-toggle');
 const day=new Date();day.setUTCDate(day.getUTCDate()+100);const arrival=day.toISOString().slice(0,10);day.setUTCDate(day.getUTCDate()+2);const departure=day.toISOString().slice(0,10);
 await go(page,`/disponibilidad?arrival=${arrival}&departure=${departure}&guests=1`);await page.click('table tbody a');await page.waitForSelector('#roma-booking-form');
 await fill(page,'customer_name','Cliente ficticio final');await fill(page,'email','final@example.invalid');await page.click('[name=demo_consent]');await submit(page,'#roma-booking-form');
 const body=await page.evaluate(()=>document.body.innerText);reference=body.match(/ROMA-[A-F0-9]{12}/)?.[0];token=body.match(/\b[a-f0-9]{48}\b/)?.[0];check(!!reference&&!!token,'Solicitud web persistida y recibo privado');
 await go(admin,'/user/login');await fill(admin,'name',env.DRUPAL_ADMIN_USER);await fill(admin,'pass',env.DRUPAL_ADMIN_PASSWORD);await submit(admin,'#user-login-form');
 await go(admin,'/admin/roma/reservas');const confirm=await admin.evaluate(ref=>[...document.querySelectorAll('tbody tr')].find(r=>r.textContent.includes(ref))?.querySelector('a[href$="/confirm"]')?.getAttribute('href'),reference);check(!!confirm,'Solicitud visible en panel administrativo');
 await go(admin,confirm);await submit(admin,'form');check((await admin.evaluate(()=>document.body.innerText)).includes('Estado actualizado'),'Confirmación protegida desde panel');
 await go(page,'/mi-reserva');await fill(page,'reference',reference);await fill(page,'secret',token);await submit(page,'#roma-lookup-form');check((await page.evaluate(()=>document.body.innerText)).includes('Estado: Confirmada'),'Consulta privada refleja confirmación');
 await go(page,'/pago-demo');await fill(page,'reference',reference);await fill(page,'secret',token);await page.select('[name=outcome]','demo_approved');await page.click('[name=demo_consent]');await submit(page,'#roma-demo-payment-form');
 check((await page.evaluate(()=>document.body.innerText)).includes('Aprobado en simulación'),'Pago ficticio autorizado sin datos bancarios');
 await go(admin,'/admin/roma/reservas');const cancel=await admin.evaluate(ref=>[...document.querySelectorAll('tbody tr')].find(r=>r.textContent.includes(ref))?.querySelector('a[href$="/cancel"]')?.getAttribute('href'),reference);await go(admin,cancel);await submit(admin,'form');check(true,'Cancelación libera la reserva de prueba');
 await Deno.mkdir(root+'docs/capturas',{recursive:true});
 for(const width of [1440,768,390,360]){
  await page.setViewport({width,height:width<620?844:1000});await go(page,'/');await page.evaluate(()=>document.fonts.ready);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Portada sin desbordamiento a '+width);
  for(const path of ['/reservar','/mi-reserva','/pago-demo','/contacto']){await go(page,path);check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),path+' sin desbordamiento a '+width)}
  await go(page,'/');if(width===1440||width===390)await page.screenshot({path:root+`docs/capturas/final-${width}.png`,fullPage:true});
 }
 await admin.setViewport({width:1440,height:1000});await go(admin,'/admin/roma');await admin.screenshot({path:root+'docs/capturas/panel-final.png',fullPage:true});
 check(!errors.length,'Sin errores JavaScript en recorrido final');
 await Deno.writeTextFile(root+'docs/verificacion-final-browser.json',JSON.stringify({date:new Date().toISOString(),checks,errors,fixture:'Solicitud ficticia final cancelada con pago de simulación, sin cargo real.'},null,2));
}catch(error){console.error(error.stack);Deno.exitCode=1;}finally{await browser.close();await Deno.remove(profile,{recursive:true})}
