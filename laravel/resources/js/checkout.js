const checkoutDraftFieldNames = new Set(['mode', 'address_id', 'recipient', 'phone', 'payment_method', 'start_date']);

export function checkoutDraftValues(form) {
    return [...(form?.querySelectorAll('input[name], select[name], textarea[name]') ?? [])]
        .filter((field) => checkoutDraftFieldNames.has(field.name) && !field.disabled)
        .map((field) => [field.name, field.value]);
}

export function initCheckout(root) {
    if (!root) return;
    const mode = root.querySelector('[name="mode"]');
    if (!mode) return;
    const fields = root.querySelector('[data-delivery-fields]');
    const shipping = root.querySelector('[data-shipping-fee]');
    const total = root.querySelector('[data-checkout-total]');
    const checkoutForm = root.querySelector('[data-checkout-form]');
    const changeForms = root.querySelectorAll?.('[data-cart-change]') ?? [];
    const format = (value) => new Intl.NumberFormat('vi-VN').format(value) + 'đ';
    const update = () => {
        const delivery = mode.value === 'delivery' && !!fields;
        if (fields) {
            fields.hidden = !delivery;
            fields.disabled = !delivery;
            fields.querySelectorAll('[data-delivery-required]').forEach((input) => { input.required = delivery; });
        }
        const fee = delivery ? Number(root.dataset.deliveryFee) : 0;
        if (shipping) shipping.textContent = format(fee);
        if (total) total.textContent = format(Number(root.dataset.baseTotal) + fee);
    };

    changeForms.forEach((form) => {
        form.addEventListener('submit', () => {
            checkoutDraftValues(checkoutForm).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                input.dataset.checkoutDraft = '';
                form.append(input);
            });
        });
    });

    mode.addEventListener('change', update);
    update();
}
