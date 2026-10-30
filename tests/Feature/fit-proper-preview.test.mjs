import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/fit-proper.js', import.meta.url), 'utf8');
const keys = ['adaptability', 'authenticity', 'contributing', 'learning', 'diversity', 'job_mastery', 'improvement', 'anti_bribery', 'anti_gratuity', 'conflict'];
function candidate(id, saved = {}) {
    const outputs = Object.fromEntries(['core', 'eligibility', 'average'].map(key => [key, { value: '-' }]));
    const article = {
        dataset: { participantId: String(id) }, outputs,
        querySelectorAll: () => Object.values(article.fields),
        querySelector: selector => outputs[selector.match(/="(.*?)"/)[1]],
    };
    article.fields = Object.fromEntries(keys.map(key => [key, {
        dataset: { fpIndicator: key }, value: String(saved[key] ?? ''), validity: { valid: true },
        matches: selector => selector === '[data-fp-indicator]',
        closest: selector => selector.includes('data-participant-id') ? article : null,
    }]));
    return article;
}
function page(candidates, readyState = 'complete') {
    const listeners = {};
    runInNewContext(source, { document: {
        readyState,
        querySelectorAll: () => candidates,
        addEventListener: (type, callback) => (listeners[type] ??= []).push(callback),
    } });
    return {
        loaded: () => (listeners.DOMContentLoaded ?? []).forEach(callback => callback()),
        input: (article, key, value) => {
            article.fields[key].value = String(value);
            listeners.input.forEach(callback => callback({ target: article.fields[key] }));
        },
    };
}

test('input updates partial competencies, eligibility and average; clearing ignores empty values', () => {
    const a = candidate(10);
    const ui = page([a]);
    assert.equal(a.outputs.core.value, '-');
    assert.equal(a.outputs.average.value, '-');
    ui.input(a, 'adaptability', 4);
    assert.equal(a.outputs.core.value, '4,0');
    ui.input(a, 'authenticity', 5);
    assert.equal(a.outputs.core.value, '4,5');
    ui.input(a, 'adaptability', 3);
    assert.equal(a.outputs.core.value, '4,0');
    ui.input(a, 'adaptability', '');
    assert.equal(a.outputs.core.value, '5,0');
    ui.input(a, 'anti_bribery', 4);
    assert.equal(a.outputs.eligibility.value, '4,0');
    ui.input(a, 'anti_gratuity', 2);
    assert.equal(a.outputs.eligibility.value, '3,0');
    assert.equal(a.outputs.average.value, '4,0');
    ui.input(a, 'job_mastery', 1);
    assert.equal(a.outputs.average.value, '3,0');
    ui.input(a, 'authenticity', '');
    assert.equal(a.outputs.core.value, '-');
    assert.equal(a.outputs.average.value, '2,0');
});

test('saved values initialize on DOMContentLoaded and candidates remain isolated', () => {
    const values = { adaptability: 4, authenticity: 3, contributing: 4, learning: 5, diversity: 4,
        job_mastery: 4.5, improvement: 3.5, anti_bribery: 4, anti_gratuity: 5, conflict: 3 };
    const a = candidate(10, values), b = candidate(11, Object.fromEntries(keys.map(key => [key, 2])));
    const ui = page([a, b], 'loading');
    assert.equal(a.outputs.core.value, '-');
    ui.loaded();
    assert.equal(a.outputs.core.value, '4,0');
    assert.equal(a.outputs.eligibility.value, '4,0');
    assert.equal(a.outputs.average.value, '4,0');
    assert.equal(b.outputs.average.value, '2,0');
    ui.input(a, 'job_mastery', 1);
    assert.equal(a.outputs.average.value, '3,1');
    assert.equal(b.outputs.average.value, '2,0');
});

test('zero, out-of-range and nonnumeric values never contribute to preview', () => {
    const a = candidate(22), ui = page([a]);
    for (const value of [0, -1, 5.1, 'abc', 'Infinity', '']) {
        ui.input(a, 'adaptability', value);
        assert.equal(a.outputs.core.value, '-');
        assert.equal(a.outputs.average.value, '-');
    }
    ui.input(a, 'adaptability', 4);
    ui.input(a, 'authenticity', 0);
    assert.equal(a.outputs.core.value, '4,0');
});
