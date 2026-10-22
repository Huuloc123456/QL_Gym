import { initCarousel } from './carousel.js';
import { parseAssistantAnswer } from './chat-answer.js';
import { formatLastTrainingDay } from './membership-period.js';

const menuButton = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

const navigationGroups = document.querySelectorAll('details[name="gym-primary-navigation"]');

if (navigationGroups.length > 0) {
    document.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('details[name="gym-primary-navigation"]')) {
            return;
        }

        navigationGroups.forEach((group) => {
            group.open = false;
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openGroup = [...navigationGroups].find((group) => group.open);

        if (openGroup) {
            openGroup.open = false;
            openGroup.querySelector('summary')?.focus();
        }
    });
}

if (menuButton && menu) {
    const setMenuExpanded = (isExpanded) => {
        menuButton.setAttribute('aria-expanded', String(isExpanded));
        menuButton.setAttribute('aria-label', isExpanded ? 'Đóng menu' : 'Mở menu');
        menu.classList.toggle('hidden', !isExpanded);
    };

    menuButton.addEventListener('click', () => {
        const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
        setMenuExpanded(!isExpanded);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
            setMenuExpanded(false);
            menuButton.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && !menuButton.contains(event.target)) {
            setMenuExpanded(false);
        }
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setMenuExpanded(false));
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', () => setMenuExpanded(false));
}

const chatWidget = document.querySelector('[data-gym-chatbot]');

if (chatWidget) {
    const panel = chatWidget.querySelector('[data-chat-panel]');
    const openButton = chatWidget.querySelector('[data-chat-open]');
    const closeButton = chatWidget.querySelector('[data-chat-close]');
    const form = chatWidget.querySelector('[data-chat-form]');
    const input = chatWidget.querySelector('[data-chat-input]');
    const sendButton = chatWidget.querySelector('[data-chat-send]');
    const messages = chatWidget.querySelector('[data-chat-messages]');
    const status = chatWidget.querySelector('[data-chat-status]');
    const suggestions = chatWidget.querySelector('[data-chat-suggestions]');
    let isSending = false;

    const setOpen = (isOpen) => {
        panel.hidden = !isOpen;
        openButton.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            input.focus();
        } else {
            openButton.focus();
        }
    };

    const appendMessage = (role, text, productCards = []) => {
        const article = document.createElement('article');
        article.className = `gym-chat-message gym-chat-message--${role} space-y-2`;

        if (role === 'assistant') {
            parseAssistantAnswer(text).forEach((block) => {
                if (block.type === 'list') {
                    const list = document.createElement('ul');
                    list.className = 'list-disc space-y-1 pl-5';
                    block.items.forEach((item) => {
                        const listItem = document.createElement('li');
                        listItem.textContent = item;
                        list.append(listItem);
                    });
                    article.append(list);
                    return;
                }

                const paragraph = document.createElement('p');
                paragraph.textContent = block.text;
                article.append(paragraph);
            });

            if (productCards.length > 0) {
                const cards = document.createElement('div');
                cards.className = 'mt-3 grid gap-2';
                cards.setAttribute('aria-label', 'Sản phẩm liên quan');

                productCards.forEach((product) => {
                    const card = document.createElement('article');
                    card.className = 'grid grid-cols-[4.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 rounded-xl border border-slate-200 bg-white p-2.5';

                    const image = document.createElement('img');
                    image.className = 'size-[4.5rem] rounded-lg bg-slate-50 object-contain';
                    image.src = product.image_url || product.image_placeholder_url;
                    image.alt = `Ảnh sản phẩm ${product.name}`;
                    image.width = 72;
                    image.height = 72;
                    image.loading = 'lazy';
                    image.addEventListener('error', () => {
                        image.src = product.image_placeholder_url;
                    }, { once: true });

                    const details = document.createElement('div');
                    details.className = 'min-w-0 self-center';

                    const name = document.createElement('p');
                    name.className = 'text-sm font-semibold leading-5 text-gym-ink';
                    name.textContent = product.name;

                    const price = document.createElement('p');
                    price.className = 'mt-1 text-sm font-bold text-gym-blue';
                    price.textContent = product.price;

                    const availability = document.createElement('p');
                    availability.className = `mt-0.5 text-xs ${product.availability === 'Còn hàng' ? 'text-emerald-700' : 'text-slate-500'}`;
                    availability.textContent = product.availability;

                    details.append(name, price, availability);

                    const detailLink = document.createElement('a');
                    detailLink.className = 'col-span-2 inline-flex min-h-9 items-center justify-center rounded-lg bg-gym-blue px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-gym-blue-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gym-blue';
                    detailLink.href = product.detail_url;
                    detailLink.textContent = 'Chi tiết';

                    card.append(image, details, detailLink);
                    cards.append(card);
                });

                article.append(cards);
            }
        } else {
            const paragraph = document.createElement('p');
            paragraph.textContent = text;
            article.append(paragraph);
        }

        messages.append(article);
        messages.scrollTop = messages.scrollHeight;
        return article;
    };

    const updateForm = () => {
        sendButton.disabled = isSending || input.value.trim().length === 0;
    };

    openButton.addEventListener('click', () => setOpen(true));
    closeButton.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
        }
    });

    suggestions.querySelectorAll('[data-chat-prompt]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.chatPrompt;
            updateForm();
            form.requestSubmit();
        });
    });

    input.addEventListener('input', updateForm);
    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) {
            return;
        }

        event.preventDefault();
        if (!isSending) {
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || isSending) {
            return;
        }

        appendMessage('user', message);
        input.value = '';
        isSending = true;
        status.textContent = 'Đang tìm câu trả lời…';
        suggestions.hidden = true;
        const pendingMessage = appendMessage('assistant', 'Đang trả lời…');
        updateForm();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message }),
            });
            const result = await response.json();
            pendingMessage.remove();

            if (!response.ok) {
                appendMessage('assistant', result.message || 'Trợ lý đang bận. Bạn thử lại sau nhé.');
            } else {
                appendMessage('assistant', result.answer, Array.isArray(result.products) ? result.products : []);
            }
        } catch {
            pendingMessage.remove();
            appendMessage('assistant', 'Chưa kết nối được với trợ lý. Bạn thử lại sau nhé.');
        } finally {
            isSending = false;
            status.textContent = 'Không gửi mật khẩu hoặc mã OTP.';
            if (!panel.hidden) {
                input.focus();
            }
            updateForm();
        }
    });

    updateForm();
}

document.querySelectorAll('[data-carousel]').forEach(initCarousel);

document.querySelectorAll('[data-membership-purchase]').forEach((form) => {
    const startDateInput = form.querySelector('[data-start-date]');
    const lastTrainingDay = form.querySelector('[data-last-training-day]');
    const durationMonths = Number(form.dataset.durationMonths);

    if (!startDateInput || !lastTrainingDay) {
        return;
    }

    const updateLastTrainingDay = () => {
        if (!startDateInput.value) {
            lastTrainingDay.textContent = 'Chọn ngày bắt đầu để xem ngày tập cuối.';
            return;
        }

        lastTrainingDay.textContent = 'Ngày tập cuối dự kiến: ' + formatLastTrainingDay(startDateInput.value, durationMonths);
    };

    startDateInput.addEventListener('input', updateLastTrainingDay);
    updateLastTrainingDay();
});

import { initCheckout } from './checkout.js';
initCheckout(document.querySelector('[data-checkout]'));
