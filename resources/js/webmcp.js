(function (global) {
    'use strict';

    function unique(values) { return Array.from(new Set((values || []).filter(Boolean))); }
    function emit(name, detail) { document.dispatchEvent(new CustomEvent(name, { detail: detail })); }

    function createWebMcp(options) {
        var config = Object.assign({}, options || {});
        var selectedTools = new Set();
        var registrations = new Map();
        var revision = null;
        var running = false;
        var livewireCleanup = null;
        var syncQueued = false;
        var syncVersion = 0;
        var syncChain = Promise.resolve();

        function modelContext() {
            if (document.modelContext) return document.modelContext;
            if (global.navigator && navigator.modelContext) return navigator.modelContext;
            return null;
        }

        function scanDom() {
            selectedTools = new Set(unique(Array.from(document.querySelectorAll('[data-webmcp-tool]')).map(function (node) { return node.dataset.webmcpTool; })));
        }

        function manifestUrl() {
            var url = new URL(config.manifestUrl, document.baseURI);
            selectedTools.forEach(function (value) { url.searchParams.append('tools[]', value); });
            return url.toString();
        }

        async function execute(definition, input, executeOptions, retried) {
            var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
            if (config.csrfToken) headers['X-CSRF-TOKEN'] = config.csrfToken;
            var response = await fetch(definition.execution.url, {
                method: definition.execution.method || 'POST', credentials: 'same-origin', headers: headers,
                body: JSON.stringify(input || {}), signal: executeOptions && executeOptions.signal,
            });
            if (response.status === 419 && !retried) { await sync(true); return execute(definition, input, executeOptions, true); }
            if (response.status === 401 || response.status === 403 || response.status === 404) sync(true).catch(function () {});
            var body = await response.json().catch(function () { return {}; });
            if (!response.ok) return JSON.stringify({ error: { status: response.status, message: body.message || 'WebMCP request failed', fields: body.errors || {} } });
            return body.result;
        }

        async function register(definition, version) {
            var context = modelContext();
            if (!context || typeof context.registerTool !== 'function') return;
            var controller = new AbortController();
            var descriptor = { name: definition.name, description: definition.description, inputSchema: definition.inputSchema, annotations: definition.annotations };
            descriptor.execute = function (input, opts) { return execute(definition, input, opts || {}, false); };
            await context.registerTool(descriptor, { signal: controller.signal });
            if (!running || version !== syncVersion) { controller.abort(); return; }
            registrations.set(definition.name, { controller: controller, hash: JSON.stringify(definition) });
            emit('webmcp:registered', { name: definition.name });
        }

        function sync(force) {
            var version = ++syncVersion;
            syncChain = syncChain.then(function () { return refresh(force, version); });
            return syncChain;
        }

        async function refresh(force, version) {
            if (!running || version !== syncVersion) return;
            scanDom();
            var headers = revision && !force ? { 'If-None-Match': '"' + revision + '"' } : {};
            try {
                var response = await fetch(manifestUrl(), { credentials: 'same-origin', headers: headers });
                if (response.status === 304) return;
                if (!response.ok) { registrations.forEach(function (item) { item.controller.abort(); }); registrations.clear(); revision = null; throw new Error('Unable to load WebMCP manifest: ' + response.status); }
                var manifest = await response.json();
                if (!running || version !== syncVersion) return;
                config.csrfToken = manifest.csrfToken;
                var incoming = new Map((manifest.tools || []).map(function (tool) { return [tool.name, tool]; }));
                registrations.forEach(function (registration, name) {
                    var next = incoming.get(name);
                    if (!next || registration.hash !== JSON.stringify(next)) { registration.controller.abort(); registrations.delete(name); emit('webmcp:unregistered', { name: name }); }
                });
                for (var tool of incoming.values()) if (!registrations.has(tool.name)) await register(tool, version);
                if (!running || version !== syncVersion) return;
                revision = manifest.revision;
                emit('webmcp:sync', { revision: revision, tools: Array.from(incoming.keys()) });
            } catch (error) { revision = null; emit('webmcp:error', { error: error }); }
        }

        function scheduleSync() { if (syncQueued) return; syncQueued = true; queueMicrotask(function () { syncQueued = false; sync(true); }); }
        function installLivewireHooks() {
            if (livewireCleanup || !global.Livewire || typeof global.Livewire.hook !== 'function') return;
            livewireCleanup = global.Livewire.hook('morph.updated', scheduleSync) || function () {};
        }
        function start() { if (running) return api; running = true; sync(true); installLivewireHooks(); document.addEventListener('livewire:init', installLivewireHooks); document.addEventListener('livewire:navigated', onNavigation); document.addEventListener('webmcp:auth-changed', authChanged); global.addEventListener('focus', onFocus); return api; }
        function stop() { running = false; syncVersion++; registrations.forEach(function (item) { item.controller.abort(); }); registrations.clear(); if (typeof livewireCleanup === 'function') livewireCleanup(); livewireCleanup = null; document.removeEventListener('livewire:init', installLivewireHooks); document.removeEventListener('livewire:navigated', onNavigation); document.removeEventListener('webmcp:auth-changed', authChanged); global.removeEventListener('focus', onFocus); return api; }
        function onNavigation() { sync(true); }
        function onFocus() { sync(false); }
        function authChanged() { revision = null; return sync(true); }
        var api = {
            start: start, stop: stop, sync: sync, authChanged: authChanged,
        };
        return api;
    }

    global.FossevaWebMcp = { createWebMcp: createWebMcp };
    function boot() {
        var element = document.getElementById('webmcp-config');
        if (!element || global.webMcp) return;
        try { global.webMcp = createWebMcp(JSON.parse(element.textContent || '{}')).start(); }
        catch (error) { emit('webmcp:error', { error: error }); }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})(window);
