import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { install, matches, targetFor } from '../../src/Resources/app/storefront/src/forms.mjs';

const config = {
    patterns: ['/form/contact', '/form/custom/*'], basePath: '',
    challengeUrl: '/starcode/challenge', language: 'en',
    retryMessage: 'Try again', unavailableMessage: 'Unavailable',
};

function setup(html = '<form method="post" action="/form/contact"><button type="submit">Send</button></form>') {
    const dom = new JSDOM(html, { url: 'https://shop.invalid/' });
    let calls = 0;
    class Widget extends dom.window.HTMLElement {
        configure(value) { this.config = value; return Promise.resolve(); }
        getState() { return 'unverified'; }
        reset() {}
        async verify() { calls += 1; return { payload: `proof-${calls}` }; }
    }
    dom.window.customElements.define('altcha-widget', Widget);
    const api = install(dom.window, config);
    return { dom, api, calls: () => calls };
}

test('path matching does not leak protection onto external or nested targets', () => {
    const { dom, api } = setup();
    const form = dom.window.document.querySelector('form');
    assert.equal(targetFor(form, null, config, dom.window.location), '/form/contact');
    form.action = 'https://provider.invalid/form/contact';
    assert.equal(targetFor(form, null, config, dom.window.location), null);
    assert.equal(matches('/form/custom/*', '/form/custom/one'), true);
    assert.equal(matches('/form/custom/*', '/form/custom/one/two'), false);
    api.destroy();
    dom.window.close();
});

test('protects dynamic forms and never duplicates widgets', async () => {
    const { dom, api } = setup('');
    const form = dom.window.document.createElement('form');
    form.method = 'post';
    form.action = '/form/contact';
    dom.window.document.body.append(form);
    await new Promise(resolve => setTimeout(resolve, 0));
    api.scan();
    assert.equal(form.querySelectorAll('altcha-widget').length, 1);
    api.destroy();
    dom.window.close();
});

test('each scripted submission gets a fresh proof with privacy options disabled', async () => {
    const { dom, api, calls } = setup();
    const form = dom.window.document.querySelector('form');
    assert.equal(await api.protect(form), 'proof-1');
    assert.equal(await api.protect(form), 'proof-2');
    assert.equal(calls(), 2);
    assert.equal(new dom.window.FormData(form).get('starcode'), 'proof-2');
    const settings = JSON.parse(form.querySelector('altcha-widget').getAttribute('configuration'));
    assert.equal(settings.humanInteractionSignature, false);
    assert.equal(settings.serverVerificationFields, false);
    assert.equal(settings.serverVerificationTimeZone, false);
    assert.equal(settings.setCookie, null);
    assert.equal(settings.verifyUrl, null);
    api.destroy();
    dom.window.close();
});

test('custom submit action and locale prefix use the actual destination', () => {
    const { dom, api } = setup('<form method="post" action="/ignored"><button formaction="/de/form/contact">Send</button></form>');
    const form = dom.window.document.querySelector('form');
    assert.equal(targetFor(form, form.querySelector('button'), { ...config, basePath: '/de' }, dom.window.location), '/form/contact');
    api.destroy();
    dom.window.close();
});

test('GET forms, checkout and newsletter unsubscribe stay untouched', () => {
    const { dom, api } = setup('<form method="get" action="/form/contact"></form><form method="post" action="/checkout/order"></form><form method="post" action="/form/newsletter"><input name="option" value="unsubscribe"></form>');
    assert.equal(dom.window.document.querySelectorAll('altcha-widget').length, 0);
    api.destroy();
    dom.window.close();
});

test('native submit waits for proof, retains submitter and resumes once', async () => {
    const { dom, api, calls } = setup();
    const form = dom.window.document.querySelector('form');
    const button = form.querySelector('button');
    const received = [];
    form.addEventListener('submit', event => {
        event.preventDefault();
        received.push({ submitter: event.submitter, token: new dom.window.FormData(form).get('starcode') });
    });
    form.requestSubmit(button);
    form.requestSubmit(button);
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(calls(), 1);
    assert.deepEqual(received, [{ submitter: button, token: 'proof-1' }]);
    api.destroy();
    dom.window.close();
});

test('failed challenge stops submission and provides recoverable feedback', async () => {
    const { dom, api } = setup();
    const form = dom.window.document.querySelector('form');
    form.querySelector('altcha-widget').verify = async () => null;
    let submissions = 0;
    form.addEventListener('submit', event => { event.preventDefault(); submissions += 1; });
    form.requestSubmit();
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(submissions, 0);
    assert.equal(form.querySelector('[role="alert"]').hidden, false);
    assert.equal(form.hasAttribute('aria-busy'), false);
    assert.equal(new dom.window.FormData(form).get('starcode'), '');
    api.destroy();
    dom.window.close();
});

test('changing action to another origin removes the local widget', async () => {
    const { dom, api } = setup();
    const form = dom.window.document.querySelector('form');
    form.action = 'https://other.invalid/newsletter';
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(form.querySelector('altcha-widget'), null);
    api.destroy();
    dom.window.close();
});
