export function initCarousel(carousel) {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const indicators = [...carousel.querySelectorAll('[data-carousel-go]')];
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let paused = motion.matches;
    let hovering = false;
    let focused = false;
    let loading = false;
    let cursor = 0;
    let timer;

    if (slides.length < 2) {
        return;
    }

    const showSlide = async (index) => {
        if (loading) {
            return;
        }

        cursor = (index + slides.length) % slides.length;
        loading = true;

        try {
            await slides[cursor].querySelector('img')?.decode();
            slides.forEach((slide, slideIndex) => slide.classList.toggle('hidden', slideIndex !== cursor));
            indicators.forEach((indicator, slideIndex) => indicator.setAttribute('aria-current', String(slideIndex === cursor)));
        } catch {
            // Keep the current image visible; the next tick tries the following slide.
        } finally {
            loading = false;
        }
    };

    const updateTimer = () => {
        clearInterval(timer);
        if (!paused && !hovering && !focused && !document.hidden) {
            timer = setInterval(() => showSlide(cursor + 1), 5000);
        }
    };

    indicators.forEach((indicator, index) => indicator.addEventListener('click', () => {
        showSlide(index);
        updateTimer();
    }));
    carousel.addEventListener('pointerenter', () => { hovering = true; updateTimer(); });
    carousel.addEventListener('pointerleave', () => { hovering = false; updateTimer(); });
    carousel.addEventListener('focusin', () => { focused = true; updateTimer(); });
    carousel.addEventListener('focusout', (event) => { focused = carousel.contains(event.relatedTarget); updateTimer(); });
    document.addEventListener('visibilitychange', updateTimer);
    motion.addEventListener('change', () => { paused = motion.matches; updateTimer(); });
    updateTimer();
}
