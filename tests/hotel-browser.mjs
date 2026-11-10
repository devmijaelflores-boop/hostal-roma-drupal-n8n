import puppeteer from 'npm:puppeteer-core@25.10.0';
const root=new URL('../',import.meta.url).pathname,base='http://localhost:8080';
const env=Object.fromEntries((await Deno.readTextFile(root+'.env')).split('\n').filter(x=>x&&!x.startsWith('#')&&x.includes('=')).map(x=>[x.slice(0,x.indexOf('=')),x.slice(x.indexOf('=')+1)]));
const browser=await puppeteer.launch({executablePath:'/opt/brave-bin/brave',headless:true,args:['--no-sandbox','--disable-dev-shm-usage'],userDataDir:await Deno.makeTempDir({prefix:'roma-hotel-qa-'})});
const results=[];let reference,token;
const check=(value,name)=>{if(!value)throw Error(name);results.push(name);console.log('OK',name)};
const go=async(page,path)=>{const r=await page.goto(base+path,{waitUntil:'networkidle0'});return r.status()};
const fill=async(page,selector,value)=>{await page.$eval(selector,(e,v)=>{e.value=v;e.dispatchEvent(new Event('change',{bubbles:true}));},value)};
try{
 const publicContext=await browser.createBrowserContext();const page=await publicContext.newPage();await page.setViewport({width:1440,height:1000});
 for(const path of ['/reservar','/mi-reserva','/contacto','/disponibilidad'])check(await go(page,path)===200,'Formulario público '+path);
 for(const path of ['/admin/roma','/admin/roma/catalogo','/admin/roma/reservas','/admin/roma/mensajes']){const code=await go(page,path);check(code===403||page.url().includes('/user/login'),'Acceso anónimo rechazado '+path)}
 const today=new Date();today.setUTCDate(today.getUTCDate()+60);const arrival=today.toISOString().slice(0,10);today.setUTCDate(today.getUTCDate()+2);const departure=today.toISOString().slice(0,10);
 await go(page,`/disponibilidad?arrival=${arrival}&departure=${departure}&guests=1`);
 check((await page.$$('table tbody tr')).length>=1,'Disponibilidad muestra inventario desde MariaDB');
 await page.click('table tbody a');await page.waitForSelector('#roma-booking-form');
 await fill(page,'[name="customer_name"]','Cliente ficticio navegador');await fill(page,'[name="email"]','browser@example.invalid');await page.click('[name="demo_consent"]');
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-booking-form [type="submit"]')]);
 const body=await page.evaluate(()=>document.body.innerText);
 check(body.includes('Solicitud de prueba registrada')&&body.includes('Estado: pendiente'),'Formulario registra solicitud pendiente real');
 reference=body.match(/ROMA-[A-F0-9]{12}/)?.[0];token=body.match(/\b[a-f0-9]{48}\b/)?.[0];check(!!reference&&!!token,'Recibo entrega código y clave privada');
 await go(page,'/mi-reserva');await fill(page,'[name="reference"]',reference);await fill(page,'[name="secret"]',token);
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-lookup-form [type="submit"]')]);
 check((await page.evaluate(()=>document.body.innerText)).includes('Estado: Pendiente'),'Consulta privada muestra estado desde la base');
 const adminContext=await browser.createBrowserContext();const admin=await adminContext.newPage();await admin.setViewport({width:1440,height:1000});await go(admin,'/user/login');await fill(admin,'[name="name"]',env.DRUPAL_ADMIN_USER);await fill(admin,'[name="pass"]',env.DRUPAL_ADMIN_PASSWORD);
 await Promise.all([admin.waitForNavigation({waitUntil:'networkidle0'}),admin.click('#user-login-form [type="submit"]')]);
 check(await go(admin,'/admin/roma')===200,'Administrador accede al panel');check(await go(admin,'/admin/roma/catalogo')===200,'Administrador ve inventario y tarifas');
 await go(admin,'/admin/roma/reservas');
 const confirm=await admin.evaluate(ref=>{const row=[...document.querySelectorAll('tbody tr')].find(r=>r.textContent.includes(ref));return row?.querySelector('a[href$="/confirm"]')?.getAttribute('href')},reference);
 check(!!confirm,'Solicitud visible en el panel protegido');await go(admin,confirm);
 await Promise.all([admin.waitForNavigation({waitUntil:'networkidle0'}),admin.click('[type="submit"]')]);
 check((await admin.evaluate(()=>document.body.innerText)).includes('Estado actualizado'),'Confirmación administrativa mediante POST');
 await go(page,'/mi-reserva');await fill(page,'[name="reference"]',reference);await fill(page,'[name="secret"]',token);
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-lookup-form [type="submit"]')]);
 check((await page.evaluate(()=>document.body.innerText)).includes('Estado: Confirmada'),'El cliente ve la confirmación persistida');
 await go(admin,'/admin/roma/reservas');const cancel=await admin.evaluate(ref=>[...document.querySelectorAll('tbody tr')].find(r=>r.textContent.includes(ref))?.querySelector('a[href$="/cancel"]')?.getAttribute('href'),reference);await go(admin,cancel);
 await Promise.all([admin.waitForNavigation({waitUntil:'networkidle0'}),admin.click('[type="submit"]')]);
 check((await admin.evaluate(()=>document.body.innerText)).includes('Estado actualizado'),'Cancelación administrativa mediante POST');
 await go(page,'/contacto');await fill(page,'[name="name"]','Alumno ficticio');await fill(page,'[name="email"]','student@example.invalid');await fill(page,'[name="message"]','Mensaje ficticio de comprobación del formulario de contacto.');await page.click('[name="demo_consent"]');
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-contact-form [type="submit"]')]);
 check((await page.evaluate(()=>document.body.innerText)).includes('Mensaje de prueba guardado'),'Contacto guarda mensaje sin simular correo');
 await go(admin,'/admin/roma/mensajes');check((await admin.evaluate(()=>document.body.innerText)).includes('Mensaje ficticio de comprobación'),'Mensaje visible solo en panel protegido');
 for(const path of ['/reservar','/mi-reserva','/contacto','/disponibilidad']){await page.setViewport({width:390,height:844});await go(page,path);check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Formulario responsive '+path)}
 await Deno.mkdir(root+'docs/capturas',{recursive:true});await go(admin,'/admin/roma');await admin.setViewport({width:1440,height:1000});await admin.screenshot({path:root+'docs/capturas/panel-hotelero.png',fullPage:true});
 await Deno.writeTextFile(root+'docs/verificacion-fase-3-browser.json',JSON.stringify({date:new Date().toISOString(),results,fixture:'Una solicitud ficticia cancelada y un mensaje de prueba permanecen para la revisión.'},null,2));
}catch(e){console.error(e.stack);for(const page of await browser.pages()){console.error(page.url(),(await page.evaluate(()=>document.body.innerText).catch(()=>'' )).slice(-1400).replaceAll(token||'__no_token__','[CLAVE OCULTA]'))}Deno.exitCode=1}finally{await browser.close()}
