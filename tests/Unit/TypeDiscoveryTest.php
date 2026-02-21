<?php

use AhmedTarboush\PapyrusDocs\Validation\TypeDiscovery;

// ═══════════════════════════════════════════════════════════════════════════
// TypeDiscovery — Exhaustive Unit Tests
// ═══════════════════════════════════════════════════════════════════════════

describe('TypeDiscovery::resolve()', function () {

    // ── HTML5 Native Types ───────────────────────────────────────────────

    it('resolves "email" to email', function () {
        expect(TypeDiscovery::resolve(['email']))->toBe('email');
    });

    it('resolves "email:rfc,dns" to email (colon params)', function () {
        expect(TypeDiscovery::resolve(['email:rfc,dns']))->toBe('email');
    });

    it('resolves "email:rfc" to email', function () {
        expect(TypeDiscovery::resolve(['email:rfc']))->toBe('email');
    });

    it('resolves "url" to url', function () {
        expect(TypeDiscovery::resolve(['url']))->toBe('url');
    });

    it('resolves "active_url" to url', function () {
        expect(TypeDiscovery::resolve(['active_url']))->toBe('url');
    });

    it('resolves "integer" to number', function () {
        expect(TypeDiscovery::resolve(['integer']))->toBe('number');
    });

    it('resolves "numeric" to number', function () {
        expect(TypeDiscovery::resolve(['numeric']))->toBe('number');
    });

    it('resolves "decimal" to number', function () {
        expect(TypeDiscovery::resolve(['decimal']))->toBe('number');
    });

    it('resolves "decimal:2" to number (colon params)', function () {
        expect(TypeDiscovery::resolve(['decimal:2']))->toBe('number');
    });

    it('resolves "boolean" to boolean', function () {
        expect(TypeDiscovery::resolve(['boolean']))->toBe('boolean');
    });

    it('resolves "bool" to boolean', function () {
        expect(TypeDiscovery::resolve(['bool']))->toBe('boolean');
    });

    it('resolves "date" to date', function () {
        expect(TypeDiscovery::resolve(['date']))->toBe('date');
    });

    it('resolves "password" to password', function () {
        expect(TypeDiscovery::resolve(['password']))->toBe('password');
    });

    it('resolves "hex_color" to color', function () {
        expect(TypeDiscovery::resolve(['hex_color']))->toBe('color');
    });

    it('resolves "json" to json', function () {
        expect(TypeDiscovery::resolve(['json']))->toBe('json');
    });

    it('resolves "array" to array', function () {
        expect(TypeDiscovery::resolve(['array']))->toBe('array');
    });

    // ── File Detection (Highest Priority) ────────────────────────────────

    it('resolves "file" to file', function () {
        expect(TypeDiscovery::resolve(['file']))->toBe('file');
    });

    it('resolves "image" to file', function () {
        expect(TypeDiscovery::resolve(['image']))->toBe('file');
    });

    it('resolves "mimes:jpg,png" to file', function () {
        expect(TypeDiscovery::resolve(['mimes:jpg,png']))->toBe('file');
    });

    it('resolves "mimetypes:image/jpeg" to file', function () {
        expect(TypeDiscovery::resolve(['mimetypes:image/jpeg']))->toBe('file');
    });

    it('resolves "extensions:pdf,doc" to file', function () {
        expect(TypeDiscovery::resolve(['extensions:pdf,doc']))->toBe('file');
    });

    it('resolves "dimensions:min_width=100" to file', function () {
        expect(TypeDiscovery::resolve(['dimensions:min_width=100']))->toBe('file');
    });

    it('file always wins over other types', function () {
        expect(TypeDiscovery::resolve(['email', 'file']))->toBe('file');
        expect(TypeDiscovery::resolve(['integer', 'image']))->toBe('file');
        expect(TypeDiscovery::resolve(['mimes:jpg', 'url']))->toBe('file');
    });

    // ── Dynamic Discovery Types ──────────────────────────────────────────

    it('resolves "uuid" as dynamic type', function () {
        expect(TypeDiscovery::resolve(['uuid']))->toBe('uuid');
    });

    it('resolves "ulid" as dynamic type', function () {
        expect(TypeDiscovery::resolve(['ulid']))->toBe('ulid');
    });

    it('resolves "ip" as dynamic type', function () {
        expect(TypeDiscovery::resolve(['ip']))->toBe('ip');
    });

    it('resolves "ipv4" as dynamic type', function () {
        expect(TypeDiscovery::resolve(['ipv4']))->toBe('ipv4');
    });

    it('resolves "ipv6" as dynamic type', function () {
        expect(TypeDiscovery::resolve(['ipv6']))->toBe('ipv6');
    });

    it('resolves "mac_address" as dynamic type', function () {
        expect(TypeDiscovery::resolve(['mac_address']))->toBe('mac_address');
    });

    // ── Date-format Prefix ───────────────────────────────────────────────

    it('resolves "date_format:Y-m-d" to date', function () {
        expect(TypeDiscovery::resolve(['date_format:Y-m-d']))->toBe('date');
    });

    it('resolves "before:2025-01-01" to date', function () {
        expect(TypeDiscovery::resolve(['before:2025-01-01']))->toBe('date');
    });

    it('resolves "after:tomorrow" to date', function () {
        expect(TypeDiscovery::resolve(['after:tomorrow']))->toBe('date');
    });

    // ── Class Suffix Check ───────────────────────────────────────────────

    it('resolves class ending with \\Password to password', function () {
        expect(TypeDiscovery::resolve(['Illuminate\\Validation\\Rules\\Password']))->toBe('password');
    });

    it('resolves class ending with \\Email to email', function () {
        expect(TypeDiscovery::resolve(['Illuminate\\Validation\\Rules\\Email']))->toBe('email');
    });

    // ── Default Fallback ─────────────────────────────────────────────────

    it('defaults to text for unknown rules', function () {
        expect(TypeDiscovery::resolve(['string']))->toBe('text');
        expect(TypeDiscovery::resolve(['required']))->toBe('text');
        expect(TypeDiscovery::resolve(['max:255']))->toBe('text');
    });

    it('defaults to text for empty rules', function () {
        expect(TypeDiscovery::resolve([]))->toBe('text');
    });

    // ── Priority Resolution ──────────────────────────────────────────────

    it('HTML5 type wins over default text', function () {
        expect(TypeDiscovery::resolve(['required', 'email']))->toBe('email');
        expect(TypeDiscovery::resolve(['string', 'integer']))->toBe('number');
    });

    it('ignores non-scalar values gracefully', function () {
        expect(TypeDiscovery::resolve([null, '', 42]))->toBe('text');
    });

    // ── Mixed Rules with Colon Params ────────────────────────────────────

    it('resolves mixed rules with colon params correctly', function () {
        expect(TypeDiscovery::resolve(['required', 'email:rfc,dns', 'max:255']))->toBe('email');
        expect(TypeDiscovery::resolve(['nullable', 'integer', 'min:0', 'max:100']))->toBe('number');
        expect(TypeDiscovery::resolve(['required', 'date_format:Y-m-d H:i:s']))->toBe('date');
    });
});
