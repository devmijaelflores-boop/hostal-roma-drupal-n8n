import puppeteer from 'npm:puppeteer-core@25.10.0';
const root=new URL('../',import.meta.url).pathname;
const env=Object.fromEntries((await Deno.readTextFile(root+'.env')).trim().split('\n').map(s=>[s.slice(0,s.indexOf('=')),s.slice(s.indexOf('=')+1)]));
const base=Deno.env.get('ROMA_BROWSER_BASE') || 'http://localhost:8080',checks=[],errors=[];
const check=(v,n)=>{if(!v)throw Error(n);checks.push(n);console.log('OK',n);};
async function php(code){const r=await new Deno.Command('docker',{cwd:root,args:['compose','exec','-T','drupal','vendor/bin/drush','php:eval',code],stdout:'piped',stderr:'piped'}).output();if(!r.success)throw Error(new TextDecoder().decode(r.stderr));return JSON.parse(new TextDecoder().decode(r.stdout));}
const fixture=await php("$h=\\Drupal::service('roma_booking.hotel');$room=$h->saveItem('room',0,['code'=>'QA-UI-'.bin2hex(random_bytes(3)),'type_id'=>1,'active'=>1]);$a=$h->today();$d=(new \\DateTimeImmutable($a))->modify('+1 day')->format('Y-m-d');$r=$h->request(['room_id'=>$room,'arrival'=>$a,'departure'=>$d,'guests'=>1,'customer_name'=>'Ensayo interfaz recepción','email'=>'reception-ui@example.invalid']);$h->transition($r['id'],'confirmed',1);echo json_encode(['room'=>$room,'id'=>$r['id'],'today'=>$a]);");
const browser=await puppeteer.launch({executablePath:'/opt/brave-bin/brave',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
try{
 const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
 let r=await page.goto(base+'/admin/roma/recepcion',{waitUntil:'networkidle0'});check(r.status()===403||page.url().includes('/user/login'),'Calendario protegido ante acceso anónimo');
 await page.goto(base+'/user/login',{waitUntil:'networkidle0'});await page.type('[name=name]',env.DRUPAL_ADMIN_USER);await page.type('[name=pass]',env.DRUPAL_ADMIN_PASSWORD);await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#user-login-form [type=submit]')]);
 r=await page.goto(base+'/admin/roma/recepcion',{waitUntil:'networkidle0'});check(r.status()===200&&await page.$('.reception-calendar'),'Administrador accede al calendario');
 check((await page.$$('.reception-calendar th')).length===15,'Calendario muestra 14 noches y columna de habitación');
 check((await page.evaluate(()=>document.body.innerText)).includes('Ensayo interfaz recepción'),'Recepción muestra la llegada confirmada de hoy');
 const status=()=>php(`$o=\\Drupal::service('roma_reception.operations');echo json_encode(['state'=>$o->stay(${fixture.id})['state'],'clean'=>$o->housekeeping(${fixture.room})]);`);
 const actionPath=a=>`/admin/roma/recepcion/stay/${fixture.id}/${a}`;
 await page.goto(base+actionPath('check_in'),{waitUntil:'networkidle0'});check((await status()).state==='not_arrived','GET de confirmación no registra entrada');
 await page.$eval('[name=form_token]',el=>el.value='incorrecto');await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-reception-action [type=submit]')]);
 check((await status()).state==='not_arrived','POST con token manipulado no registra entrada');
 await page.goto(base+actionPath('check_in'),{waitUntil:'networkidle0'});await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-reception-action [type=submit]')]);check((await status()).state==='in_house','Formulario registra check-in con confirmación');
 await page.goto(base+actionPath('check_out'),{waitUntil:'networkidle0'});await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-reception-action [type=submit]')]);let value=await status();check(value.state==='checked_out'&&value.clean==='dirty','Formulario de checkout deja habitación por limpiar');
 await page.goto(`${base}/admin/roma/recepcion/room/${fixture.room}/clean`,{waitUntil:'networkidle0'});await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-reception-action [type=submit]')]);check((await status()).clean==='clean','Recepción vuelve a marcar la habitación lista');
 const day=n=>new Date(Date.now()+n*86400000).toISOString().slice(0,10);
 await page.goto(base+'/admin/roma/mantenimiento',{waitUntil:'networkidle0'});
 await page.select('[name=room]',String(fixture.room));
 for(const [name,value] of [['arrival',day(10)],['departure',day(12)],['reason','Ensayo interfaz mantenimiento']]){await page.$eval(`[name="${name}"]`,(el,v)=>el.value=v,value);}
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-reception-block [type=submit]')]);
 const blocks=await php(`echo json_encode(\\Drupal::database()->select('roma_room_block','m')->fields('m',['id'])->condition('room_id',${fixture.room})->condition('active',1)->execute()->fetchCol());`);
 check(blocks.length===1,'Formulario programa mantenimiento con fechas y motivo');
 let response=await fetch(base+`/api/roma/v1/availability?arrival=${day(10)}&departure=${day(12)}&guests=1`);let data=await response.json();
 check(!data.rooms.some(r=>Number(r.room_id)===fixture.room),'La API pública excluye la habitación en mantenimiento');
 await page.goto(`${base}/admin/roma/recepcion/block/${blocks[0]}/release`,{waitUntil:'networkidle0'});await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('#roma-reception-action [type=submit]')]);
 response=await fetch(base+`/api/roma/v1/availability?arrival=${day(10)}&departure=${day(12)}&guests=1`);data=await response.json();check(data.rooms.some(r=>Number(r.room_id)===fixture.room),'Liberar el mantenimiento devuelve disponibilidad pública');
 await page.setViewport({width:1440,height:1000});await page.screenshot({path:root+'docs/capturas/recepcion-aws.png',fullPage:true});
 await page.goto(base+'/admin/roma/recepcion?start='+fixture.today+'&days=7',{waitUntil:'networkidle0'});check((await page.$$('.reception-calendar th')).length===8,'Filtro cambia el calendario a siete noches');
 await page.setViewport({width:390,height:844});check(await page.$eval('.reception-scroll',el=>el.scrollWidth>el.clientWidth),'Calendario móvil permite desplazamiento horizontal dentro del panel');
 check(!errors.length,'Sin errores JavaScript en recepción');
 await Deno.writeTextFile(root+'docs/verificacion-recepcion-browser.json',JSON.stringify({date:new Date().toISOString(),checks,errors},null,2)+'\n');
}finally{
 await php(`$db=\\Drupal::database();$db->delete('roma_stay')->condition('reservation_id',${fixture.id})->execute();foreach(['roma_audit','roma_notification'] as $t){$db->delete($t)->condition('reservation_id',${fixture.id})->execute();}$db->delete('roma_reception_event')->condition('kind','stay')->condition('entity_id',${fixture.id})->execute();$db->delete('roma_reception_event')->condition('kind','room')->condition('entity_id',${fixture.room})->execute();$db->delete('roma_reservation')->condition('id',${fixture.id})->execute();$blocks=$db->select('roma_room_block','m')->fields('m',['id'])->condition('room_id',${fixture.room})->execute()->fetchCol();if($blocks){$db->delete('roma_reception_event')->condition('kind','block')->condition('entity_id',$blocks,'IN')->execute();}$db->delete('roma_room_block')->condition('room_id',${fixture.room})->execute();$db->delete('roma_housekeeping')->condition('room_id',${fixture.room})->execute();$em=\\Drupal::entityTypeManager();foreach($em->getStorage('commerce_product_variation')->loadByProperties(['sku'=>'ROMA-ROOM-${fixture.room}']) as $v){if(!$em->getStorage('commerce_order_item')->getQuery()->accessCheck(FALSE)->condition('purchased_entity',$v->id())->count()->execute()){if($product=$v->getProduct()){$product->delete();}else{$v->delete();}}}$db->delete('roma_room')->condition('id',${fixture.room})->execute();echo 'true';`);
 await browser.close();
}
