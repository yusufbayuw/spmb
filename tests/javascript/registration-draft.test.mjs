import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../public/js/registration-draft.js', import.meta.url), 'utf8');
const PREFIX = 'spmb:registration-draft:v1:';
const owner = 24;
const opening = 'opening-123';
const version = 'configuration-456';
const key = PREFIX + owner + ':' + opening + ':' + version;

function harness(initialData = {}, initialStorage = {}, blocked = false, completedKey = null) {
    const values = new Map(Object.entries(initialStorage));
    const listeners = new Map();
    const domListeners = new Map();
    const elementListeners = new Map();
    const timers = new Map();
    const watched = [];
    const requests = [];
    const calls = [];
    let nextTimer = 1;

    const storage = {
        get length() { return values.size; },
        key(index) { return Array.from(values.keys())[index] ?? null; },
        getItem(item) {
            if (blocked) throw new Error('Storage disabled');
            return values.get(item) ?? null;
        },
        setItem(item, value) {
            if (blocked) throw new Error('Storage disabled');
            values.set(item, String(value));
        },
        removeItem(item) {
            if (blocked) throw new Error('Storage disabled');
            values.delete(item);
        },
    };
    const add = (map, name, callback) => {
        map.set(name, [...(map.get(name) || []), callback]);
    };
    const emit = (map, name, payload = {}) => {
        for (const listener of map.get(name) || []) listener(payload);
    };
    const wire = {
        data: structuredClone(initialData),
        $watch(name, callback) { watched.push({ name, callback }); },
        $set(name, value, live) {
            requests.push({ name, value, live });
            this[name] = value;
            watched.filter((watcher) => watcher.name === name).forEach((watcher) => watcher.callback(value));
            return Promise.resolve();
        },
        $call(name) {
            calls.push(name);
            return Promise.resolve();
        },
    };
    const component = {
        canonical: {
            draftOwnerId: owner,
            openingUuid: opening,
            configurationUuid: version,
            draftFileKeys: ['document_upload'],
        },
        $wire: wire,
        el: {
            addEventListener(name, callback) { add(elementListeners, name, callback); },
            removeEventListener() {},
        },
    };
    const context = {
        localStorage: storage,
        window: {
            Livewire: {
                hook(name, callback) {
                    if (name === 'component.init') callback({ component, cleanup() {} });
                },
                all() { return []; },
            },
            addEventListener(name, callback) { add(listeners, name, callback); },
            removeEventListener() {},
        },
        document: {
            visibilityState: 'visible',
            addEventListener(name, callback) { add(domListeners, name, callback); },
            removeEventListener() {},
            getElementById(id) {
                if (id === 'spmb-registration-draft-completed' && completedKey) {
                    return { textContent: JSON.stringify(completedKey) };
                }
                return null;
            },
        },
        setTimeout(callback) { const id = nextTimer++; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    };

    runInNewContext(source, context, { filename: 'registration-draft.js' });

    return {
        values, wire, requests, calls, key, component, context,
        input() { emit(elementListeners, 'input'); },
        change() { emit(elementListeners, 'change'); },
        hide() { emit(domListeners, 'visibilitychange'); },
        pagehide() { emit(listeners, 'pagehide'); },
        completed() { emit(listeners, 'spmb-registration-created', { detail: { key } }); },
        flushTimers() {
            const pending = [...timers.values()];
            timers.clear();
            pending.forEach((callback) => callback());
        },
        async settle() { await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); },
    };
}

