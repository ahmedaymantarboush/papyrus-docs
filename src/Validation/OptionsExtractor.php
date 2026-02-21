<?php

namespace AhmedTarboush\PapyrusDocs\Validation;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In;

/**
 * OptionsExtractor — Extracts selectable options from validation rules.
 *
 * Handles four sources of options:
 *   1. Inline `in:val1,val2,val3` string rules
 *   2. `Rule::in([...])` objects (Illuminate\Validation\Rules\In)
 *   3. PHP 8.1+ Enum rules (Illuminate\Validation\Rules\Enum)
 *   4. Enum class names passed as raw strings
 *
 * Also handles custom Rule objects:
 *   - Checks for `public string $papyrus_name` property
 *   - Falls back to Str::snake(class_basename($rule))
 *
 * SECURITY: Never queries the database for `exists` or `unique` rules.
 */
class OptionsExtractor
{
    /**
     * Extract options from normalized rules.
     *
     * Returns an array of option values, or null if no options found.
     *
     * @param  array  $normalizedRules      Flat array of string rules
     * @param  array  $originalRules        Original rules before normalization (may contain objects)
     * @return array|null  Array of option values or null
     */
    public static function extract(array $normalizedRules, array $originalRules = []): ?array
    {
        $options = null;

        // ── Scan normalized (string) rules for `in:` ────────────────
        foreach ($normalizedRules as $rule) {
            if (!is_scalar($rule)) continue;

            $r = strtolower(trim((string) $rule));

            if (str_starts_with($r, 'in:')) {
                $values = explode(',', substr((string) $rule, 3)); // Use original case
                $options = array_map(function ($v) {
                    $v = trim($v);
                    // Strip surrounding double-quotes from Enum.__toString() format
                    if (strlen($v) >= 2 && $v[0] === '"' && $v[strlen($v) - 1] === '"') {
                        $v = substr($v, 1, -1);
                        $v = str_replace('""', '"', $v); // Unescape doubled quotes
                    }
                    return $v;
                }, $values);
            }
        }

        // ── Scan original rules for Rule objects ────────────────────
        foreach ($originalRules as $rule) {
            // Rule::in([...]) — Illuminate\Validation\Rules\In
            if ($rule instanceof In) {
                $str = (string) $rule; // Produces "in:val1,val2"
                if (str_starts_with(strtolower($str), 'in:')) {
                    $values = explode(',', substr($str, 3));
                    $options = array_map('trim', $values);
                }
                continue;
            }

            // PHP 8.1 Enum rule — Illuminate\Validation\Rules\Enum
            if (is_object($rule) && self::isEnumRule($rule)) {
                $enumCases = self::extractEnumCases($rule);
                if ($enumCases !== null) {
                    $options = $enumCases;
                }
                continue;
            }

            // Raw enum class name as string
            if (is_string($rule) && enum_exists($rule)) {
                $options = array_map(function ($case) {
                    return $case->value ?? $case->name;
                }, $rule::cases());
                continue;
            }
        }

        return $options;
    }

    /**
     * Extract a custom type name from a rule object.
     *
     * Checks for `public string $papyrus_name` property first.
     * Falls back to Str::snake(class_basename($ruleObject)).
     *
     * @param  object  $rule  A custom validation rule object
     * @return string|null  The custom type name, or null if not a custom rule object
     */
    public static function extractCustomRuleName(object $rule): ?string
    {
        // Skip framework rules
        if (str_starts_with(get_class($rule), 'Illuminate\\')) {
            return null;
        }

        // Check for explicit papyrus_name property
        if (property_exists($rule, 'papyrus_name') && is_string($rule->papyrus_name)) {
            return $rule->papyrus_name;
        }

        // Fallback to snake_case of class basename
        return Str::snake(class_basename($rule));
    }

    /**
     * Check if a rule object is Illuminate\Validation\Rules\Enum.
     */
    protected static function isEnumRule(object $rule): bool
    {
        return str_starts_with(get_class($rule), 'Illuminate\\Validation\\Rules\\Enum');
    }

    /**
     * Extract enum case values from an Enum validation rule.
     *
     * Uses reflection to access the protected `$type` property
     * on the Illuminate\Validation\Rules\Enum class.
     *
     * @param  object  $rule  An Illuminate\Validation\Rules\Enum instance
     * @return array|null  Array of enum values/names, or null on failure
     */
    protected static function extractEnumCases(object $rule): ?array
    {
        try {
            $reflection = new \ReflectionClass($rule);

            if (! $reflection->hasProperty('type')) {
                return null;
            }

            $prop = $reflection->getProperty('type');
            $enumClass = $prop->getValue($rule);

            if ($enumClass === null || ! enum_exists($enumClass)) {
                return null;
            }

            return array_map(function ($case) {
                return $case->value ?? $case->name;
            }, $enumClass::cases());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
