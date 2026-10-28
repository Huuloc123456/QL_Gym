import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

let parseAssistantAnswer;

try {
    ({ parseAssistantAnswer } = await import('../../resources/js/chat-answer.js'));
} catch (error) {
    if (error.code !== 'ERR_MODULE_NOT_FOUND') {
        throw error;
    }
}

test('keeps answer prose separate from dash-formatted bullet points', () => {
    const answer = 'Gói tháng theo tháng lịch. - **Ngày hết hạn** là ngày tương ứng. - Ngày tập cuối liền trước.';

    assert.deepEqual(parseAssistantAnswer?.(answer) ?? null, [
        { type: 'paragraph', text: 'Gói tháng theo tháng lịch.' },
        { type: 'list', items: ['Ngày hết hạn là ngày tương ứng.', 'Ngày tập cuối liền trước.'] },
    ]);
});

test('does not turn a dash inside a normal sentence into a list', () => {
    assert.deepEqual(parseAssistantAnswer?.('Gói dài hạn - tiết kiệm chi phí.') ?? null, [
        { type: 'paragraph', text: 'Gói dài hạn - tiết kiệm chi phí.' },
    ]);
});

test('does not access the removed suggestion chips when submitting a chat message', async () => {
    const appSource = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');

    assert.doesNotMatch(appSource, /suggestions\.hidden/);
});
