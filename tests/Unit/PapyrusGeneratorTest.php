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

describe('PapyrusGenerator DocBlock Parsing', function () {
    beforeEach(function () {
        $this->generator = new class extends PapyrusGenerator {
            public function testExtractDocBlockResponseExamples(\ReflectionMethod $reflection): array
            {
                return $this->extractDocBlockResponseExamples($reflection);
            }
        };
    });

    it('parses response examples with loose whitespace and carriage returns', function () {
        // Create an anonymous class with a mocked docblock containing different edge-cases
        $anonObj = new class {
            /**
             * @papyrus-responseExample-start 200
             * {
             *    "success": true,
             *    "data": "strict newline"
             * }
             * @papyrus-responseExample-end
             * 
             * @papyrus-responseExample-start 400 
             * {
             *    "success": false,
             *    "data": "trailing space"
             * }
             * @papyrus-responseExample-end
             * 
             * @papyrus-responseExample-start 404     
             * {
             *    "success": false,
             *    "data": "multiple spaces"
             * }
             * @papyrus-responseExample-end
             */
            public function dummyMethod() {}
        };

        $reflection = new \ReflectionMethod($anonObj, 'dummyMethod');
        $examples = $this->generator->testExtractDocBlockResponseExamples($reflection);

        expect($examples)->toHaveCount(3);

        expect($examples['200'])->toContain('"data": "strict newline"');
        expect($examples['400'])->toContain('"data": "trailing space"');
        expect($examples['404'])->toContain('"data": "multiple spaces"');
    });
});

describe('PapyrusGenerator Manual Schema Parsing', function () {
    beforeEach(function () {
        $this->generator = new class extends PapyrusGenerator {
            public function testBuildManualSchema(array $params): array
            {
                return $this->buildManualSchema($params);
            }
        };
    });

    it('correctly maps array properties with wildcards like array.*', function () {
        $schema = $this->generator->testBuildManualSchema([
            [
                'type' => 'string',
                'key' => 'items.*',
                'description' => 'A list of strings',
            ]
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('items');
        expect($schema[0]['type'])->toBe('array');
        expect($schema[0]['isList'])->toBeFalse();
        expect($schema[0]['childType'])->toBe('text');
    });

    it('correctly maps array of objects using wildcards like array.*.key', function () {
        $schema = $this->generator->testBuildManualSchema([
            [
                'type' => 'string',
                'key' => 'users.*.email',
                'description' => 'User email address',
            ],
            [
                'type' => 'int',
                'key' => 'users.*.age',
                'description' => 'User age',
            ]
        ]);

        expect($schema)->toHaveCount(1);
        expect($schema[0]['key'])->toBe('users');
        expect($schema[0]['type'])->toBe('object');
        expect($schema[0]['isList'])->toBeTrue();
        expect($schema[0]['schema'])->toHaveCount(2);

        $emailProp = collect($schema[0]['schema'])->firstWhere('key', 'email');
        expect($emailProp['type'])->toBe('text');

        $ageProp = collect($schema[0]['schema'])->firstWhere('key', 'age');
        expect($ageProp['type'])->toBe('number');
    });
});

describe('PapyrusGenerator Auto Schema from JSON', function () {
    beforeEach(function () {
        $this->generator = new class extends PapyrusGenerator {
            public function testBuildSchemaFromJson(array $data): array
            {
                return $this->buildSchemaFromJson($data);
            }
        };
    });

    it('recursively builds accurate schemas from nested JSON payloads', function () {
        $payload = [
            'success' => true,
            'metadata' => [
                'total_pages' => 5,
                'has_more' => false,
            ],
            'users' => [
                [
                    'id' => 101,
                    'is_active' => true,
                    'roles' => ['admin', 'moderator']
                ]
            ],
            'tags' => ['api', 'docs']
        ];

        $schema = $this->generator->testBuildSchemaFromJson($payload);

        expect($schema)->toHaveCount(4);

        $success = collect($schema)->firstWhere('key', 'success');
        expect($success['type'])->toBe('boolean');

        $metadata = collect($schema)->firstWhere('key', 'metadata');
        expect($metadata['type'])->toBe('object');
        expect($metadata['schema'])->toHaveCount(2);

        $users = collect($schema)->firstWhere('key', 'users');
        expect($users['type'])->toBe('object');
        expect($users['isList'])->toBeTrue();
        expect($users['schema'])->toHaveCount(3);

        $tags = collect($schema)->firstWhere('key', 'tags');
        expect($tags['type'])->toBe('array');
        expect($tags['isList'])->toBeFalse();
        expect($tags['childType'])->toBe('text');
    });
});
