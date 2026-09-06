import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

export async function browser(debug = 'http://127.0.0.1:9333') {
    const target = await fetch(`${debug}/json/new?about:blank`, { method: 'PUT' }).then(response => response.json());
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise(done => socket.addEventListener('open', done, { once: true }));
    let serial = 0;
    const pending = new Map();
    const errors = [];
    const requests = [];
    socket.addEventListener('message', ({ data }) => {
        const event = JSON.parse(data);
        if (event.id) { pending.get(event.id)?.(event); pending.delete(event.id); }
        if (event.method === 'Runtime.exceptionThrown') errors.push(event.params.exceptionDetails.text);
        if (event.method === 'Runtime.consoleAPICalled' && event.params.type === 'error') errors.push(JSON.stringify(event.params.args));
        if (event.method === 'Network.requestWillBeSent') requests.push(event.params.request.url);
    });
    const send = (method, params = {}) => new Promise((done, reject) => {
        const id = ++serial;
        const timer = setTimeout(() => reject(new Error(`Timeout: ${method}`)), 15000);
        pending.set(id, result => { clearTimeout(timer); result.error ? reject(result.error) : done(result.result); });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        assert.ok(!result.exceptionDetails, JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const wait = async expression => {
        for (let attempt = 0; attempt < 100; attempt++) {
            if (await evaluate(expression)) return;
            await new Promise(done => setTimeout(done, 100));
        }
        throw new Error(`Not ready: ${expression}`);
    };
    await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
    return {
        send, evaluate, wait, errors, requests,
        resize: width => send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false }),
        navigate: async url => { await send('Page.navigate', { url }); await wait("!!document.querySelector('.corporate-header')"); },
        screenshot: async path => {
            const image = await send('Page.captureScreenshot', { captureBeyondViewport: true });
            await writeFile(path, Buffer.from(image.data, 'base64'));
        },
        close: async () => { socket.close(); await fetch(`${debug}/json/close/${target.id}`); },
    };
}
