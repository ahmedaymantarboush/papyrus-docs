<?php

namespace AhmedTarboush\PapyrusDocs\Validation;

/**
 * TypeDiscovery — Maps Laravel validation rules to schema types.
 *
 * Implements a priority-based type resolution strategy:
 *   1. `select` wins if options are present (in:, Enum)
 *   2. `file` wins if file/image rules are present
 *   3. HTML5-native types: email, url, number, boolean, date, password, color, json
 *   4. Dynamic Discovery: uuid, ulid, ip, ipv4, ipv6, mac_address → type = rule name
 *   5. Default fallback: 'text'
 *
 * The "Dynamic Discovery" feature is critical: if a rule IS a recognized
 * data-format validator but does NOT map to a standard HTML5 input type,
 * we set schema.type to the exact rule name rather than falling back to 'text'.
 * This allows the frontend to render format-specific hints and placeholders.
 */
class TypeDiscovery
{
    /**
     * HTML5-native type mappings.
     * These map directly to <input type="..."> elements.
     *
     * Key = lowercase rule name, Value = schema type
     */
    protected static array $html5Map = [
        'email'       => 'email',
        'url'         => 'url',
        'active_url'  => 'url',
        'integer'     => 'number',
        'numeric'     => 'number',
        'decimal'     => 'number',
        'float'       => 'number',
        'boolean'     => 'boolean',
        'bool'        => 'boolean',
        'date'        => 'date',
        'password'    => 'password',
        'hex_color'   => 'color',
        'json'        => 'json',
        'array'       => 'array',
    ];

    /**
     * Dynamic discovery types.
     * These are valid Laravel rules that represent specific data formats
     * but have no HTML5 <input> equivalent. We preserve the rule name
     * as the type so the frontend can render format-specific UI hints.
     */
    protected static array $dynamicTypes = [
        'uuid',
        'ulid',
        'ip',
        'ipv4',
        'ipv6',
        'mac_address',
    ];

    /**
     * Rules that indicate a file upload field.
     * File type takes high priority in resolution.
     */
    protected static array $fileIndicators = [
        'file',
        'image',
    ];

    /**
     * Rule prefixes that also indicate a file field.
     */
    protected static array $filePrefixes = [
        'mimes:',
        'mimetypes:',
        'extensions:',
        'dimensions:',
    ];

    /**
     * Rule class suffixes that indicate a file field.
     */
    protected static array $fileClassSuffixes = [
        '\\File',
        '\\Image',
    ];

    /**
     * Resolve the schema type from a set of normalized rules.
     *
     * Priority order: file > HTML5 > dynamic > date_format > text
     *
     * @param  array  $normalizedRules  Flat array of string rules
     * @return string The resolved schema type
     */
    public static function resolve(array $normalizedRules): string
    {
        $resolved = 'text'; // Default fallback
        $isFile = false;

        foreach ($normalizedRules as $rule) {
            $r = is_scalar($rule) ? strtolower(trim((string) $rule)) : '';
            if ($r === '') continue;

            // ── File Detection (highest priority) ────────────────────
            if (in_array($r, self::$fileIndicators)) {
                $isFile = true;
                continue;
            }

            foreach (self::$filePrefixes as $prefix) {
                if (str_starts_with($r, $prefix)) {
                    $isFile = true;
                    break;
                }
            }

            // Check for rule class suffixes (e.g., Illuminate\....\File)
            foreach (self::$fileClassSuffixes as $suffix) {
                if (str_ends_with($r, strtolower($suffix))) {
                    $isFile = true;
                    break;
                }
            }

            // Extract base rule name before colon (e.g. "email:rfc,dns" -> "email")
            $baseRule = explode(':', $r)[0];

            // ── HTML5 Native Types ───────────────────────────────────
            if (isset(self::$html5Map[$baseRule])) {
                $resolved = self::$html5Map[$baseRule];
                continue;
            }

            // ── Dynamic Discovery Types ──────────────────────────────
            if (in_array($baseRule, self::$dynamicTypes)) {
                $resolved = $baseRule;
                continue;
            }

            // ── Date-format prefix check ─────────────────────────────
            if (str_starts_with($r, 'date_format:') || str_starts_with($r, 'before:') || str_starts_with($r, 'after:')) {
                $resolved = 'date';
                continue;
            }

            // ── Password class suffix check ──────────────────────────
            if (str_ends_with($r, '\\password') || str_ends_with($r, '\\email')) {
                $resolved = str_ends_with($r, '\\password') ? 'password' : 'email';
                continue;
            }
        }

        // File always wins over other types
        if ($isFile) {
            return 'file';
        }

        return $resolved;
    }
}
