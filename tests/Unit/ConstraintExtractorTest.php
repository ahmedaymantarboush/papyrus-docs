<?php

use AhmedTarboush\PapyrusDocs\Validation\ConstraintExtractor;

// ═══════════════════════════════════════════════════════════════════════════
// ConstraintExtractor — Exhaustive Unit Tests
// ═══════════════════════════════════════════════════════════════════════════

describe('ConstraintExtractor::extract()', function () {

    // ── Required & Nullable ──────────────────────────────────────────────

    it('detects required', function () {
        $c = ConstraintExtractor::extract(['required', 'string']);
        expect($c['required'])->toBeTrue();
    });

    it('does not set required for conditional rules', function () {
        $c = ConstraintExtractor::extract(['required_if:role,admin']);
        expect($c['required'])->toBeFalse();
    });

    it('detects nullable', function () {
        $c = ConstraintExtractor::extract(['nullable', 'string']);
        expect($c['nullable'])->toBeTrue();
    });

    it('defaults required and nullable to false', function () {
        $c = ConstraintExtractor::extract(['string', 'max:255']);
        expect($c['required'])->toBeFalse();
        expect($c['nullable'])->toBeFalse();
    });

    // ── Confirmed ────────────────────────────────────────────────────────

    it('detects confirmed', function () {
        $c = ConstraintExtractor::extract(['required', 'confirmed']);
        expect($c['confirmed'])->toBeTrue();
    });

    it('defaults confirmed to false', function () {
        $c = ConstraintExtractor::extract(['string']);
        expect($c['confirmed'])->toBeFalse();
    });

    // ── Min / Max / Size / Between ───────────────────────────────────────

    it('extracts min value', function () {
        $c = ConstraintExtractor::extract(['min:3']);
        expect($c['min'])->toBe(3);
    });

    it('extracts max value', function () {
        $c = ConstraintExtractor::extract(['max:255']);
        expect($c['max'])->toBe(255);
    });

    it('extracts size as both min and max', function () {
        $c = ConstraintExtractor::extract(['size:10']);
        expect($c['min'])->toBe(10);
        expect($c['max'])->toBe(10);
    });

    it('extracts between as min and max', function () {
        $c = ConstraintExtractor::extract(['between:1,100']);
        expect($c['min'])->toBe(1);
        expect($c['max'])->toBe(100);
    });

    it('handles float min/max', function () {
        $c = ConstraintExtractor::extract(['min:0.5', 'max:99.9']);
        expect($c['min'])->toBe(0.5);
        expect($c['max'])->toBe(99.9);
    });

    it('returns null for non-numeric min/max values', function () {
        $c = ConstraintExtractor::extract(['min:abc']);
        expect($c['min'])->toBeNull();
    });

    // ── Regex Pattern ────────────────────────────────────────────────────

    it('extracts regex pattern', function () {
        $c = ConstraintExtractor::extract(['regex:/^[a-z]+$/']);
        expect($c['pattern'])->toBe('/^[a-z]+$/');
    });

    it('preserves original case in regex pattern', function () {
        $c = ConstraintExtractor::extract(['regex:/^[A-Z][a-z]+$/']);
        expect($c['pattern'])->toBe('/^[A-Z][a-z]+$/');
    });

    // ── File Accept ──────────────────────────────────────────────────────

    it('extracts extensions as accept', function () {
        $c = ConstraintExtractor::extract(['extensions:pdf,doc,docx']);
        expect($c['accept'])->toBe('.pdf,.doc,.docx');
    });

    it('extracts mimes as accept', function () {
        $c = ConstraintExtractor::extract(['mimes:jpg,png,gif']);
        expect($c['accept'])->toBe('.jpg,.png,.gif');
    });

    // ── Dimensions ───────────────────────────────────────────────────────

    it('extracts image dimensions', function () {
        $c = ConstraintExtractor::extract(['dimensions:min_width=100,min_height=200,max_width=1920']);
        expect($c['dimensions'])->toBe([
            'min_width'  => '100',
            'min_height' => '200',
            'max_width'  => '1920',
        ]);
    });

    it('defaults dimensions to null', function () {
        $c = ConstraintExtractor::extract(['string']);
        expect($c['dimensions'])->toBeNull();
    });

    // ── Conditional Rules ────────────────────────────────────────────────

    it('parses required_if conditional', function () {
        $c = ConstraintExtractor::extract(['required_if:role,admin']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('required_if');
        expect($c['conditionals'][0]['field'])->toBe('role');
        expect($c['conditionals'][0]['value'])->toBe('admin');
    });

    it('parses required_unless conditional', function () {
        $c = ConstraintExtractor::extract(['required_unless:type,guest']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('required_unless');
    });

    it('parses required_with as multi-field conditional', function () {
        $c = ConstraintExtractor::extract(['required_with:name,email']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('required_with');
        expect($c['conditionals'][0]['fields'])->toBe(['name', 'email']);
    });

    it('parses required_without_all conditional', function () {
        $c = ConstraintExtractor::extract(['required_without_all:phone,email']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('required_without_all');
        expect($c['conditionals'][0]['fields'])->toBe(['phone', 'email']);
    });

    it('parses standalone prohibited', function () {
        $c = ConstraintExtractor::extract(['prohibited']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('prohibited');
    });

    it('parses prohibited_if conditional', function () {
        $c = ConstraintExtractor::extract(['prohibited_if:role,guest']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('prohibited_if');
        expect($c['conditionals'][0]['field'])->toBe('role');
    });

    it('parses exclude_if conditional', function () {
        $c = ConstraintExtractor::extract(['exclude_if:type,internal']);
        expect($c['conditionals'])->toHaveCount(1);
        expect($c['conditionals'][0]['rule'])->toBe('exclude_if');
    });

    it('handles multiple conditionals in same field', function () {
        $c = ConstraintExtractor::extract(['required_if:role,admin', 'prohibited_if:status,banned']);
        expect($c['conditionals'])->toHaveCount(2);
    });

    // ── Class-based Required ─────────────────────────────────────────────

    it('detects class-based RequiredIf as required', function () {
        $c = ConstraintExtractor::extract(['Illuminate\\Validation\\Rules\\RequiredIf']);
        expect($c['required'])->toBeTrue();
    });

    // ── Edge Cases ───────────────────────────────────────────────────────

    it('handles empty rules array', function () {
        $c = ConstraintExtractor::extract([]);
        expect($c['required'])->toBeFalse();
        expect($c['nullable'])->toBeFalse();
        expect($c['min'])->toBeNull();
        expect($c['max'])->toBeNull();
        expect($c['conditionals'])->toBe([]);
    });

    it('ignores non-scalar rules gracefully', function () {
        $c = ConstraintExtractor::extract([null, '', false]);
        expect($c['required'])->toBeFalse();
    });

    // ── Combined Rules ───────────────────────────────────────────────────

    it('extracts all constraints from a complex ruleset', function () {
        $c = ConstraintExtractor::extract([
            'required',
            'nullable',
            'min:1',
            'max:100',
            'regex:/^[0-9]+$/',
            'confirmed',
            'required_if:plan,premium',
        ]);

        expect($c['required'])->toBeTrue();
        expect($c['nullable'])->toBeTrue();
        expect($c['min'])->toBe(1);
        expect($c['max'])->toBe(100);
        expect($c['pattern'])->toBe('/^[0-9]+$/');
        expect($c['confirmed'])->toBeTrue();
        expect($c['conditionals'])->toHaveCount(1);
    });
});
