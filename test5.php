<?php

require 'vendor/autoload.php';

use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use AhmedTarboush\PapyrusDocs\Validation\ValidationParser;

$generator = new class extends PapyrusGenerator {
    public function testSchema(array $rules): array
    {
        $parser = new ValidationParser();
        $enriched = [];
        foreach ($rules as $key => $r) {
            $enriched[$key] = $parser->parse($key, $r);
        }

        $tree = [];
        foreach ($enriched as $key => $meta) {
            $parts   = explode('.', $key);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                $isLast = ($i === count($parts) - 1);

                if (! isset($current[$part])) {
                    $current[$part] = [
                        'key'      => $part,
                        'type'     => $isLast ? $meta['type'] : 'object',
                        'children' => [],
                    ];
                }

                if ($isLast) {
                    $current[$part] = array_merge($current[$part], $meta);
                }

                $current = &$current[$part]['children'];
            }
        }

        return $this->formatSchemaNodes($tree);
    }
};

$schema = $generator->testSchema([
    'course_ids'   => 'required|array',
    'course_ids.*' => 'integer',
]);

echo json_encode($schema, JSON_PRETTY_PRINT);
echo "\n";
