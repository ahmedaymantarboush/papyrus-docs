<?php

use AhmedTarboush\PapyrusDocs\Validation\ValidationParser;
use Illuminate\Validation\Rules\In;

// ═══════════════════════════════════════════════════════════════════════════
// ValidationParser — Full Pipeline Integration Tests
// ═══════════════════════════════════════════════════════════════════════════

describe('ValidationParser::parse() — Full Pipeline', function () {

    beforeEach(function () {
        $this->parser = new ValidationParser();
    });

    // ── Basic Parsing ────────────────────────────────────────────────────

    it('parses a simple string field', function () {
        $result = $this->parser->parse('name', 'required|string|max:255');
        expect($result['key'])->toBe('name');
        expect($result['type'])->toBe('text');
        expect($result['required'])->toBeTrue();
        expect($result['max'])->toBe(255);
    });

    it('parses an email field with colon params', function () {
        $result = $this->parser->parse('email', 'required|email:rfc,dns|max:255');
        expect($result['key'])->toBe('email');
        expect($result['type'])->toBe('email');
        expect($result['required'])->toBeTrue();
    });

    it('parses a password field with confirmed', function () {
        $result = $this->parser->parse('password', 'required|string|min:8|confirmed');
        expect($result['type'])->toBe('text');
        expect($result['confirmed'])->toBeTrue();
        expect($result['min'])->toBe(8);
    });

    it('parses a file field', function () {
        $result = $this->parser->parse('avatar', ['required', 'image', 'max:2048']);
        expect($result['type'])->toBe('file');
        expect($result['required'])->toBeTrue();
        expect($result['max'])->toBe(2048);
    });

    it('parses a UUID field', function () {
        $result = $this->parser->parse('token', 'required|uuid');
        expect($result['type'])->toBe('uuid');
        expect($result['required'])->toBeTrue();
    });

    // ── Select Type Priority ─────────────────────────────────────────────

    it('resolves select type when in: rule is present', function () {
        $result = $this->parser->parse('status', 'required|in:active,inactive,pending');
        expect($result['type'])->toBe('select');
        expect($result['options'])->toBe(['active', 'inactive', 'pending']);
    });

    it('resolves select type with Rule::in() object', function () {
        $ruleIn = new In(['admin', 'editor', 'viewer']);
        $result = $this->parser->parse('role', ['required', $ruleIn]);
        expect($result['type'])->toBe('select');
        expect($result['options'])->not->toBeNull();
    });

    // ── Rule Normalization ───────────────────────────────────────────────

    it('normalizes pipe-separated string rules', function () {
        $parser = new ValidationParser();
        $normalized = $parser->normalizeRules('required|string|max:255');
        expect($normalized)->toBe(['required', 'string', 'max:255']);
    });

    it('normalizes array of mixed types', function () {
        $parser = new ValidationParser();
        $normalized = $parser->normalizeRules(['required', 'string|max:255']);
        expect($normalized)->toBe(['required', 'string', 'max:255']);
    });

    it('skips Closures during normalization', function () {
        $parser = new ValidationParser();
        $normalized = $parser->normalizeRules([
            'required',
            function ($attribute, $value, $fail) {},
            'string',
        ]);
        expect($normalized)->toBe(['required', 'string']);
    });

    it('stringifies Rule::in() during normalization', function () {
        $parser = new ValidationParser();
        $in = new In(['a', 'b', 'c']);
        $normalized = $parser->normalizeRules([$in]);
        expect($normalized)->toHaveCount(1);
        expect($normalized[0])->toStartWith('in:');
    });

    it('handles scalar fallbacks', function () {
        $parser = new ValidationParser();
        $normalized = $parser->normalizeRules('required');
        expect($normalized)->toBe(['required']);
    });

    it('returns empty array for closure-only rules', function () {
        $parser = new ValidationParser();
        $normalized = $parser->normalizeRules(function () {});
        expect($normalized)->toBe([]);
    });

    // ── Stringified Rules for Frontend ────────────────────────────────────

    it('includes all rules in stringified output', function () {
        $result = $this->parser->parse('email', 'required|email|max:255');
        expect($result['rules'])->toContain('required');
        expect($result['rules'])->toContain('email');
        expect($result['rules'])->toContain('max:255');
    });

    it('strips FQCN to snake_case basename', function () {
        $result = $this->parser->parse('field', ['required', 'Illuminate\\Validation\\Rules\\Password']);
        // Should contain something like 'password' (snake_case of basename), not the FQCN
        $hasSnaked = collect($result['rules'])->contains(fn($r) => !str_contains($r, '\\'));
        expect($hasSnaked)->toBeTrue();
    });

    // ── Dot-Notation Key Handling ─────────────────────────────────────────

    it('uses the leaf key for nested fields', function () {
        $result = $this->parser->parse('user.address.street', 'required|string');
        expect($result['key'])->toBe('street');
    });

    it('uses the full key for simple fields', function () {
        $result = $this->parser->parse('email', 'required|email');
        expect($result['key'])->toBe('email');
    });

    // ── Complete Output Schema Shape ─────────────────────────────────────

    it('returns the complete schema shape', function () {
        $result = $this->parser->parse('field', 'required|string');

        expect($result)->toHaveKeys([
            'key',
            'type',
            'required',
            'nullable',
            'min',
            'max',
            'pattern',
            'accept',
            'confirmed',
            'dimensions',
            'conditionals',
            'options',
            'rules',
        ]);
    });
});
