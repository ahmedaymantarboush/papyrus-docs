/**
 * schema.test.js — Exhaustive tests for the schema helper module.
 *
 * Covers: buildInitialTree, resetTreeToDefaults, deepCloneTree,
 *         hydrateFormTreeFromJson, sanitizeTree
 */
import { describe, it, expect } from 'vitest';
import {
    buildInitialTree,
    resetTreeToDefaults,
    deepCloneTree,
    hydrateFormTreeFromJson,
    sanitizeTree,
} from '../schema.js';

// ═══════════════════════════════════════════════════════════════════════════
// deepCloneTree
// ═══════════════════════════════════════════════════════════════════════════

describe('deepCloneTree', () => {
    it('returns an empty array for non-array input', () => {
        expect(deepCloneTree(null)).toEqual([]);
        expect(deepCloneTree(undefined)).toEqual([]);
        expect(deepCloneTree('string')).toEqual([]);
    });

    it('deeply clones a flat tree', () => {
        const tree = [
            { key: 'name', type: 'text', value: 'hello', enabled: true },
            { key: 'age', type: 'number', value: 25, enabled: true },
        ];
        const clone = deepCloneTree(tree);

        expect(clone).toEqual(tree);
        expect(clone).not.toBe(tree);
        expect(clone[0]).not.toBe(tree[0]); // Deep clone, not shallow
    });

    it('recursively clones children', () => {
        const tree = [
            {
                key: 'address', type: 'object', value: undefined, enabled: true,
                children: [
                    { key: 'street', type: 'text', value: '123 Main', enabled: true },
                ],
            },
        ];
        const clone = deepCloneTree(tree);

        expect(clone[0].children).toEqual(tree[0].children);
        expect(clone[0].children).not.toBe(tree[0].children);
    });

    it('strips File values to null', () => {
        const fakeFile = new File([''], 'test.jpg', { type: 'image/jpeg' });
        const tree = [{ key: 'avatar', type: 'file', value: fakeFile, enabled: true }];
        const clone = deepCloneTree(tree);

        expect(clone[0].value).toBeNull();
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// buildInitialTree
// ═══════════════════════════════════════════════════════════════════════════

describe('buildInitialTree', () => {
    it('builds tree from schema params (object format)', () => {
        const params = {
            name: { key: 'name', type: 'text', required: true },
            email: { key: 'email', type: 'email', required: true },
        };
        const tree = buildInitialTree(params);

        expect(tree).toHaveLength(2);
        expect(tree[0].key).toBe('name');
        expect(tree[1].key).toBe('email');
        expect(tree[1].type).toBe('email');
    });

    it('builds tree from schema params (array format)', () => {
        const params = [
            { key: 'name', type: 'text' },
            { key: 'age', type: 'number' },
        ];
        const tree = buildInitialTree(params);

        expect(tree).toHaveLength(2);
        expect(tree[0].key).toBe('name');
        expect(tree[1].type).toBe('number');
    });

    it('uses saved state as source of truth when present', () => {
        const params = [
            { key: 'name', type: 'text' },
            { key: 'email', type: 'email' },
        ];
        const saved = [
            { key: 'name', type: 'text', value: 'Ahmed' },
            { key: 'email', type: 'email', value: 'a@b.com' },
            { key: 'phone', type: 'text', value: '123' }, // user-added
        ];
        const tree = buildInitialTree(params, saved);

        expect(tree).toHaveLength(3); // 3 from saved, preserving the added 'phone'
        expect(tree[0].value).toBe('Ahmed');
        expect(tree[2].key).toBe('phone');
    });

    it('preserves user deletions (saved state smaller than schema)', () => {
        const params = [
            { key: 'name', type: 'text' },
            { key: 'email', type: 'email' },
            { key: 'phone', type: 'text' },
        ];
        const saved = [
            { key: 'name', type: 'text', value: '' },
        ];
        const tree = buildInitialTree(params, saved);

        expect(tree).toHaveLength(1); // Only the saved item
        expect(tree[0].key).toBe('name');
    });

    it('falls back to schema when no saved state', () => {
        const params = [
            { key: 'name', type: 'text' },
        ];
        const tree = buildInitialTree(params, []);

        expect(tree).toHaveLength(1);
        expect(tree[0].key).toBe('name');
    });

    it('handles empty params gracefully', () => {
        const tree = buildInitialTree([], []);
        expect(tree).toEqual([]);
    });

    it('handles null/undefined params gracefully', () => {
        const tree = buildInitialTree(null);
        expect(tree).toEqual([]);
    });

    it('builds nested objects recursively', () => {
        const params = [
            {
                key: 'address', type: 'object',
                schema: [
                    { key: 'street', type: 'text' },
                    { key: 'city', type: 'text' },
                ],
            },
        ];
        const tree = buildInitialTree(params);

        expect(tree[0].type).toBe('object');
        expect(tree[0].children).toHaveLength(2);
        expect(tree[0].children[0].key).toBe('street');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// resetTreeToDefaults
// ═══════════════════════════════════════════════════════════════════════════

describe('resetTreeToDefaults', () => {
    it('resets all values to empty strings', () => {
        const params = [
            { key: 'name', type: 'text', value: 'Ahmed' },
            { key: 'email', type: 'email', value: 'a@b.com' },
        ];
        const reset = resetTreeToDefaults(params);

        expect(reset[0].value).toBe('');
        expect(reset[1].value).toBe('');
    });

    it('sets all nodes to enabled', () => {
        const params = [
            { key: 'name', type: 'text', enabled: false },
        ];
        const reset = resetTreeToDefaults(params);
        expect(reset[0].enabled).toBe(true);
    });

    it('preserves the schema reference', () => {
        const params = [
            { key: 'name', type: 'text' },
        ];
        const reset = resetTreeToDefaults(params);
        expect(reset[0].schema).toBeDefined();
        expect(reset[0].schema.key).toBe('name');
    });

    it('recursively resets nested objects', () => {
        const params = [
            {
                key: 'address', type: 'object',
                schema: [
                    { key: 'street', type: 'text', value: '123 Main' },
                ],
            },
        ];
        const reset = resetTreeToDefaults(params);

        expect(reset[0].children[0].value).toBe('');
    });

    it('handles object format params', () => {
        const params = {
            name: { key: 'name', type: 'text', value: 'test' },
        };
        const reset = resetTreeToDefaults(params);
        expect(reset[0].value).toBe('');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// hydrateFormTreeFromJson
// ═══════════════════════════════════════════════════════════════════════════

describe('hydrateFormTreeFromJson', () => {
    it('hydrates scalar values', () => {
        const json = { name: 'Ahmed', age: 25, active: true };
        const tree = hydrateFormTreeFromJson(json);

        expect(tree).toHaveLength(3);
        expect(tree[0].key).toBe('name');
        expect(tree[0].value).toBe('Ahmed');
        expect(tree[1].type).toBe('number');
        expect(tree[1].value).toBe(25);
        expect(tree[2].type).toBe('boolean');
    });

    it('hydrates nested objects', () => {
        const json = { address: { street: '123 Main', city: 'Cairo' } };
        const tree = hydrateFormTreeFromJson(json);

        expect(tree[0].type).toBe('object');
        expect(tree[0].children).toHaveLength(2);
        expect(tree[0].children[0].key).toBe('street');
    });

    it('hydrates arrays with scalars', () => {
        const json = { tags: ['php', 'laravel', 'react'] };
        const tree = hydrateFormTreeFromJson(json);

        expect(tree[0].type).toBe('array');
        expect(tree[0].children).toHaveLength(3);
        expect(tree[0].children[0].value).toBe('php');
    });

    it('hydrates arrays with objects', () => {
        const json = { items: [{ name: 'Item 1' }, { name: 'Item 2' }] };
        const tree = hydrateFormTreeFromJson(json);

        expect(tree[0].type).toBe('array');
        expect(tree[0].children[0].type).toBe('object');
        expect(tree[0].children[0].children[0].value).toBe('Item 1');
    });

    it('handles null values', () => {
        const json = { name: null };
        const tree = hydrateFormTreeFromJson(json);

        expect(tree[0].type).toBe('text');
        expect(tree[0].value).toBe('');
    });

    it('preserves existing type when available', () => {
        const existing = [{ key: 'email', type: 'email', value: '' }];
        const json = { email: 'a@b.com' };
        const tree = hydrateFormTreeFromJson(json, existing);

        expect(tree[0].type).toBe('email');
    });

    it('returns existing tree for non-object input', () => {
        const existing = [{ key: 'a', type: 'text', value: '' }];
        expect(hydrateFormTreeFromJson(null, existing)).toBe(existing);
        expect(hydrateFormTreeFromJson('string', existing)).toBe(existing);
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// sanitizeTree
// ═══════════════════════════════════════════════════════════════════════════

describe('sanitizeTree', () => {
    it('strips File values to null', () => {
        const fakeFile = new File([''], 'test.pdf', { type: 'application/pdf' });
        const tree = [{ key: 'doc', type: 'file', value: fakeFile, enabled: true }];
        const safe = sanitizeTree(tree);

        expect(safe[0].value).toBeNull();
    });

    it('strips schema references', () => {
        const tree = [{ key: 'name', type: 'text', value: '', schema: { key: 'name' } }];
        const safe = sanitizeTree(tree);

        expect(safe[0].schema).toBeUndefined();
    });

    it('recursively sanitizes children', () => {
        const fakeFile = new File([''], 'test.png', { type: 'image/png' });
        const tree = [{
            key: 'group', type: 'object', value: undefined,
            children: [
                { key: 'photo', type: 'file', value: fakeFile, schema: {} },
            ],
        }];
        const safe = sanitizeTree(tree);

        expect(safe[0].children[0].value).toBeNull();
        expect(safe[0].children[0].schema).toBeUndefined();
    });

    it('returns empty array for non-array input', () => {
        expect(sanitizeTree(null)).toEqual([]);
        expect(sanitizeTree(undefined)).toEqual([]);
    });
});
