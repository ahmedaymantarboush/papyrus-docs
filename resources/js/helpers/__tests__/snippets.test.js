/**
 * snippets.test.js — Exhaustive tests for the snippet generator.
 *
 * Covers: generateSnippet (curl, php, js, python)
 */
import { describe, it, expect } from 'vitest';
import { generateSnippet } from '../snippets.js';

const postRoute = { uri: 'api/users', methods: ['POST'] };
const getRoute = { uri: 'api/users', methods: ['GET'] };
const paramRoute = { uri: 'api/users/{user}/posts/{post?}', methods: ['GET'] };

const formValues = { name: 'Ahmed', email: 'a@b.com' };
const emptyForm = {};
const headers = { 'Authorization': 'Bearer token123' };

// ═══════════════════════════════════════════════════════════════════════════
// cURL Snippets
// ═══════════════════════════════════════════════════════════════════════════

describe('generateSnippet — curl', () => {
    it('generates a basic curl POST', () => {
        const snippet = generateSnippet('curl', postRoute, formValues, {});
        expect(snippet).toContain('curl -X POST');
        expect(snippet).toContain('{{base_url}}/api/users');
        expect(snippet).toContain('"name"');
        expect(snippet).toContain('"Ahmed"');
    });

    it('includes custom headers', () => {
        const snippet = generateSnippet('curl', postRoute, formValues, {}, headers);
        expect(snippet).toContain('-H "Authorization: Bearer token123"');
    });

    it('replaces path parameters', () => {
        const snippet = generateSnippet('curl', paramRoute, emptyForm, { user: '42' });
        expect(snippet).toContain('/42/');
        expect(snippet).not.toContain('{user}');
    });

    it('generates GET without body', () => {
        const snippet = generateSnippet('curl', getRoute, emptyForm, {});
        expect(snippet).toContain('curl -X GET');
        expect(snippet).not.toContain("-d '");
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// PHP Snippets
// ═══════════════════════════════════════════════════════════════════════════

describe('generateSnippet — php', () => {
    it('generates a GuzzleHttp snippet', () => {
        const snippet = generateSnippet('php', postRoute, formValues, {});
        expect(snippet).toContain('GuzzleHttp');
        expect(snippet).toContain("'POST'");
        expect(snippet).toContain("'json'");
    });

    it('includes headers in PHP snippet', () => {
        const snippet = generateSnippet('php', postRoute, formValues, {}, headers);
        expect(snippet).toContain("'headers'");
        expect(snippet).toContain('Authorization');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// JavaScript Snippets
// ═══════════════════════════════════════════════════════════════════════════

describe('generateSnippet — js', () => {
    it('generates a fetch() snippet', () => {
        const snippet = generateSnippet('js', postRoute, formValues, {});
        expect(snippet).toContain("fetch('");
        expect(snippet).toContain("method: 'POST'");
        expect(snippet).toContain('JSON.stringify');
    });

    it('includes headers object', () => {
        const snippet = generateSnippet('js', postRoute, formValues, {}, headers);
        expect(snippet).toContain('headers:');
        expect(snippet).toContain('Authorization');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Python Snippets
// ═══════════════════════════════════════════════════════════════════════════

describe('generateSnippet — python', () => {
    it('generates a requests snippet', () => {
        const snippet = generateSnippet('python', postRoute, formValues, {});
        expect(snippet).toContain('import requests');
        expect(snippet).toContain('requests.post');
        expect(snippet).toContain('json=');
    });

    it('includes headers in Python snippet', () => {
        const snippet = generateSnippet('python', postRoute, formValues, {}, headers);
        expect(snippet).toContain('headers=');
        expect(snippet).toContain('Authorization');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Edge Cases
// ═══════════════════════════════════════════════════════════════════════════

describe('generateSnippet — edge cases', () => {
    it('returns empty string for unknown language', () => {
        const snippet = generateSnippet('rust', postRoute, formValues, {});
        expect(snippet).toBe('');
    });

    it('handles empty form values', () => {
        const snippet = generateSnippet('curl', postRoute, emptyForm, {});
        expect(snippet).toContain('curl -X POST');
        // No -d flag since safe object is empty
    });

    it('removes optional path params when empty', () => {
        const snippet = generateSnippet('curl', paramRoute, emptyForm, { user: '42' });
        expect(snippet).not.toContain('{post');
    });
});
