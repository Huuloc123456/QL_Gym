const menuButton = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

if (menuButton && menu) {
    menuButton.addEventListener('click', () => {
        const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isExpanded));
        menu.classList.toggle('hidden', isExpanded);
    });
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
