const menuButton = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

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

    window.matchMedia('(min-width: 1280px)').addEventListener('change', () => setMenuExpanded(false));
}

document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const status = carousel.querySelector('[data-carousel-status]');
    const buttons = [...carousel.querySelectorAll('[data-carousel-prev], [data-carousel-next]')];
    let activeIndex = 0;
    let isLoading = false;

    if (!slides.length) {
        return;
    }

    const showSlide = async (index) => {
        if (isLoading) {
            return;
        }

        const nextIndex = (index + slides.length) % slides.length;
        const image = slides[nextIndex].querySelector('img');
        isLoading = true;
        carousel.setAttribute('aria-busy', 'true');
        buttons.forEach((button) => { button.disabled = true; });

        try {
            if (image?.dataset.src) {
                image.srcset = image.dataset.srcset ?? '';
                image.src = image.dataset.src;
                await image.decode();
                delete image.dataset.src;
                delete image.dataset.srcset;
            }

            activeIndex = nextIndex;
            slides.forEach((slide, slideIndex) => {
                slide.hidden = slideIndex !== activeIndex;
            });

            if (status) {
                status.textContent = 'Ảnh ' + (activeIndex + 1) + ' trên ' + slides.length;
            }
        } catch {
            if (status) {
                status.textContent = 'Không tải được ảnh. Bạn có thể thử lại.';
            }
        } finally {
            isLoading = false;
            carousel.setAttribute('aria-busy', 'false');
            buttons.forEach((button) => { button.disabled = slides.length < 2; });
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
