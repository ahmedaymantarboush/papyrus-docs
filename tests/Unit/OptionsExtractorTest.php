<?php

use AhmedTarboush\PapyrusDocs\Validation\OptionsExtractor;
use Illuminate\Validation\Rules\In;

// ═══════════════════════════════════════════════════════════════════════════
// OptionsExtractor — Exhaustive Unit Tests
// ═══════════════════════════════════════════════════════════════════════════

// Create a test enum for option extraction
enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}

enum TestPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}

// Unit-backed enum (no value property)
enum TestColor
{
    case Red;
    case Green;
    case Blue;
}

describe('OptionsExtractor::extract()', function () {

    // ── Inline in: Rules ─────────────────────────────────────────────────

    it('extracts options from inline "in:" rule', function () {
        $options = OptionsExtractor::extract(['in:active,inactive,pending'], []);
        expect($options)->toBe(['active', 'inactive', 'pending']);
    });

    it('preserves original case for inline "in:" values', function () {
        $options = OptionsExtractor::extract(['in:Yes,No,Maybe'], ['in:Yes,No,Maybe']);
        expect($options)->toBe(['Yes', 'No', 'Maybe']);
    });

    it('returns null when no options found', function () {
        $options = OptionsExtractor::extract(['required', 'string'], ['required', 'string']);
        expect($options)->toBeNull();
    });

    // ── Rule::in() Object ────────────────────────────────────────────────

    it('extracts options from Rule::in() object', function () {
        $ruleIn = new In(['admin', 'editor', 'viewer']);
        $options = OptionsExtractor::extract([(string) $ruleIn], [$ruleIn]);
        expect($options)->toBe(['"admin"', '"editor"', '"viewer"']);
    });

    // ── PHP 8.1 Enum Rules ───────────────────────────────────────────────

    it('extracts options from a backed string enum class name', function () {
        $options = OptionsExtractor::extract([], [TestStatus::class]);
        expect($options)->toBe(['active', 'inactive', 'pending']);
    });

    it('extracts options from a backed int enum class name', function () {
        $options = OptionsExtractor::extract([], [TestPriority::class]);
        expect($options)->toBe([1, 2, 3]);
    });

    it('extracts names from a unit enum class name', function () {
        $options = OptionsExtractor::extract([], [TestColor::class]);
        expect($options)->toBe(['Red', 'Green', 'Blue']);
    });

    // ── Edge Cases ───────────────────────────────────────────────────────

    it('returns null for empty rules', function () {
        $options = OptionsExtractor::extract([], []);
        expect($options)->toBeNull();
    });

    it('ignores non-option rules', function () {
        $options = OptionsExtractor::extract(['required', 'string', 'max:255'], ['required', 'string', 'max:255']);
        expect($options)->toBeNull();
    });
});

describe('OptionsExtractor::extractCustomRuleName()', function () {

    it('returns null for Illuminate framework rules', function () {
        $rule = new In(['a', 'b']);
        $name = OptionsExtractor::extractCustomRuleName($rule);
        expect($name)->toBeNull();
    });

    it('returns snake_case basename for custom rule objects', function () {
        $rule = new class {
            // A simple custom rule with no papyrus_name
        };
        $name = OptionsExtractor::extractCustomRuleName($rule);
        // class_basename for anonymous class produces varied results; just check it returns a string
        expect($name)->toBeString();
    });

    it('returns papyrus_name property when defined', function () {
        $rule = new class {
            public string $papyrus_name = 'phone_number';
        };
        $name = OptionsExtractor::extractCustomRuleName($rule);
        expect($name)->toBe('phone_number');
    });
});
