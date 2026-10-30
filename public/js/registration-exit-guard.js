/*
 * Explicit-save applicant form exit protection.
 * Does not persist or autosave form content.
 * Browser refresh/tab close may only show the browser's built-in warning.
 */
(() => {
    'use strict';

    const PREFIX = 'spmb:registration-draft:v1:';
    const initialized = new WeakSet();

    // Historical localStorage entries must only be removed on successful
    // final registration. Do not clear unrelated users' or unfinished drafts.
    function removeCompletedBrowserDraft(key) {
        if (typeof key !== 'string'
            || !/^spmb:registration-draft:v1:[0-9]+:[a-f0-9-]{36}:[a-f0-9-]{36}$/i.test(key)) {
            return;
        }

        try {
            localStorage.removeItem(key);
        } catch (_) {
            // Private browsing / storage restrictions cannot break registration.
        }
    }

    const completedMarker = document.getElementById('spmb-registration-draft-completed');
    if (completedMarker) {
        try {
            removeCompletedBrowserDraft(JSON.parse(completedMarker.textContent || 'null'));
        } catch (_) {
            // Never block page rendering on invalid historical browser data.
        }
    }

    window.addEventListener('spmb-registration-created', (event) => {
        removeCompletedBrowserDraft(event.detail?.key);
    });

    function init(component, cleanup = () => {}) {
        const state = component?.canonical;
        if (!state?.openingUuid || !state?.configurationUuid
            || !state?.draftOwnerId || initialized.has(component)) {
            return;
        }

        initialized.add(component);
        let dirty = false;

        const onInput = (event) => {
            const form = event.target?.closest?.('form');
            if (form && !form.closest('[role="dialog"]')) {
                dirty = true;
            }
        };

        const onBeforeUnload = (event) => {
            if (!dirty) return;

            // Standard browsers do not permit a custom save modal here.
            event.preventDefault();
            event.returnValue = '';
        };

        const onNavigate = (event) => {
            if (!dirty) return;

            if (!window.confirm('Ada perubahan formulir yang belum disimpan sebagai draft. Keluar tanpa menyimpan perubahan?')) {
                event.preventDefault();
                return;
            }

            // Navigation was explicitly approved, so don't prompt again.
            dirty = false;
        };

        const clean = () => { dirty = false; };

        component.el?.addEventListener('input', onInput, true);
        component.el?.addEventListener('change', onInput, true);
        document.addEventListener('livewire:navigate', onNavigate);
        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('spmb-draft-clean', clean);
        window.addEventListener('spmb-registration-created', clean);

        cleanup(() => {
            component.el?.removeEventListener('input', onInput, true);
            component.el?.removeEventListener('change', onInput, true);
            document.removeEventListener('livewire:navigate', onNavigate);
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('spmb-draft-clean', clean);
            window.removeEventListener('spmb-registration-created', clean);
        });
    }

    function install() {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;

        window.Livewire.hook('component.init', ({ component, cleanup }) => {
            init(component, cleanup);
        });

        // Deferred script can execute after Livewire initialization on some pages.
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
