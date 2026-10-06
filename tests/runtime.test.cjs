const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const runtime = readFileSync(require('node:path').join(__dirname, '../resources/js/webmcp.js'), 'utf8');

function harness(responses) {
    const registered = new Map();
    const calls = [];
    const state = { markers: ['exposure-token'], responses: [...responses] };
    const document = {
        readyState: 'complete', baseURI: 'https://example.test/',
        modelContext: { registerTool(tool, { signal }) {
            assert.equal(registered.has(tool.name), false, 'no duplicate registration');
            registered.set(tool.name, tool);
            signal.addEventListener('abort', () => registered.delete(tool.name));
        } },
        querySelectorAll: () => state.markers.map(token => ({ dataset: { webmcpTool: token } })),
        getElementById: () => null, addEventListener() {}, removeEventListener() {}, dispatchEvent() {},
    };
    const window = { navigator: {}, addEventListener() {}, removeEventListener() {} };
    vm.runInNewContext(runtime, { window, document, URL, AbortController, queueMicrotask,
        CustomEvent: class {}, fetch: async (url, options) => {
            calls.push({ url, options });
            const next = state.responses.shift();
            assert.ok(next, 'unexpected network request');
            return { ok: next.status < 400, status: next.status, json: async () => next.body };
        },
    });
    return { state, calls, registered, api: window.FossevaWebMcp.createWebMcp({ manifestUrl: '/_webmcp/manifest' }) };
}
const definition = { name: 'increment_counter', description: 'Increment the SDK counter', inputSchema: { type: 'object', properties: { amount: { type: 'integer' } } }, annotations: {}, execution: { method: 'POST', url: 'https://example.test/_webmcp/execute?tool=token' } };
const manifest = (tools = [definition], csrfToken = 'csrf-one') => ({ status: 200, body: { revision: csrfToken, csrfToken, tools } });

// start() queues its initial sync; the following sync supersedes it before the network request.
test('registers SDK metadata and returns the original SDK string', async () => {
    const h = harness([manifest(), { status: 200, body: { result: '{"counter":4}' } }]);
    h.api.start(); await h.api.sync();
    const result = await h.registered.get('increment_counter').execute({ amount: 3 });
    assert.equal(result, '{"counter":4}');
    assert.equal(new URL(h.calls[0].url).searchParams.get('tools[]'), 'exposure-token');
    assert.equal(h.calls[1].options.headers['X-CSRF-TOKEN'], 'csrf-one');
    assert.equal(h.calls[1].options.body, '{"amount":3}');
    assert.equal(h.calls[1].options.credentials, 'same-origin');
    h.api.stop(); assert.equal(h.registered.size, 0);
});

test('removing Blade markers unregisters their tools without stale bootstrap selection', async () => {
    const h = harness([manifest(), manifest([])]);
    h.api.start(); await h.api.sync();
    h.state.markers = []; await h.api.sync(true);
    assert.equal(h.registered.size, 0);
    assert.equal(new URL(h.calls[1].url).searchParams.has('tools[]'), false);
});

test('validation errors are readable by the agent without retrying a mutation', async () => {
    const h = harness([manifest(), { status: 422, body: { message: 'Invalid amount', errors: { amount: ['Maximum is 10'] } } }]);
    h.api.start(); await h.api.sync();
    const result = JSON.parse(await h.registered.get('increment_counter').execute({ amount: 25 }));
    assert.equal(result.error.status, 422);
    assert.deepEqual(result.error.fields.amount, ['Maximum is 10']);
    assert.equal(h.calls.filter(call => call.options.method === 'POST').length, 1);
});

test('refreshes CSRF once before retrying a rejected request', async () => {
    const h = harness([manifest(), { status: 419, body: {} }, manifest([definition], 'csrf-two'), { status: 200, body: { result: 'done' } }]);
    h.api.start(); await h.api.sync();
    assert.equal(await h.registered.get('increment_counter').execute({ amount: 1 }), 'done');
    assert.equal(h.calls[3].options.headers['X-CSRF-TOKEN'], 'csrf-two');
    assert.equal(h.calls.filter(call => call.options.method === 'POST').length, 2);
});

test('a repeated CSRF failure ends after one retry', async () => {
    const h = harness([manifest(), { status: 419, body: {} }, manifest(), { status: 419, body: { message: 'Expired session' } }]);
    h.api.start(); await h.api.sync();
    assert.equal(JSON.parse(await h.registered.get('increment_counter').execute({ amount: 1 })).error.status, 419);
    assert.equal(h.calls.length, 4);
});

test('an unauthorized manifest clears registrations', async () => {
    const h = harness([manifest(), { status: 403, body: {} }]);
    h.api.start(); await h.api.sync(); await h.api.sync(true);
    assert.equal(h.registered.size, 0);
});
