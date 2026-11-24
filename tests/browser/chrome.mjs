import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
export async function launch(profile) {
    const chrome = spawn(process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless', '--disable-gpu', '--disable-background-networking', '--no-first-run',
        '--disable-default-apps', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
    ], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    const exited = once(chrome, 'exit');
    let port;
    try {
        for (let i = 0; i < 100; i++) {
            if (chrome.exitCode !== null) throw new Error('Chrome exited during startup');
            try { port = parseInt(await readFile(join(profile, 'DevToolsActivePort'), 'utf8')); } catch {}
            if (port) break;
            await delay(100);
        }
        const endpoint = 'http://127.0.0.1:' + port;
        const tabs = await (await fetch(endpoint + '/json/list')).json();
        const socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await once(socket, 'open');
        let id = 0;
        const replies = new Map();
        socket.onmessage = ({ data }) => {
            const msg = JSON.parse(data);
            if (replies.has(msg.id)) {
                const { resolve, reject } = replies.get(msg.id);
                replies.delete(msg.id);
                if (msg.error) reject(new Error(msg.error.message));
                else resolve(msg.result);
            }
        };
        const send = (method, params = {}) => new Promise((resolve, reject) => {
            replies.set(++id, { resolve, reject });
            socket.send(JSON.stringify({ id, method, params }));
        });
        const evaluate = async (expression, userGesture = false) => {
            const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        return { send, evaluate, close: async () => {
            socket.send(JSON.stringify({ id: ++id, method: 'Browser.close' }));
            await exited;
            socket.close();
        } };
    } catch (error) {
        chrome.kill();
        throw error;
    }
}

export async function until(page, expression) {
    for (let i = 0; i < 100; i++) {
        if (await page.evaluate(expression)) return;
        await delay(100);
    }
    throw new Error('Timed out waiting for ' + expression + '\n' + await page.evaluate('document.body.innerText'));
}

