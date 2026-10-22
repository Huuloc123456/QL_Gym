import assert from 'node:assert/strict';
import test from 'node:test';
import { initCarousel } from '../../resources/js/carousel.js';

class Element extends EventTarget {
    attributes = new Map();
    hidden = false;
    classList = { toggle: (_, hidden) => { this.hidden = hidden; } };
    image = { decode: async () => {} };
    querySelector() { return this.image; }
    setAttribute(name, value) { this.attributes.set(name, value); }
    contains(element) { return element === this; }
}

function setup(t, reducedMotion = false) {
    const slides = Array.from({ length: 3 }, (_, index) => Object.assign(new Element(), { hidden: index !== 0 }));
    const indicators = slides.map(() => new Element());
    const pause = new Element();
    const carousel = new Element();
    carousel.querySelectorAll = (selector) => selector === '[data-carousel-slide]' ? slides : indicators;
    carousel.querySelector = () => pause;
    const document = Object.assign(new EventTarget(), { hidden: false });
    const motion = Object.assign(new EventTarget(), { matches: reducedMotion });
    const previous = { document: globalThis.document, window: globalThis.window };
    globalThis.document = document;
    globalThis.window = { matchMedia: () => motion };
    t.after(() => {
        for (const [key, value] of Object.entries(previous)) {
            if (value === undefined) { delete globalThis[key]; } else { globalThis[key] = value; }
        }
    });
    const timers = new Map();
    let id = 0;
    t.mock.method(globalThis, 'setInterval', (callback, delay) => {
        assert.equal(delay, 5000);
        timers.set(++id, callback);
        return id;
    });
    t.mock.method(globalThis, 'clearInterval', (timer) => timers.delete(timer));
    initCarousel(carousel);
    return { slides, indicators, pause, carousel, document, motion, timers, tick: async () => timers.values().next().value() };
}

test('automatically advances every five seconds and wraps to the first image', async (t) => {
    const { slides, indicators, timers, tick } = setup(t);
    await tick();
    assert.deepEqual(slides.map((slide) => slide.hidden), [true, false, true]);
    assert.equal(indicators[1].attributes.get('aria-current'), 'true');
    await tick();
    await tick();
    assert.deepEqual(slides.map((slide) => slide.hidden), [false, true, true]);
    assert.equal(timers.size, 1);
});

test('rotation waits while hovered, focused or in a hidden tab', (t) => {
    const { carousel, document, timers } = setup(t);
    carousel.dispatchEvent(new Event('pointerenter'));
    carousel.dispatchEvent(new Event('focusin'));
    carousel.dispatchEvent(new Event('pointerleave'));
    assert.equal(timers.size, 0);
    carousel.dispatchEvent(new Event('focusout'));
    assert.equal(timers.size, 1);
    document.hidden = true;
    document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(timers.size, 0);
    document.hidden = false;
    document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(timers.size, 1);
});

test('reduced motion starts paused and resumes when the preference changes', (t) => {
    const { timers, motion } = setup(t, true);
    assert.equal(timers.size, 0);
    motion.matches = false;
    motion.dispatchEvent(new Event('change'));
    assert.equal(timers.size, 1);
});

test('a broken image keeps the current banner visible and does not block the next image', async (t) => {
    const { slides, tick } = setup(t);
    slides[1].image.decode = async () => { throw new Error('Image unavailable'); };
    await tick();
    assert.deepEqual(slides.map((slide) => slide.hidden), [false, true, true]);
    await tick();
    assert.deepEqual(slides.map((slide) => slide.hidden), [true, true, false]);
});
