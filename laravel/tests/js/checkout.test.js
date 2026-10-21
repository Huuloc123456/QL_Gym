import assert from 'node:assert/strict';
import test from 'node:test';
import { initCheckout } from '../../resources/js/checkout.js';

function fixture(modeValue = 'pickup', hasFields = true) {
    let onChange;
    const required = { required: false };
    const mode = { value: modeValue, addEventListener: (_, fn) => { onChange = fn; } };
    const fields = { hidden: false, disabled: false, querySelectorAll: () => [required] };
    const shipping = { textContent: '' };
    const total = { textContent: '' };
    const elements = {'[name="mode"]': mode, '[data-delivery-fields]': hasFields ? fields : null, '[data-shipping-fee]': shipping, '[data-checkout-total]': total};
    const root = {dataset: { baseTotal: '90000', deliveryFee: '30000' }, querySelector: (key) => elements[key]};
    initCheckout(root);
    return { mode, fields, shipping, total, required, change: () => onChange() };
}

test('pickup hides and disables address fields and has no delivery fee', () => {
    const f = fixture();
    assert.equal(f.fields.hidden, true);
    assert.equal(f.fields.disabled, true);
    assert.equal(f.required.required, false);
    assert.equal(f.shipping.textContent, '0đ');
    assert.equal(f.total.textContent, '90.000đ');
});

test('delivery requires address fields and updates total without applying discount to shipping', () => {
    const f = fixture();
    f.mode.value = 'delivery'; f.change();
    assert.equal(f.fields.hidden, false);
    assert.equal(f.fields.disabled, false);
    assert.equal(f.required.required, true);
    assert.equal(f.total.textContent, '120.000đ');
    f.mode.value = 'pickup'; f.change();
    assert.equal(f.total.textContent, '90.000đ');
});

test('service-only checkout cannot acquire a shipping fee', () => {
    const f = fixture('delivery', false);
    assert.equal(f.total.textContent, '90.000đ');
    initCheckout(null);
});
