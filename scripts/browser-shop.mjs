// Recorrido del rediseño con fixture tarifado en PostgreSQL dedicado *_test.
// Las credenciales opcionales se leen localmente y nunca se incluyen en resultados.
import { spawn } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const { BROWSER_BIN: browser, PHP_BIN: php, BROWSER_ARTIFACTS: artifacts, BROWSER_PRODUCT_ID: product } = process.env;
if (!browser || !php || !artifacts || !/^\d+$/.test(product ?? '') || !process.env.DB_DATABASE?.endsWith('_test')) throw new Error('Configura navegador, PHP, artefactos, producto tarifado y una base *_test.');
await mkdir(artifacts, { recursive: true });
const server = spawn(php, ['-S', '127.0.0.1:8095', '-t', '.', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.resolve('public'), env: { ...process.env, APP_ENV: 'testing', SESSION_DRIVER: 'file', CACHE_STORE: 'array' }, stdio: 'ignore' });
const edge = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=9241', `--user-data-dir=${path.join(artifacts, 'profile-'+Date.now())}`, 'about:blank'], { stdio: 'ignore' });
let socket, id = 0;
const pending = new Map(), errors = [], checks = [];
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function wait(fn, label) { for(let i=0;i<120;i++){try{if(await fn())return;}catch{}await sleep(100);}throw new Error(`Tiempo agotado: ${label}`); }
function call(method, params = {}) { const key=++id; return new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error(method)),15000);pending.set(key,{resolve,reject,timer});socket.send(JSON.stringify({id:key,method,params}));}); }
async function evaluate(expression) { const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result?.value; }
async function go(url) { await call('Page.navigate',{url:`http://127.0.0.1:8095${url}`});await wait(()=>evaluate(`location.pathname+location.search===${JSON.stringify(url)} && document.readyState==='complete' && !!document.querySelector('main')`),url); }
async function size(width) { await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500}); }
async function input(id,value) { await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('input',{bubbles:true}));})()`); }
async function screenshot(name) { await evaluate('window.scrollTo(0,0)');const r=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});await writeFile(path.join(artifacts,name+'.png'),Buffer.from(r.data,'base64')); }
async function assert(expression,label) { if(!await evaluate(expression))throw new Error(label);checks.push(label); }
async function noOverflow(label) { await assert('document.documentElement.scrollWidth<=innerWidth+1',label); }
try {
    await wait(async()=> (await fetch('http://127.0.0.1:8095/up')).ok,'PHP');
    let tab;await wait(async()=>{tab=(await(await fetch('http://127.0.0.1:9241/json/list')).json()).find(t=>t.type==='page');return !!tab;},'Edge');
    socket=new WebSocket(tab.webSocketDebuggerUrl);await new Promise((r,j)=>{socket.onopen=r;socket.onerror=j;});
    socket.onmessage=({data})=>{const m=JSON.parse(data);if(m.id&&pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);clearTimeout(p.timer);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);};
    await call('Runtime.enable');await call('Page.enable');
    for(const width of [390,768,1440]){
        await size(width);
        await go('/catalogo');await noOverflow(`Catálogo sin desborde ${width}`);await screenshot(`catalog-${width}`);
        await go(`/cotizar?product=${product}`);
        await input('cover-length','2,40');await input('cover-width','60');
        await wait(()=>evaluate(`document.querySelector('.summary-total')?.innerText.includes('14.400')`),'precio de 2,40 metros');
        await assert(`document.querySelector('.summary-facts').innerText.includes('240 cm')`,'Conversión m→cm '+width);
        await assert(`document.querySelector('.summary-facts').innerText.includes('1,44 m²')`,'Superficie '+width);
        await noOverflow(`Cotizador sin desborde ${width}`);
        if(width===1440){await evaluate(`document.querySelector('.finish-expand')?.click()`);await wait(()=>evaluate(`document.querySelectorAll('.finish-choice').length>6`),'ampliar acabados');checks.push('Catálogo completo accesible');await evaluate(`document.querySelector('.finish-expand').click()`);await wait(()=>evaluate(`document.querySelectorAll('.finish-choice').length===6`),'reducir acabados');}
        await assert(`(()=>{const c=document.querySelector('.config-main').getBoundingClientRect(),s=document.querySelector('.quote-sidebar').getBoundingClientRect(),h=document.querySelector('.quote-help').getBoundingClientRect();return ${width}===1440?h.left<c.left&&c.left<s.left:${width}<740?s.top>=c.bottom&&h.top>=s.bottom:s.left>c.left;})()`, 'Orden de columnas '+width);
        await screenshot(`configurator-${width}`);
        if(width===390){await evaluate(`document.querySelector('.mobile-menu summary').click()`);await assert(`document.querySelector('.mobile-menu').open`,'Menú móvil operativo');await evaluate(`document.querySelector('.mobile-menu summary').click()`);}
    }
    await input('cover-length','1,80');
    await evaluate(`(()=>{const s=document.querySelector('select[aria-label="Unidad del largo"]');s.value='cm';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await wait(()=>evaluate(`document.getElementById('cover-length').value==='180' && document.querySelector('.summary-total')?.innerText.includes('10.800')`),'cambio de unidad con edición pendiente');checks.push('Cambio de unidad atómico con escritura pendiente');
    await evaluate(`(()=>{const s=document.querySelector('select[aria-label="Unidad del largo"]');s.value='m';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await wait(()=>evaluate(`document.getElementById('cover-length').value==='1.8'`),'volver a metros');
    await input('cover-length','2,40');await wait(()=>evaluate(`document.querySelector('.summary-total')?.innerText.includes('14.400')`),'restaurar 240 cm');
    await input('cover-length','1,50');
    await sleep(80);
    await assert(`(()=>{const e=document.querySelector('.summary-price');return !e.getClientRects().length||!document.querySelector('.summary-total')?.innerText.includes('14.400');})()`,'Precio anterior oculto durante edición');
    await wait(()=>evaluate(`document.querySelector('.summary-total')?.innerText.includes('9.000')`),'recálculo a 150 cm');
    await input('cover-width','-1');await sleep(550);
    await evaluate(`document.querySelector('.quote-workspace').requestSubmit()`);
    await wait(()=>evaluate(`!!document.getElementById('width_cm-error')`),'error de ancho inválido');checks.push('Medidas inválidas rechazadas');
    await input('cover-width','60');await wait(()=>evaluate(`!!document.querySelector('.summary-total')`),'restaurar medidas');
    for(const count of [1,2]){
        await evaluate(`document.querySelector('.quote-workspace').requestSubmit()`);
        await wait(()=>evaluate(`document.querySelector('.cart-count')?.innerText==='${count}'`),'contador carrito');
        await assert(`location.pathname==='/cotizar' && !!document.querySelector('.added-notice')`,'Continuar cotizando después de agregar '+count);
    }
    await go('/carrito');await assert(`document.querySelectorAll('.cart-item').length===1`,'Agrupación idéntica');
    for(const width of [390,768,1440]){await size(width);await noOverflow('Carrito sin desborde '+width);await screenshot(`cart-${width}`);}
    await evaluate(`window.__cartPending=true; document.querySelector('.cart-item button[aria-label="Aumentar cantidad"]').click()`);
    await wait(()=>evaluate(`!window.__cartPending && document.readyState==='complete' && document.querySelector('.cart-item input[name=quantity]')?.value==='3'`),'aumentar cantidad en carrito');checks.push('Control + del carrito');
    await evaluate(`window.__cartPending=true; document.querySelector('.cart-item button[aria-label="Disminuir cantidad"]').click()`);
    await wait(()=>evaluate(`!window.__cartPending && document.readyState==='complete' && document.querySelector('.cart-item input[name=quantity]')?.value==='2'`),'disminuir cantidad en carrito');checks.push('Control - del carrito');
    await evaluate(`document.querySelector('.split-action').click()`);
    await wait(()=>evaluate(`location.pathname.endsWith('/editar')&&!!document.querySelector('#cover-length')`),'separar unidad');
    await input('cover-length','1,80');await wait(()=>evaluate(`document.querySelector('.summary-total')?.innerText.includes('10.800')`),'precio de unidad editada');
    await evaluate(`document.querySelector('.quote-workspace').requestSubmit()`);
    await wait(()=>evaluate(`location.pathname==='/carrito'&&document.querySelectorAll('.cart-item').length===2`),'guardar edición');checks.push('Separar y editar sin perder otra unidad');
    await evaluate(`(()=>{const card=[...document.querySelectorAll('.cart-item')].find(e=>e.innerText.includes('180.00'));card.querySelector('button[aria-label^="Eliminar"]').click();})()`);
    await wait(()=>evaluate(`document.querySelectorAll('.cart-item').length===1`),'eliminar configuración');checks.push('Eliminar una configuración');
    await go('/cotizar?special=1');await input('material-name','Material especial de navegador');await input('material-description','Descripción privada de demostración');await input('cover-length','1,50');await input('cover-width','60');
    await wait(()=>evaluate(`document.querySelector('.summary-price').innerText.includes('Precio por confirmar')`),'precio pendiente');
    await evaluate(`document.querySelector('.quote-workspace').requestSubmit()`);
    await wait(()=>evaluate(`!!document.querySelector('.added-notice')`),'agregar especial');await go('/carrito');
    await assert(`document.querySelector('main').innerText.includes('Subtotal conocido') && document.querySelector('main').innerText.includes('no es el total definitivo')`,'Importes mixtos no son total definitivo');
    await evaluate(`(()=>{const card=[...document.querySelectorAll('.cart-item')].find(e=>!e.innerText.includes('Material especial de navegador'));card.querySelector('button[aria-label^="Eliminar"]').click();})()`);
    await wait(()=>evaluate(`document.querySelectorAll('.cart-item').length===1`),'carrito solo especial');
    await assert(`document.querySelector('main').innerText.includes('Total definitivo pendiente de confirmación') && !document.querySelector('main').innerText.includes('$0 CLP')`,'Pendientes sin precio gratuito');
    for(const width of [390,768,1440]){await size(width);await noOverflow('Carrito pendiente sin desborde '+width);await screenshot(`cart-pending-${width}`);await go('/solicitud');await noOverflow('Solicitud sin desborde '+width);await screenshot(`request-${width}`);await go('/carrito');}
    await go('/solicitud');await assert(`document.getElementById('destination-city').getBoundingClientRect().height===0`,'Campos de transporte ocultos con retiro');
    await evaluate(`(()=>{const s=document.getElementById('delivery-method');s.value='transport';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);await sleep(80);
    await assert(`document.getElementById('destination-city').required && document.getElementById('destination-city').getBoundingClientRect().height>0`,'Transporte condicional');
    await input('customer-name','Cliente sintético de interfaz');await input('customer-phone','NO REAL');await input('destination-city','Comuna ficticia');
    await evaluate(`document.querySelector('.request-card').requestSubmit()`);await wait(()=>evaluate(`location.pathname==='/solicitud/recibida'`),'solicitud persistida');
    await evaluate(`document.querySelector('a[href*="#number="]').click()`);await wait(()=>evaluate(`location.pathname.startsWith('/seguimiento/')&&!!document.querySelector('main')`),'seguimiento privado');checks.push('Solicitud con transporte y seguimiento privado');
    if(process.env.BROWSER_ACCESS_FILE){
        const access=JSON.parse(await readFile(process.env.BROWSER_ACCESS_FILE,'utf8'));
        await go('/equipo/ingresar');await evaluate(`(()=>{const f=document.querySelector('form[action$="/equipo/ingresar"]');f.elements.email.value=${JSON.stringify(access.users.admin.email)};f.elements.password.value=${JSON.stringify(access.users.admin.password)};f.requestSubmit();})()`);
        await wait(()=>evaluate(`location.pathname==='/admin'`),'login administrativo');
        for(const url of ['/admin','/admin/cotizaciones','/admin/configuracion']){await go(url);await noOverflow('Panel operativo '+url);}
    }
    if(errors.length)throw new Error('Se detectaron excepciones JavaScript.');
    await writeFile(path.join(artifacts,'results.json'),JSON.stringify({checks,errors},null,2));
    console.log(JSON.stringify({checks:checks.length,viewports:[390,768,1440],errors,artifacts},null,2));
}catch(error){if(socket?.readyState===WebSocket.OPEN)console.error(JSON.stringify(await evaluate(`({path:location.pathname,title:document.title,alerts:[...document.querySelectorAll('[role=alert]')].map(e=>e.innerText)})`)));throw error;}
finally{if(socket?.readyState===WebSocket.OPEN){try{await call('Browser.close');}catch{}socket.close();}server.kill();edge.kill();}
