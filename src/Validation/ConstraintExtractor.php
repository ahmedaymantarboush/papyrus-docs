<?php

namespace AhmedTarboush\PapyrusDocs\Validation;

/**
 * ConstraintExtractor — Extracts size, presence, pattern, file-accept,
 * confirmation, and conditional constraints from normalized Laravel rules.
 *
 * This class processes each rule string and populates a structured
 * constraints array that the frontend can use for:
 *   - Input attributes (min, max, pattern, accept, step)
 *   - Visual indicators (required asterisk, nullable hint)
 *   - Conditional logic badges (required_if, prohibited_if, etc.)
 *   - Confirmation field auto-generation
 *   - File dimension restrictions
 */
class ConstraintExtractor
{
    /**
     * Rules that set `required = true` directly.
     * Conditional variants (required_if, etc.) go to conditionals[] instead.
     */
    protected static array $directRequired = [
        'required',
    ];

    /**
     * Conditional rule prefixes that produce structured conditional entries.
     * These NEVER set required = true on the field itself.
     */
    protected static array $conditionalPrefixes = [
        'required_if',
        'required_unless',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'prohibited',
        'prohibited_if',
        'prohibited_unless',
        'exclude_if',
        'exclude_unless',
        'exclude_with',
        'exclude_without',
    ];

    /**
     * Extract all constraints from normalized rules.
     *
     * @param  array  $normalizedRules  Flat array of string rules
     * @return array  Structured constraints
     */
    public static function extract(array $normalizedRules): array
    {
        $constraints = [
            'required'     => false,
            'nullable'     => false,
            'min'          => null,
            'max'          => null,
            'pattern'      => null,
            'accept'       => null,
            'confirmed'    => false,
            'dimensions'   => null,
            'conditionals' => [],
        ];

        foreach ($normalizedRules as $rule) {
            $r = is_scalar($rule) ? strtolower(trim((string) $rule)) : '';
            if ($r === '') continue;

            // ── Direct Required ──────────────────────────────────────
            if ($r === 'required') {
                $constraints['required'] = true;
                continue;
            }

            // ── Nullable ─────────────────────────────────────────────
            if ($r === 'nullable') {
                $constraints['nullable'] = true;
                continue;
            }

            // ── Confirmed ────────────────────────────────────────────
            if ($r === 'confirmed') {
                $constraints['confirmed'] = true;
                continue;
            }

            // ── Conditional Rules ────────────────────────────────────
            $conditional = self::parseConditional($r);
            if ($conditional !== null) {
                $constraints['conditionals'][] = $conditional;
                continue;
            }

            // ── Min / Max / Size / Between ───────────────────────────
            if (str_starts_with($r, 'min:')) {
                $constraints['min'] = self::numericOrNull(substr($r, 4));
                continue;
            }

            if (str_starts_with($r, 'max:')) {
                $constraints['max'] = self::numericOrNull(substr($r, 4));
                continue;
            }

            if (str_starts_with($r, 'size:')) {
                $val = self::numericOrNull(substr($r, 5));
                $constraints['min'] = $val;
                $constraints['max'] = $val;
                continue;
            }

            if (str_starts_with($r, 'between:')) {
                $parts = explode(',', substr($r, 8));
                if (count($parts) === 2) {
                    $constraints['min'] = self::numericOrNull(trim($parts[0]));
                    $constraints['max'] = self::numericOrNull(trim($parts[1]));
                }
                continue;
            }

            // ── Regex Pattern ────────────────────────────────────────
            // Regex rules use the format: regex:/pattern/
            // We need to handle the full original rule since it may contain pipe chars
            if (str_starts_with($r, 'regex:')) {
                // Use the original (non-lowercased) rule for pattern preservation
                $original = is_scalar($rule) ? trim((string) $rule) : '';
                $constraints['pattern'] = substr($original, 6); // Everything after "regex:"
                continue;
            }

            // ── File Accept (extensions / mimes) ─────────────────────
            if (str_starts_with($r, 'extensions:') || str_starts_with($r, 'mimes:')) {
                $prefix = str_starts_with($r, 'extensions:') ? 'extensions:' : 'mimes:';
                $exts = explode(',', substr($r, strlen($prefix)));
                $dotExts = array_map(fn($e) => '.' . trim($e), $exts);
                $constraints['accept'] = implode(',', $dotExts);
                continue;
            }

            // ── Dimensions (for image files) ─────────────────────────
            if (str_starts_with($r, 'dimensions:')) {
                $constraints['dimensions'] = self::parseDimensions(substr($r, 11));
                continue;
            }

            // ── Class-based required rules (e.g., RequiredIf class) ──
            if (str_ends_with($r, '\\requiredif') || str_ends_with($r, '\\required')) {
                $constraints['required'] = true;
                continue;
            }
        }

        return $constraints;
    }

    /**
     * Parse a conditional rule string into a structured array.
     *
     * Examples:
     *   "required_if:role,admin"    → { rule: "required_if", field: "role", value: "admin" }
     *   "required_with:name,email"  → { rule: "required_with", fields: ["name","email"] }
     *   "prohibited"               → { rule: "prohibited" }
     *
     * @param  string  $rule  Lowercase rule string
     * @return array|null  Structured conditional or null if not a conditional
     */
    protected static function parseConditional(string $rule): ?array
    {
        foreach (self::$conditionalPrefixes as $prefix) {
            // Exact match (no colon), e.g., "prohibited"
            if ($rule === $prefix) {
                return ['rule' => $prefix];
            }

            // Prefix with parameters, e.g., "required_if:field,value"
            if (str_starts_with($rule, $prefix . ':')) {
                $params = explode(',', substr($rule, strlen($prefix) + 1));

                // Single-field conditionals: required_if, required_unless, prohibited_if, etc.
                if (in_array($prefix, [
                    'required_if',
                    'required_unless',
                    'prohibited_if',
                    'prohibited_unless',
                    'exclude_if',
                    'exclude_unless',
                ])) {
                    return [
                        'rule'  => $prefix,
                        'field' => $params[0],
                        'value' => $params[1] ?? '',
                    ];
                }

                // Multi-field conditionals: required_with, required_without, etc.
                if (in_array($prefix, [
                    'required_with',
                    'required_with_all',
                    'required_without',
                    'required_without_all',
                    'exclude_with',
                    'exclude_without',
                ])) {
                    return [
                        'rule'   => $prefix,
                        'fields' => array_map('trim', $params),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Parse image dimensions string into key-value pairs.
     *
     * Input: "min_width=100,min_height=200,max_width=1920"
     * Output: ["min_width" => "100", "min_height" => "200", "max_width" => "1920"]
     */
    protected static function parseDimensions(string $dimensionStr): array
    {
        $result = [];
        $parts = explode(',', $dimensionStr);

        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $result[trim($kv[0])] = trim($kv[1]);
            }
        }

        return $result;
    }

    /**
     * Cast a string to int/float or return null.
     */
    protected static function numericOrNull(string $value): int|float|null
    {
        $trimmed = trim($value);

        if (is_numeric($trimmed)) {
            // Return int if possible, float otherwise
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return null;
    }
}
