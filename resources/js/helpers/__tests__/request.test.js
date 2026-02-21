/**
 * request.test.js — Exhaustive tests for the request helper module.
 *
 * Covers: compileTreeToPayload, prepareRequest, treeHasFiles
 */
import { describe, it, expect } from 'vitest';
import { compileTreeToPayload, prepareRequest, treeHasFiles } from '../request.js';

// ═══════════════════════════════════════════════════════════════════════════
// compileTreeToPayload
// ═══════════════════════════════════════════════════════════════════════════

describe('compileTreeToPayload', () => {
    it('compiles flat scalar nodes', () => {
        const tree = [
            { key: 'name', type: 'text', value: 'Ahmed', enabled: true },
            { key: 'age', type: 'number', value: '25', enabled: true },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.name).toBe('Ahmed');
        expect(payload.age).toBe(25); // Cast to number
    });

    it('skips disabled nodes', () => {
        const tree = [
            { key: 'name', type: 'text', value: 'Ahmed', enabled: true },
            { key: 'secret', type: 'text', value: 'hidden', enabled: false },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.name).toBe('Ahmed');
        expect(payload).not.toHaveProperty('secret');
    });

    it('skips nodes without keys', () => {
        const tree = [
            { key: '', type: 'text', value: 'no-key', enabled: true },
            { key: 'valid', type: 'text', value: 'yes', enabled: true },
        ];
        const payload = compileTreeToPayload(tree);

        expect(Object.keys(payload)).toEqual(['valid']);
    });

    it('compiles nested objects', () => {
        const tree = [
            {
                key: 'address', type: 'object', enabled: true,
                children: [
                    { key: 'street', type: 'text', value: '123 Main', enabled: true },
                    { key: 'city', type: 'text', value: 'Cairo', enabled: true },
                ],
            },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.address).toEqual({ street: '123 Main', city: 'Cairo' });
    });

    it('compiles arrays', () => {
        const tree = [
            {
                key: 'tags', type: 'array', enabled: true,
                children: [
                    { key: '', type: 'text', value: 'php', enabled: true },
                    { key: '', type: 'text', value: 'react', enabled: true },
                ],
            },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.tags).toEqual(['php', 'react']);
    });

    it('compiles arrays with object children', () => {
        const tree = [
            {
                key: 'items', type: 'array', enabled: true,
                children: [
                    {
                        key: '', type: 'object', enabled: true,
                        children: [
                            { key: 'name', type: 'text', value: 'Item 1', enabled: true },
                        ],
                    },
                ],
            },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.items).toEqual([{ name: 'Item 1' }]);
    });

    it('skips disabled children in arrays', () => {
        const tree = [
            {
                key: 'tags', type: 'array', enabled: true,
                children: [
                    { key: '', type: 'text', value: 'keep', enabled: true },
                    { key: '', type: 'text', value: 'skip', enabled: false },
                ],
            },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.tags).toEqual(['keep']);
    });

    it('casts boolean values correctly', () => {
        const tree = [
            { key: 'active', type: 'boolean', value: 'true', enabled: true },
            { key: 'hidden', type: 'boolean', value: 'false', enabled: true },
        ];
        const payload = compileTreeToPayload(tree);

        expect(payload.active).toBe(true);
        expect(payload.hidden).toBe(false);
    });

    it('returns empty object for non-array input', () => {
        expect(compileTreeToPayload(null)).toEqual({});
        expect(compileTreeToPayload(undefined)).toEqual({});
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// prepareRequest
// ═══════════════════════════════════════════════════════════════════════════

describe('prepareRequest', () => {
    const postRoute = {
        uri: 'api/users',
        methods: ['POST'],
    };

    const getRoute = {
        uri: 'api/users',
        methods: ['GET'],
    };

    const paramRoute = {
        uri: 'api/users/{user}/posts/{post?}',
        methods: ['GET'],
    };

    it('builds correct URL for POST', () => {
        const { url, method, body } = prepareRequest(postRoute, { name: 'Test' }, {}, {});
        expect(url).toBe('/api/users');
        expect(method).toBe('POST');
        expect(body).toBe(JSON.stringify({ name: 'Test' }));
    });

    it('appends query string for GET', () => {
        const { url, method } = prepareRequest(getRoute, { search: 'hello' }, {}, {});
        expect(url).toContain('?search=hello');
        expect(method).toBe('GET');
    });

    it('replaces required path params', () => {
        const { url } = prepareRequest(paramRoute, {}, { user: '42', post: '' }, {});
        expect(url).toContain('/42/');
    });

    it('removes optional path params when empty', () => {
        const { url } = prepareRequest(paramRoute, {}, { user: '42' }, {});
        expect(url).not.toContain('{post');
    });

    it('sets Content-Type: application/json for non-file POST', () => {
        const { hdrs } = prepareRequest(postRoute, { name: 'Test' }, {}, {});
        expect(hdrs['Content-Type']).toBe('application/json');
    });

    it('merges custom headers', () => {
        const { hdrs } = prepareRequest(postRoute, {}, {}, { 'X-Custom': 'value' });
        expect(hdrs['X-Custom']).toBe('value');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// treeHasFiles
// ═══════════════════════════════════════════════════════════════════════════

describe('treeHasFiles', () => {
    it('returns false for tree without files', () => {
        const tree = [
            { key: 'name', type: 'text', value: 'hello' },
        ];
        expect(treeHasFiles(tree)).toBe(false);
    });

    it('returns true when a node has a File value', () => {
        const file = new File([''], 'test.jpg', { type: 'image/jpeg' });
        const tree = [
            { key: 'avatar', type: 'file', value: file },
        ];
        expect(treeHasFiles(tree)).toBe(true);
    });

    it('detects files in nested children', () => {
        const file = new File([''], 'test.pdf', { type: 'application/pdf' });
        const tree = [
            {
                key: 'group', type: 'object',
                children: [
                    { key: 'doc', type: 'file', value: file },
                ],
            },
        ];
        expect(treeHasFiles(tree)).toBe(true);
    });

    it('returns false for non-array input', () => {
        expect(treeHasFiles(null)).toBe(false);
        expect(treeHasFiles(undefined)).toBe(false);
    });
});