test('autosave is silent, local-only, and omits uploads and server identifiers', () => {
    const env = harness({
        nik: '3273010101010001',
        birth_date: '2015-01-01',
        full_name: 'Peserta',
        unit_uuid: 'server-unit',
        registration_opening_uuid: opening,
        unit_configuration_uuid: version,
        custom_answers: { text: 'Jawaban', document_upload: 'pre-registration/24/id.pdf' },
        achievements: [{ title: 'Lomba', certificate_path: 'pre-registration/24/award.pdf', certificate_original_name: 'award.pdf' }],
    });

    env.input();
    assert.equal(env.values.size, 0);
    env.flushTimers();
    const persisted = JSON.parse(env.values.get(key));
    assert.equal(persisted.data.nik, '3273010101010001');
    assert.equal(persisted.data.custom_answers.text, 'Jawaban');
    assert.ok(!Object.hasOwn(persisted.data.custom_answers, 'document_upload'));
    assert.ok(!Object.hasOwn(persisted.data, 'unit_uuid'));
    assert.ok(!Object.hasOwn(persisted.data, 'registration_opening_uuid'));
    assert.ok(!Object.hasOwn(persisted.data, 'unit_configuration_uuid'));
    assert.ok(!Object.hasOwn(persisted.data.achievements[0], 'certificate_path'));
    assert.ok(!Object.hasOwn(persisted.data.achievements[0], 'certificate_original_name'));
    assert.equal(env.requests.length, 0);
    assert.equal(env.calls.length, 0);
});

test('restore preserves authoritative hidden fields and only calls continuation matching after hydrate', async () => {
    const payload = JSON.stringify({
        savedAt: Date.now(),
        data: {
            nik: '3273010101010001',
            birth_date: '2015-01-01',
            full_name: 'Peserta Sebelumnya',
            registration_opening_uuid: 'untrusted-opening',
            custom_answers: { text: 'Lanjutkan', document_upload: 'should-never-restore.pdf' },
        },
    });
    const env = harness({
        unit_uuid: 'real-unit',
        registration_opening_uuid: opening,
        unit_configuration_uuid: version,
        full_name: '',
    }, { [key]: payload });

    await env.settle();
    assert.equal(env.requests.length, 1);
    assert.equal(env.requests[0].name, 'data');
    assert.equal(env.requests[0].live, true);
    assert.equal(env.wire.data.full_name, 'Peserta Sebelumnya');
    assert.equal(env.wire.data.registration_opening_uuid, opening);
    assert.equal(env.wire.data.unit_uuid, 'real-unit');
    assert.ok(!Object.hasOwn(env.wire.data.custom_answers, 'document_upload'));
    assert.deepEqual(env.calls, ['applyContinuationPrefill']);
});

test('restores never cross account or configuration version', () => {
    const otherUser = PREFIX + '25:' + opening + ':' + version;
    const olderVersion = PREFIX + owner + ':' + opening + ':older';
    const env = harness({}, {
        [otherUser]: JSON.stringify({ savedAt: Date.now(), data: { full_name: 'Other' } }),
        [olderVersion]: JSON.stringify({ savedAt: Date.now(), data: { full_name: 'Old' } }),
    });

    assert.equal(env.requests.length, 0);
    assert.ok(env.values.has(otherUser));
    assert.ok(!env.values.has(olderVersion));
});

test('expired draft is ignored and removed', () => {
    const expired = JSON.stringify({
        savedAt: Date.now() - 8 * 24 * 60 * 60 * 1000,
        data: { full_name: 'Stale' },
    });
    const env = harness({}, { [key]: expired });
    assert.equal(env.requests.length, 0);
    assert.ok(!env.values.has(key));
});

test('successful submission clears draft and cancels subsequent pagehide writes', () => {
    const env = harness({ full_name: 'Peserta' });
    env.input();
    env.flushTimers();
    assert.ok(env.values.has(key));
    env.completed();
    env.pagehide();
    assert.ok(!env.values.has(key));
});

test('redirect flash cleanup removes a draft even after leaving the create page', () => {
    const env = harness({}, { [key]: '{"savedAt":1,"data":{}}' }, false, key);
    assert.ok(!env.values.has(key));
});

test('unavailable browser storage never prevents normal editing', () => {
    const env = harness({ full_name: 'Peserta' }, {}, true);
    env.change();
    env.flushTimers();
    env.pagehide();
    assert.equal(env.requests.length, 0);
});
