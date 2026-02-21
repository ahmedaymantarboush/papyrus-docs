<?php

namespace AhmedTarboush\PapyrusDocs\Validation;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

/**
 * ValidationParser — The orchestrator for the Papyrus validation engine.
 *
 * This class is the single entry point for converting Laravel validation
 * rules into a rich, frontend-consumable schema. It delegates to:
 *
 *   - TypeDiscovery::resolve()       → Determine the field type
 *   - ConstraintExtractor::extract() → Extract min/max/pattern/accept/etc.
 *   - OptionsExtractor::extract()    → Extract dropdown/select options
 *
 * The output schema for each field includes:
 *   - key, type, required, nullable
 *   - min, max, pattern, accept, confirmed, dimensions
 *   - conditionals[] (structured conditional rules)
 *   - options[] (for select/enum fields)
 *   - rules[] (stringified original rules for frontend display)
 */
class ValidationParser
{
    /**
     * Parse a single field's validation rules into a rich schema.
     *
     * @param  string  $key    The field name (e.g., 'email', 'avatar', 'meta.tags.*')
     * @param  mixed   $rules  Raw rules: string, array, or Rule object
     * @return array   The enriched schema for this field
     */
    public function parse(string $key, mixed $rules): array
    {
        // Step 1: Normalize rules to a flat array of strings
        // Keep original rules array for object inspection (Enum, custom rules)
        $originalRules = is_array($rules) ? $rules : [$rules];
        $normalized = $this->normalizeRules($rules);

        // Step 2: Resolve type via TypeDiscovery
        $type = TypeDiscovery::resolve($normalized);

        // Step 3: Extract constraints via ConstraintExtractor
        $constraints = ConstraintExtractor::extract($normalized);

        // Step 4: Extract options via OptionsExtractor
        $options = OptionsExtractor::extract($normalized, $originalRules);

        // Step 5: Check for custom rule type override
        $customType = $this->resolveCustomRuleType($originalRules);

        // Step 6: Determine final type
        // Priority: options (select) > custom > discovered
        $finalType = $type;
        if ($options !== null) {
            $finalType = 'select';
        } elseif ($customType !== null) {
            $finalType = $customType;
        }

        // Step 7: Stringify rules for frontend display
        $stringifiedRules = $this->stringifyRules($normalized);

        return [
            'key'          => last(explode('.', $key)),  // Leaf key for nesting
            'type'         => $finalType,
            'required'     => $constraints['required'],
            'nullable'     => $constraints['nullable'],
            'min'          => $constraints['min'],
            'max'          => $constraints['max'],
            'pattern'      => $constraints['pattern'],
            'accept'       => $constraints['accept'],
            'confirmed'    => $constraints['confirmed'],
            'dimensions'   => $constraints['dimensions'],
            'conditionals' => $constraints['conditionals'],
            'options'      => $options,
            'rules'        => $stringifiedRules,
        ];
    }

    /**
     * Normalize rules to a flat array of strings/scalars.
     *
     * Handles:
     *   - Pipe-separated strings: "required|string|max:255"
     *   - Arrays of mixed strings/objects: ['required', Rule::in([...])]
     *   - Single Rule objects
     *   - Closures (safely skipped)
     *
     * @param  mixed  $rules  Raw rules input
     * @return array  Flat array of normalized rule strings
     */
    public function normalizeRules(mixed $rules): array
    {
        // ── String rules (pipe-separated) ────────────────────────────
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        // ── Array of rules ───────────────────────────────────────────
        if (is_array($rules)) {
            $normalized = [];

            foreach ($rules as $rule) {
                // Skip Closures entirely
                if ($rule instanceof \Closure) {
                    continue;
                }

                // String rule (may be pipe-separated within array)
                if (is_string($rule)) {
                    foreach (explode('|', $rule) as $r) {
                        $normalized[] = $r;
                    }
                    continue;
                }

                // Rule::in([...]) → stringify to "in:val1,val2"
                if ($rule instanceof In) {
                    $normalized[] = (string) $rule;
                    continue;
                }

                // Other Rule objects
                if (is_object($rule)) {
                    if (method_exists($rule, '__toString')) {
                        try {
                            $normalized[] = (string) $rule;
                        } catch (\Throwable $e) { // @phpstan-ignore catch.neverThrown (__toString CAN throw)
                            // __toString() can fail (e.g. Enum with unloadable class)
                            $normalized[] = get_class($rule);
                        }
                    } else {
                        $normalized[] = get_class($rule);
                    }
                    continue;
                }

                // Scalar fallbacks
                if (is_scalar($rule)) {
                    $normalized[] = (string) $rule;
                }
                // Non-scalar, non-object, non-closure → skip
            }

            return $normalized;
        }

        // ── Single Rule object ───────────────────────────────────────
        if (is_object($rules)) {
            if ($rules instanceof \Closure) {
                return []; // Skip closures
            }

            return method_exists($rules, '__toString')
                ? [(string) $rules]
                : [get_class($rules)];
        }

        // ── Scalar fallback ──────────────────────────────────────────
        return is_scalar($rules) ? [(string) $rules] : [];
    }

    /**
     * Check if any original rule provides a custom type name.
     *
     * Iterates through original (non-normalized) rules looking for
     * custom Rule objects with `$papyrus_name` or falling back
     * to snake_case class basename.
     *
     * @param  array  $originalRules  The original rules array
     * @return string|null  Custom type name or null
     */
    protected function resolveCustomRuleType(array $originalRules): ?string
    {
        foreach ($originalRules as $rule) {
            if (! is_object($rule)) continue;
            if ($rule instanceof \Closure) continue;
            if ($rule instanceof In) continue;

            $customName = OptionsExtractor::extractCustomRuleName($rule);
            if ($customName !== null) {
                return $customName;
            }
        }

        return null;
    }

    /**
     * Convert normalized rules to an array of display-safe strings.
     *
     * Strips FQCN prefixes for readability while preserving
     * the full rule text for parameters.
     *
     * @param  array  $normalizedRules  Flat array of rule strings
     * @return array  Array of human-readable rule strings
     */
    protected function stringifyRules(array $normalizedRules): array
    {
        return array_values(array_filter(array_map(function ($rule) {
            $str = is_scalar($rule) ? trim((string) $rule) : '';
            if ($str === '') return null;

            // If it looks like a FQCN (contains backslashes), use only the basename
            if (str_contains($str, '\\')) {
                $basename = class_basename($str);
                return \Illuminate\Support\Str::snake($basename);
            }

            return $str;
        }, $normalizedRules)));
    }
}
