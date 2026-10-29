export function matches(pattern, path) {
    const escaped = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replaceAll('*', '[^/]+');
    return new RegExp(`^${escaped}$`).test(path);
}

export function targetFor(form, submitter, config, location) {
    const method = (submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') return null;
    const destination = new URL(submitter?.getAttribute('formaction') || form.getAttribute('action') || location.href, location.href);
    if (destination.origin !== location.origin) return null;
    let path = destination.pathname;
    const prefix = String(config.basePath || '').replace(/\/$/, '');
    if (prefix && path.startsWith(`${prefix}/`)) path = path.slice(prefix.length);
    path = path.replace(/\/$/, '') || '/';
    if (!/^\/(?:[a-zA-Z0-9_-]+\/?)*$/.test(path) || path.includes('//')) return null;
    if (path === '/form/newsletter' && form.elements.namedItem('option')?.value === 'unsubscribe') return null;
    return config.patterns.some(pattern => matches(pattern, path)) ? path : null;
}

export function install(win, config) {
    const document = win.document;
    const states = new WeakMap();

    function syncCheckbox(state) {
        const checkbox = state?.widget.querySelector('input[type="checkbox"]');
        if (!checkbox) return;
        checkbox.autocomplete = 'off';
        if (!['verified', 'verifying'].includes(state.widget.getState?.())) checkbox.checked = false;
    }

    function restoreCheckboxes() {
        for (const form of document.forms) syncCheckbox(states.get(form));
    }

    function attach(form, submitter = null) {
        const path = targetFor(form, submitter, config, win.location);
        let state = states.get(form);
        if (!path) {
            state?.container.remove();
            states.delete(form);
            return null;
        }
        if (state && state.path === path) return state;
        if (state) {
            state.container.remove();
            states.delete(form);
        }
        const container = document.createElement('div');
        container.className = 'starcode-check';
        const widget = document.createElement('altcha-widget');
        const endpoint = new URL(config.challengeUrl, win.location.href);
        endpoint.searchParams.set('target', path);
        widget.setAttribute('configuration', JSON.stringify({
            challenge: endpoint.href, auto: 'onsubmit', language: config.language, name: '',
            type: 'checkbox', display: 'standard', workers: 2, timeout: 30000,
            humanInteractionSignature: false, serverVerificationFields: false,
            serverVerificationTimeZone: false, setCookie: null, verifyUrl: null,
            credentials: 'omit', debug: false, test: false, hideFooter: true,
            hideLogo: true, minDuration: 0, validationMessage: config.retryMessage,
        }));
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'starcode';
        const error = document.createElement('p');
        error.className = 'starcode-message';
        error.setAttribute('role', 'alert');
        error.hidden = true;
        container.append(widget, input, error);
        const button = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        if (button) button.before(container);
        else form.append(container);
        state = { widget, input, error, container, path, used: false, pending: null, resuming: false };
        states.set(form, state);
        widget.addEventListener('verified', event => { input.value = event.detail?.payload || ''; state.used = false; });
        widget.addEventListener('statechange', event => {
            if (event.detail?.state !== 'verified') input.value = '';
            syncCheckbox(state);
        });
        form.addEventListener('reset', () => {
            if (states.get(form) !== state) return;
            input.value = '';
            state.used = false;
            state.error.hidden = true;
            widget.reset?.();
        });
        return state;
    }

    async function protect(form, submitter = null) {
        const state = attach(form, submitter);
        if (!state) throw new Error('STARCODE_FORM_NOT_CONFIGURED');
        if (state.pending) return state.pending;
        state.pending = (async () => {
            state.error.hidden = true;
            form.setAttribute('aria-busy', 'true');
            try {
                await win.customElements.whenDefined('altcha-widget');
                await Promise.resolve();
                if (state.used || state.widget.getState() !== 'verified' || !state.input.value) {
                    state.widget.reset();
                    state.input.value = '';
                    const result = await state.widget.verify();
                    if (!result?.payload) throw new Error('STARCODE_VERIFICATION_FAILED');
                    state.input.value = result.payload;
                }
                state.used = true;
                return state.input.value;
            } catch (error) {
                state.input.value = '';
                state.error.textContent = config.unavailableMessage;
                state.error.hidden = false;
                throw error;
            } finally {
                form.removeAttribute('aria-busy');
            }
        })();
        try {
            return await state.pending;
        } finally {
            state.pending = null;
        }
    }

    function scan(root = document) {
        if (root instanceof win.HTMLFormElement) attach(root);
        root.querySelectorAll?.('form').forEach(form => attach(form));
    }

    async function submit(event) {
        if (!(event.target instanceof win.HTMLFormElement)) return;
        const form = event.target;
        const state = attach(form, event.submitter);
        if (!state || state.resuming) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (state.pending) return;
        try {
            await protect(form, event.submitter);
            if (targetFor(form, event.submitter, config, win.location) !== state.path) throw new Error('STARCODE_TARGET_CHANGED');
            state.resuming = true;
            try {
                win.HTMLFormElement.prototype.requestSubmit.call(form, event.submitter || undefined);
            } finally {
                state.resuming = false;
            }
        } catch {
            state.container.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
        }
    }

    win.addEventListener('submit', submit, true);
    win.addEventListener('pageshow', restoreCheckboxes);
    const observer = new win.MutationObserver(records => {
        for (const record of records) {
            for (const node of record.addedNodes) if (node.nodeType === 1) scan(node);
            if (record.type === 'attributes' && record.target instanceof win.HTMLFormElement) attach(record.target);
            const form = record.target.closest?.('form');
            if (form) syncCheckbox(states.get(form));
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['action', 'method'] });
    scan();
    return Object.freeze({ protect, scan, destroy: () => {
        observer.disconnect();
        win.removeEventListener('submit', submit, true);
        win.removeEventListener('pageshow', restoreCheckboxes);
    } });
}
