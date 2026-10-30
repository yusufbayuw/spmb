/*
 * Applicant registration only. Warn about UNSAVED changes on navigation.
 * This never autosaves data, writes to browser storage, or blocks submit.
 * Browsers intentionally show their own text for tab-close / refresh warnings.
 */
(() => {
    'use strict';
    const initialized = new WeakSet();
    function install() {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
        window.Livewire.hook('component.init', ({ component, cleanup }) => {
            if (initialized.has(component) || !component?.canonical?.openingUuid
                || !component?.canonical?.configurationUuid
                || !component?.canonical?.draftOwnerId) return;
            initialized.add(component);
            let dirty = false;
            const onInput = (event) => {
                if (event.target?.closest?.('form') && !event.target.closest('form').closest('[role="dialog"]')) {
                    dirty = true;
                }
            };
            const onBeforeUnload = (event) => {
                if (!dirty) return;
                event.preventDefault();
                event.returnValue = '';
            };
            const clean = () => { dirty = false; };
            component.el?.addEventListener('input', onInput, true);
            component.el?.addEventListener('change', onInput, true);
            window.addEventListener('beforeunload', onBeforeUnload);
            window.addEventListener('spmb-draft-clean', clean);
            window.addEventListener('spmb-registration-created', clean);
            cleanup(() => {
                component.el?.removeEventListener('input', onInput, true);
                component.el?.removeEventListener('change', onInput, true);
                window.removeEventListener('beforeunload', onBeforeUnload);
                window.removeEventListener('spmb-draft-clean', clean);
                window.removeEventListener('spmb-registration-created', clean);
            });
        });
    }
    if (window.Livewire) install();
    else document.addEventListener('livewire:init', install, { once: true });
})();
