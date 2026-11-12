// Comprobación de navegador sin paquetes adicionales. Requiere Edge/Chromium y Node >=22.
// El servidor usa exclusivamente .env.testing y no configura la base de desarrollo.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const browser = process.env.BROWSER_BIN;
const php = process.env.PHP_BIN ?? 'php';
const artifacts = process.env.BROWSER_ARTIFACTS;
const viewports = [390, 768, 1440];
if (!browser || !artifacts) throw new Error('Configura BROWSER_BIN, PHP_BIN y BROWSER_ARTIFACTS (directorio temporal).');
await mkdir(artifacts, { recursive: true });
const server = spawn(php, ['-S', '127.0.0.1:8093', '-t', '.', path.resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: path.resolve('public'),
    env: { ...process.env, APP_ENV: 'testing', SESSION_DRIVER: 'file', CACHE_STORE: 'file' },
    stdio: 'ignore',
});
const edge = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=9239', '--remote-debugging-address=127.0.0.1', `--user-data-dir=${path.join(artifacts, 'browser-profile')}`, 'about:blank'], { stdio: 'ignore' });
let socket;
let serial = 0;
const pending = new Map();
const errors = [];
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function waitFor(fn, label) {
    for (let i = 0; i < 100; i++) { try { const value = await fn(); if (value) return value; } catch {} await sleep(100); }
    throw new Error(`Tiempo agotado: ${label}`);
}
function call(method, params = {}) {
    const id = ++serial;
    return new Promise((resolve, reject) => {
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
async function evaluate(expression) {
    const response = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (response.exceptionDetails) throw new Error(response.exceptionDetails.text + ': ' + response.exceptionDetails.exception?.description);
    return response.result?.value;
}
async function navigate(url) {
    await call('Page.navigate', { url: `http://127.0.0.1:8093${url}` });
    try {
        await waitFor(() => evaluate(`document.readyState === 'complete' && location.pathname === ${JSON.stringify(url.split('?')[0])} && !!document.querySelector('main')`), url);
    } catch (error) {
        throw new Error(`${error.message}: ${await evaluate('document.body.innerText.slice(0, 1500)')}`);
    }
}
try {
    await waitFor(async () => (await fetch('http://127.0.0.1:8093/up')).ok, 'servidor PHP');
    const tabs = await waitFor(async () => (await (await fetch('http://127.0.0.1:9239/json/list')).json()).find(tab => tab.type === 'page'), 'navegador');
    socket = new WebSocket(tabs.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.id && pending.has(message.id)) {
            const request = pending.get(message.id);
            pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        }
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
        if (message.method === 'Log.entryAdded' && message.params.entry.level === 'error') errors.push(message.params.entry.text);
    };
    await call('Runtime.enable');
    await call('Page.enable');
    await call('Log.enable');
    const results = [];
    for (const width of viewports) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width < 500 });
        for (const url of ['/', '/catalogo', '/cotizar', '/guia-de-medidas', '/consultar-pedido', '/equipo/ingresar']) {
            await navigate(url);
            const result = await evaluate(`({width:innerWidth,scrollWidth:document.documentElement.scrollWidth,title:document.title})`);
            if (result.scrollWidth > result.width + 1) throw new Error(`Desborde horizontal en ${url}, ancho ${width}: ${JSON.stringify(result)}`);
            results.push({ url, viewport: width, ...result });
            if (url === '/' || url === '/cotizar') {
                const screenshot = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
                await writeFile(path.join(artifacts, `${url === '/' ? 'home' : 'configurator'}-${width}.png`), Buffer.from(screenshot.data, 'base64'));
            }
        }
    }
    await navigate('/cotizar?special=1');
    await waitFor(() => evaluate(`!!window.Livewire && !!document.querySelector('.quote-workspace')`), 'Livewire');
    await evaluate(`(async()=>{const root=[...document.querySelectorAll('*')].find(el=>el.hasAttribute('wire:id')&&el.querySelector('.quote-workspace')); const component=Livewire.find(root.getAttribute('wire:id')); await component.$set('material_name','Material ficticio de prueba de navegador',false); await component.$set('length_cm','150',false); await component.$set('width_cm','60',false); await component.$call('calculate');})()`);
    await waitFor(() => evaluate(`document.body.innerText.includes('Precio por confirmar')`), 'precio pendiente');
    await evaluate(`Livewire.find([...document.querySelectorAll('*')].find(el=>el.hasAttribute('wire:id')&&el.querySelector('.quote-workspace')).getAttribute('wire:id')).$call('addToCart')`);
    await waitFor(() => evaluate(`document.body.innerText.includes('Configuración agregada al carrito')`), 'confirmación de agregado');
    await navigate('/carrito');
    await waitFor(() => evaluate(`document.body.innerText.includes('Material ficticio de prueba de navegador')`), 'carrito persistente');
    await navigate('/solicitud');
    await evaluate(`(()=>{const form=document.querySelector('form[action$="/solicitud"]'); form.elements.name.value='Cliente ficticio de navegador'; form.elements.phone.value='PRUEBA NO REAL'; form.requestSubmit();})()`);
    await waitFor(() => evaluate(`location.pathname==='/solicitud/recibida' && document.body.innerText.includes('Código privado')`), 'solicitud persistida');
    await evaluate(`document.querySelector('a[href*="#number="]').click()`);
    await waitFor(() => evaluate(`location.pathname.startsWith('/seguimiento/') && document.body.innerText.includes('Tus cubiertas') && !location.hash`), 'enlace privado y seguimiento');
    if (errors.length) throw new Error(`Errores de navegador: ${JSON.stringify(errors)}`);
    await writeFile(path.join(artifacts, 'results.json'), JSON.stringify({ pages: results, livewireSpecialToCart: true, specialRequestAndPrivateTracking: true, errors }, null, 2));
    console.log(JSON.stringify({ checkedViewports: viewports, checkedPages: results.length, livewireSpecialToCart: true, specialRequestAndPrivateTracking: true, browserErrors: errors, artifacts }, null, 2));
} catch (error) {
    if (socket?.readyState === WebSocket.OPEN) {
        console.error(JSON.stringify({ browserErrors: errors, diagnostics: await evaluate(`({path:location.pathname,livewire:typeof window.Livewire,roots:[...document.querySelectorAll('*')].filter(el=>el.hasAttribute('wire:id')).length,scripts:[...document.scripts].map(el=>el.src)})`) }, null, 2));
    }
    throw error;
} finally {
    if (socket?.readyState === WebSocket.OPEN) {
        try { await call('Browser.close'); } catch {}
        socket.close();
    }
    server.kill();
    edge.kill();
}
