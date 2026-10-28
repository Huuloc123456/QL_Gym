import assert from 'node:assert/strict';
import test from 'node:test';
import { checkoutDraftValues, initCheckout } from '../../resources/js/checkout.js';

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

test('cart quantity updates forward the current checkout choices but omit disabled and internal fields', () => {
    const controls = [
        { name: 'mode', value: 'delivery', disabled: false },
        { name: 'address_id', value: '24', disabled: false },
        { name: 'recipient', value: 'Khách QA', disabled: false },
        { name: 'phone', value: '0900000000', disabled: false },
        { name: 'payment_method', value: 'bank_transfer', disabled: false },
        { name: 'start_date', value: '2026-11-01', disabled: false },
        { name: 'address_id', value: '9', disabled: true },
        { name: '_token', value: 'secret', disabled: false },
        { name: 'token', value: 'quote-id', disabled: false },
    ];

    assert.deepEqual(checkoutDraftValues({ querySelectorAll: () => controls }), [
        ['mode', 'delivery'],
        ['address_id', '24'],
        ['recipient', 'Khách QA'],
        ['phone', '0900000000'],
        ['payment_method', 'bank_transfer'],
        ['start_date', '2026-11-01'],
    ]);
});
