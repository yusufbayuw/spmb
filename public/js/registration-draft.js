/*
 * Silent applicant-only draft. Never stores file uploads, auth/consent tokens,
 * or server-owned configuration identifiers. The database remains authoritative.
 */
(() => {
    'use strict';

    const PREFIX = 'spmb:registration-draft:v1:';
    const MAX_BYTES = 256 * 1024;
    const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;
    const SAVE_DELAY_MS = 5000;
    const SKIP_KEYS = new Set([
        '__proto__', 'prototype', 'constructor',
        'unit_uuid', 'registration_opening_uuid', 'unit_configuration_uuid',
        'certificate_path', 'certificate_original_name',
        'privacyConsentUuid', 'privacy_consent', 'token', '_token',
    ]);
    const initialized = new WeakSet();

    // The flash marker is rendered only following a committed registration.
    const completed = document.getElementById('spmb-registration-draft-completed');
    if (completed) {
        try {
            const key = JSON.parse(completed.textContent || 'null');
            if (typeof key === 'string' && key.startsWith(PREFIX)) {
                localStorage.removeItem(key);
            }
        } catch (_) {
            // Browser storage may be unavailable; do not interrupt navigation.
        }
    }

    function clean(value, fileKeys, parent = '', depth = 0) {
        if (depth > 15) {
            return undefined;
        }

        if (value === null || typeof value === 'string' || typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : undefined;
        }

        if (Array.isArray(value)) {
            return value.map((item) => clean(item, fileKeys, parent, depth + 1))
                .filter((item) => item !== undefined);
        }

        if (Object.prototype.toString.call(value) !== '[object Object]') {
            return undefined;
        }

        const result = Object.create(null);
        for (const [key, item] of Object.entries(value)) {
            if (SKIP_KEYS.has(key) || (parent === 'custom_answers' && fileKeys.has(key))) {
                continue;
            }

            const safe = clean(item, fileKeys, key, depth + 1);
            if (safe !== undefined) {
                result[key] = safe;
            }
        }

        return result;
    }

    function init(component, cleanup = () => {}) {
        const state = component?.canonical;
        if (!state || initialized.has(component)
            || !Number.isInteger(state.draftOwnerId) || state.draftOwnerId < 1
            || typeof state.openingUuid !== 'string' || !state.openingUuid
            || typeof state.configurationUuid !== 'string' || !state.configurationUuid
            || !Array.isArray(state.draftFileKeys)) {
            return;
        }

        const wire = component.$wire;
        if (!wire || typeof wire.$set !== 'function' || typeof wire.$watch !== 'function') {
            return;
        }

        initialized.add(component);

        const fileKeys = new Set(state.draftFileKeys);
        const openingPrefix = PREFIX + state.draftOwnerId + ':' + state.openingUuid + ':';
        const key = openingPrefix + state.configurationUuid;
        let timer;
        let restoring = false;
        let finished = false;
        let touched = false;

        function removeDraft() {
            try {
                localStorage.removeItem(key);
            } catch (_) {
                // Nothing to do when storage access is denied.
            }
        }

        function save() {
            clearTimeout(timer);
            if (finished || restoring || !touched) {
                return;
            }

            try {
                const data = clean(wire.data, fileKeys);
                if (!data || typeof data !== 'object') {
                    return;
                }

                const payload = JSON.stringify({ savedAt: Date.now(), data });
                if (payload.length <= MAX_BYTES) {
                    localStorage.setItem(key, payload);
                }
            } catch (_) {
                // Autosave is best effort, not part of form submission.
            }
        }

        function schedule() {
            if (finished || restoring) {
                return;
            }

            touched = true;
            clearTimeout(timer);
            timer = setTimeout(save, SAVE_DELAY_MS);
        }

        function restore() {
            let draft = null;

            try {
                // Never apply a draft prepared against another published schema.
                for (let index = localStorage.length - 1; index >= 0; index--) {
                    const candidate = localStorage.key(index);
                    if (candidate?.startsWith(openingPrefix) && candidate !== key) {
                        localStorage.removeItem(candidate);
                    }
                }

                const raw = localStorage.getItem(key);
                if (!raw || raw.length > MAX_BYTES) {
                    return;
                }

                const stored = JSON.parse(raw);
                if (!stored || !Number.isFinite(stored.savedAt)
                    || stored.savedAt > Date.now() + 60 * 1000
                    || Date.now() - stored.savedAt > MAX_AGE_MS
                    || !stored.data || Array.isArray(stored.data)
                    || typeof stored.data !== 'object') {
                    removeDraft();
                    return;
                }

                draft = clean(stored.data, fileKeys);
            } catch (_) {
                // Invalid or blocked localStorage must not break the form.
                return;
            }

            if (!draft || !Object.keys(draft).length) {
                return;
            }

            // Preserve server-owned UUIDs and configuration; replay once only.
            const data = Object.assign({}, wire.data || {}, draft);
            restoring = true;
            touched = true;

            try {
                Promise.resolve(wire.$set('data', data, true))
                    .then(() => {
                        // Root-state restoration does not necessarily fire the
                        // two individual Livewire identity lifecycle hooks.
                        if (data.nik && data.birth_date && typeof wire.$call === 'function') {
                            return wire.$call('applyContinuationPrefill');
                        }
                    })
                    .catch(() => {})
                    .finally(() => {
                        restoring = false;
                    });
            } catch (_) {
                restoring = false;
            }
        }

        // Observe client-side Livewire state; do not call Laravel on autosave.
        wire.$watch('data', schedule);
        component.el?.addEventListener('input', schedule, true);
        component.el?.addEventListener('change', schedule, true);

        const flush = () => save();
        const onVisibilityChange = () => {
            if (document.visibilityState === 'hidden') {
                flush();
            }
        };
        const onCreated = (event) => {
            if (event.detail?.key !== key) {
                return;
            }

            finished = true;
            clearTimeout(timer);
            removeDraft();
        };

        window.addEventListener('pagehide', flush);
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('spmb-registration-created', onCreated);

        cleanup(() => {
            clearTimeout(timer);
            window.removeEventListener('pagehide', flush);
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('spmb-registration-created', onCreated);
            component.el?.removeEventListener('input', schedule, true);
            component.el?.removeEventListener('change', schedule, true);
        });

        restore();
    }

    function install() {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
            return;
        }

        window.Livewire.hook('component.init', ({ component, cleanup }) => init(component, cleanup));

        // The script may load after Livewire has already initialized the page.
        if (typeof window.Livewire.all === 'function') {
            window.Livewire.all().forEach((component) => init(component));
        }
    }

    if (window.Livewire) {
        install();
    } else {
        document.addEventListener('livewire:init', install, { once: true });
    }
})();
