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

    const appendMessage = (role, text, sources = []) => {
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
        } else {
            const paragraph = document.createElement('p');
            paragraph.textContent = text;
            article.append(paragraph);
        }

        if (sources.length > 0) {
            const sourceList = document.createElement('div');
            sourceList.className = 'gym-chat-sources';
            const sourceLabel = document.createElement('span');
            sourceLabel.textContent = 'Nguồn';
            sourceList.append(sourceLabel);

            sources.forEach((source) => {
                const sourceChip = document.createElement('span');
                sourceChip.className = 'gym-chat-source';
                sourceChip.textContent = source;
                sourceList.append(sourceChip);
            });

            article.append(sourceList);
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
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (input.value.trim() && !isSending) {
                form.requestSubmit();
            }
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
                appendMessage('assistant', result.answer, Array.isArray(result.sources) ? result.sources : []);
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
