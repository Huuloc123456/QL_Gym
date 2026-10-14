const menuButton = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

if (menuButton && menu) {
    menuButton.addEventListener('click', () => {
        const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isExpanded));
        menu.classList.toggle('hidden', isExpanded);
    });
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
        article.className = `gym-chat-message gym-chat-message--${role}`;

        const paragraph = document.createElement('p');
        paragraph.textContent = text.replace(/\*\*(.*?)\*\*/g, '$1').replace(/__(.*?)__/g, '$1');
        article.append(paragraph);

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

document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const status = carousel.querySelector('[data-carousel-status]');
    let activeIndex = 0;

    const showSlide = (index) => {
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            slide.hidden = slideIndex !== activeIndex;
        });

        if (status) {
            status.textContent = 'Ảnh ' + (activeIndex + 1) + ' trên ' + slides.length;
        }
    };

    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => showSlide(activeIndex - 1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => showSlide(activeIndex + 1));
    showSlide(0);
});

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

        const [year, month, day] = startDateInput.value.split('-').map(Number);
        const targetMonth = new Date(Date.UTC(year, month - 1 + durationMonths, 1));
        const targetMonthLastDay = new Date(
            Date.UTC(targetMonth.getUTCFullYear(), targetMonth.getUTCMonth() + 1, 0),
        ).getUTCDate();
        const finalDate = new Date(Date.UTC(
            targetMonth.getUTCFullYear(),
            targetMonth.getUTCMonth(),
            Math.min(day, targetMonthLastDay),
        ));
        finalDate.setUTCDate(finalDate.getUTCDate() - 1);

        const formatted = [finalDate.getUTCDate(), finalDate.getUTCMonth() + 1, finalDate.getUTCFullYear()]
            .map((part) => String(part).padStart(2, '0'));

        lastTrainingDay.textContent = 'Ngày tập cuối dự kiến: ' + formatted[0] + '/' + formatted[1] + '/' + formatted[2];
    };

    startDateInput.addEventListener('input', updateLastTrainingDay);
    updateLastTrainingDay();
});
