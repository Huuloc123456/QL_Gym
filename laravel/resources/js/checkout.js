export function initCheckout(root) {
    if (!root) return;
    const mode = root.querySelector('[name="mode"]');
    if (!mode) return;
    const fields = root.querySelector('[data-delivery-fields]');
    const shipping = root.querySelector('[data-shipping-fee]');
    const total = root.querySelector('[data-checkout-total]');
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
    mode.addEventListener('change', update);
    update();
}
