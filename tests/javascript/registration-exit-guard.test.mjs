import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/js/registration-exit-guard.js', import.meta.url), 'utf8');
const ownKey = 'spmb:registration-draft:v1:24:00000000-0000-0000-0000-000000000001:00000000-0000-0000-0000-000000000002';
const anotherKey = 'spmb:registration-draft:v1:25:00000000-0000-0000-0000-000000000001:00000000-0000-0000-0000-000000000002';

function harness({ initialDrafts = {}, completed = null, approved = false } = {}) {
    const events = new Map();
    const windowEvents = new Map();
    const componentEvents = new Map();
    const values = new Map(Object.entries(initialDrafts));
    const calls = { removed: [], storageWrites: [], confirmations: [], cleanups: [] };

    const add = (map, type, fn) => map.set(type, [...(map.get(type) ?? []), fn]);
    const remove = (map, type, fn) => map.set(type, (map.get(type) ?? []).filter((x) => x !== fn));
    const emit = (map, name, detail = {}) => {
        const event = {
            type: name,
            detail,
            target: detail?.target,
            defaultPrevented: false,
            preventDefault() { this.defaultPrevented = true; },
        };
        for (const handler of map.get(name) ?? []) handler(event);
        return event;
    };
    const form = { closest: () => null };
    const component = {
        canonical: {
            openingUuid: '00000000-0000-0000-0000-000000000001',
            configurationUuid: '00000000-0000-0000-0000-000000000002',
            draftOwnerId: 24,
        },
        el: {
            addEventListener(type, fn) { add(componentEvents, type, fn); },
            removeEventListener(type, fn) { remove(componentEvents, type, fn); },
        },
    };
    const localStorage = {
        removeItem(key) {
            calls.removed.push(key);
            values.delete(key);
        },
        setItem(key, value) {
            calls.storageWrites.push([key, value]);
            values.set(key, value);
        },
    };
    const window = {
        Livewire: {
            hook(type, fn) {
                if (type === 'component.init') fn({ component, cleanup: (fn) => calls.cleanups.push(fn) });
            },
            all: () => [component],
        },
        confirm(message) { calls.confirmations.push(message); return approved; },
        addEventListener(type, fn) { add(windowEvents, type, fn); },
        removeEventListener(type, fn) { remove(windowEvents, type, fn); },
    };
    const document = {
        getElementById(id) {
            return id === 'spmb-registration-draft-completed' && completed
                ? { textContent: JSON.stringify(completed) } : null;
        },
        addEventListener(type, fn) { add(events, type, fn); },
        removeEventListener(type, fn) { remove(events, type, fn); },
    };

    runInNewContext(script, { window, document, localStorage });
    return {
        calls, values,
        edit() { emit(componentEvents, 'input', { target: { closest: () => form } }); },
        refresh() { return emit(windowEvents, 'beforeunload'); },
        navigate() { return emit(events, 'livewire:navigate'); },
        clean() { return emit(windowEvents, 'spmb-draft-clean'); },
        complete(key = ownKey) { return emit(windowEvents, 'spmb-registration-created', { key }); },
    };
}

test('no autosave or browser write occurs when applicant types', () => {
    const env = harness();
    env.edit();
    assert.equal(env.calls.storageWrites.length, 0);
    assert.equal(env.values.size, 0);
    assert.equal(env.refresh().defaultPrevented, true);
});

test('livewire navigation is blocked when changes are unsaved and user cancels', () => {
    const env = harness({ approved: false });
    env.edit();
    assert.equal(env.navigate().defaultPrevented, true);
    assert.equal(env.calls.confirmations.length, 1);
    assert.equal(env.refresh().defaultPrevented, true);
});

test('livewire navigation can proceed after explicit confirmation', () => {
    const env = harness({ approved: true });
    env.edit();
    assert.equal(env.navigate().defaultPrevented, false);
    assert.equal(env.refresh().defaultPrevented, false);
});

test('saving, restoring or submitting clears the unsaved-changes warning', () => {
    const env = harness();
    env.edit();
    env.clean();
    assert.equal(env.refresh().defaultPrevented, false);
    env.edit();
    env.complete();
    assert.equal(env.refresh().defaultPrevented, false);
});

test('old draft browser data is removed only for completed registration key', () => {
    const env = harness({ initialDrafts: { [ownKey]: 'old draft', [anotherKey]: 'other applicant' } });
    env.complete();
    assert.equal(env.values.has(ownKey), false);
    assert.equal(env.values.has(anotherKey), true);
    assert.deepEqual(env.calls.removed, [ownKey]);
});

test('redirect marker clears only the exact completed legacy key', () => {
    const env = harness({
        initialDrafts: { [ownKey]: 'old draft', [anotherKey]: 'other applicant' },
        completed: ownKey,
    });
    assert.equal(env.values.has(ownKey), false);
    assert.equal(env.values.has(anotherKey), true);
});

test('no cleanup is triggered on ordinary draft save or refresh', () => {
    const env = harness({ initialDrafts: { [ownKey]: 'old draft' } });
    env.edit();
    env.clean();
    env.refresh();
    assert.equal(env.values.has(ownKey), true);
    assert.equal(env.calls.removed.length, 0);
});
