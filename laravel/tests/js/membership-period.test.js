import assert from 'node:assert/strict';
import test from 'node:test';
import { formatLastTrainingDay } from '../../resources/js/membership-period.js';

for (const [startsOn, months, expected] of [
    ['2026-01-31', 1, '28/02/2026'],
    ['2028-01-30', 1, '29/02/2028'],
    ['2028-02-29', 12, '28/02/2029'],
    ['2026-01-31', 2, '30/03/2026'],
    ['2026-08-31', 1, '30/09/2026'],
    ['2026-10-02', 1, '01/11/2026'],
]) {
    test(`${startsOn} plus ${months} months shows last training day ${expected}`, () => {
        assert.equal(formatLastTrainingDay(startsOn, months), expected);
    });
}
