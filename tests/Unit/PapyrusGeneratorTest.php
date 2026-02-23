<?php

use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use AhmedTarboush\PapyrusDocs\Validation\ValidationParser;

describe('PapyrusGenerator::getNestedSchema() — Wildcard & Array Parsing', function () {

    beforeEach(function () {
        $this->generator = new class extends PapyrusGenerator {
            public function testSchema(array $rules): array
            {
                // We mock the route parser to just run the nested schema generation
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
    });

    it('parses courses.* as an array of scalar values', function () {
        $schema = $this->generator->testSchema([
            'courses.*' => 'integer',
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('courses');
        expect($schema[0]['type'])->toBe('array');
        expect($schema[0]['childType'])->toBe('number'); // integer translates to number
    });

    it('parses courses.name as an object with property name', function () {
        $schema = $this->generator->testSchema([
            'courses.name' => 'string',
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('courses');
        expect($schema[0]['type'])->toBe('object');
        expect($schema[0]['isList'])->toBeFalse();
        expect($schema[0]['schema'])->toHaveCount(1);
        expect($schema[0]['schema'][0]['key'])->toBe('name');
        expect($schema[0]['schema'][0]['type'])->toBe('text');
    });

    it('parses courses.*.name as an array of objects', function () {
        $schema = $this->generator->testSchema([
            'courses.*.name' => 'string',
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('courses');
        expect($schema[0]['type'])->toBe('object');
        expect($schema[0]['isList'])->toBeTrue(); // Translates to array of objects in UI
        expect($schema[0]['schema'])->toHaveCount(1);
        expect($schema[0]['schema'][0]['key'])->toBe('name');
        expect($schema[0]['schema'][0]['type'])->toBe('text');
    });

    it('parses pattern properties like test* as dynamic template keys', function () {
        $schema = $this->generator->testSchema([
            'test*' => 'string',
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('test*');
        expect($schema[0]['type'])->toBe('text');
        expect($schema[0]['isPattern'])->toBeTrue();
    });

    it('prioritizes array wildcard * even if an explicit key rule exists', function () {
        $schema = $this->generator->testSchema([
            'courses.*' => 'integer',
            'courses.0' => 'string', // Explicit key rule alongside wildcard
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('courses');
        expect($schema[0]['type'])->toBe('array');
        expect($schema[0]['childType'])->toBe('number');
    });
});
