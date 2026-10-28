const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const vm = require('node:vm');
const { resolve } = require('node:path');

const template = readFileSync(resolve(__dirname, '../../src/view/frontend/templates/magewire-features/support-hyva-checkout-backwards-compatibility/magewire-ancestor-events.phtml'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?php[\s\S]*?\?>/g, '');

function environment() {
    const components = new Map();
    const calls = [];
    let effect;
    const Magewire = {
        hook(name, callback) {
            assert.equal(name, 'effect');
            effect = callback;
        },
        find(id) { return components.get(id)?.$wire; },
        dispatchTo(...args) { calls.push(['to', ...args]); }
    };
    vm.runInNewContext(script, { Magewire });

    function element(id, parentElement = null) {
        return { parentElement, getAttribute: name => name === 'wire:id' ? id : null };
    }
    function component(id, parentElement = null) {
        const result = {
            el: element(id, parentElement),
            $wire: {
                $dispatchSelf(...args) { calls.push([id, 'self', ...args]); },
                $commit() { calls.push([id, 'commit']); }
            }
        };
        components.set(id, result);
        return result;
    }
    return { effect, calls, component, element };
}

test('upward events reach each DOM ancestor without reaching self, siblings or unrelated components', () => {
    const { effect, calls, component, element } = environment();
    const root = component('root');
    const parent = component('parent', root.el);
    const child = component('child', element(null, parent.el));
    component('sibling', parent.el);
    component('unrelated');
    effect({ component: child, effects: { hyvaCheckoutAncestorEvents: [{ name: 'pair', params: ['left', 'right'] }] } });

    assert.deepEqual(calls, [
        ['parent', 'self', 'pair', ['left', 'right']],
        ['root', 'self', 'pair', ['left', 'right']]
    ]);
});

test('refresh commits ancestors without needing a refresh listener', () => {
    const { effect, calls, component } = environment();
    const root = component('root');
    const parent = component('parent', root.el);
    const child = component('child', parent.el);
    effect({ component: child, effects: { hyvaCheckoutAncestorEvents: [{ name: '$refresh', params: [] }] } });

    assert.deepEqual(calls, [['parent', 'commit'], ['root', 'commit']]);
});

test('a root component has no ancestors and does not throw', () => {
    const { effect, calls, component } = environment();
    effect({ component: component('root'), effects: { hyvaCheckoutAncestorEvents: [{ name: 'pair' }, { name: '$refresh' }] } });

    assert.deepEqual(calls, []);
});

test('normal v3 dispatch effects are left to the v3 runtime', () => {
    const { effect, calls, component } = environment();
    effect({ component: component('root'), effects: { dispatches: [{ name: 'native', params: {} }] } });

    assert.deepEqual(calls, []);
});

test('event order and explicit null payloads are preserved', () => {
    const { effect, calls, component } = environment();
    const parent = component('parent');
    const child = component('child', parent.el);
    effect({ component: child, effects: { hyvaCheckoutAncestorEvents: [
        { name: 'nullable', params: [null] },
        { name: 'next', params: { value: 42 } }
    ] } });

    assert.deepEqual(calls, [['parent', 'self', 'nullable', [null]], ['parent', 'self', 'next', { value: 42 }]]);
});

test('explicit Event scope modifiers use the v3 self and target delivery paths', () => {
    const { effect, calls, component } = environment();
    const parent = component('parent');
    const child = component('child', parent.el);
    effect({ component: child, effects: { hyvaCheckoutAncestorEvents: [
        { name: 'scoped', params: [1], self: true },
        { name: 'scoped', params: [2], to: 'target' }
    ] } });

    assert.deepEqual(calls, [['child', 'self', 'scoped', [1]], ['to', 'target', 'scoped', [2]]]);
});
