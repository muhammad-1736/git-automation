<?php

describe('data_get', function () {
    it('retrieves a top-level key from an array', function () {
        $array = [
            'name' => 'John',
            'email' => 'john@example.com',
        ];

        expect(data_get($array, 'name'))->toBe('John');
        expect(data_get($array, 'email'))->toBe('john@example.com');
    });

    it('returns default value when key does not exist', function () {
        $array = [
            'name' => 'John',
        ];

        expect(data_get($array, 'missing'))->toBeNull();
        expect(data_get($array, 'missing', 'default'))->toBe('default');
        expect(data_get($array, 'missing', 'custom_default'))->toBe('custom_default');
    });

    it('retrieves nested values using dot notation', function () {
        $array = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ];

        expect(data_get($array, 'user.name'))->toBe('John');
        expect(data_get($array, 'user.email'))->toBe('john@example.com');
    });

    it('retrieves deeply nested values using dot notation', function () {
        $array = [
            'api' => [
                'response' => [
                    'data' => [
                        'user' => [
                            'id' => 123,
                            'username' => 'johndoe',
                        ],
                    ],
                ],
            ],
        ];

        expect(data_get($array, 'api.response.data.user.id'))->toBe(123);
        expect(data_get($array, 'api.response.data.user.username'))->toBe('johndoe');
    });

    it('returns default when nested key partially exists', function () {
        $array = [
            'user' => [
                'name' => 'John',
            ],
        ];

        expect(data_get($array, 'user.profile.age'))->toBeNull();
        expect(data_get($array, 'user.profile.age', 25))->toBe(25);
        expect(data_get($array, 'missing.nested.key', 'default'))->toBe('default');
    });

    it('handles arrays at different nesting levels', function () {
        $array = [
            'items' => [
                'item1' => [
                    'price' => 99.99,
                ],
                'item2' => [
                    'price' => 49.99,
                ],
            ],
        ];

        expect(data_get($array, 'items.item1.price'))->toBe(99.99);
        expect(data_get($array, 'items.item2.price'))->toBe(49.99);
    });

    it('returns default for non-array intermediate values', function () {
        $array = [
            'user' => 'string_value',
        ];

        expect(data_get($array, 'user.name'))->toBeNull();
        expect(data_get($array, 'user.name', 'fallback'))->toBe('fallback');
    });

    it('handles empty arrays', function () {
        $array = [];

        expect(data_get($array, 'any.key'))->toBeNull();
        expect(data_get($array, 'any.key', 'default'))->toBe('default');
    });

    it('returns values with various data types', function () {
        $array = [
            'string' => 'value',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'array' => [1, 2, 3],
            'null' => null,
            'zero' => 0,
            'empty_string' => '',
        ];

        expect(data_get($array, 'string'))->toBe('value');
        expect(data_get($array, 'integer'))->toBe(42);
        expect(data_get($array, 'float'))->toBe(3.14);
        expect(data_get($array, 'boolean'))->toBeTrue();
        expect(data_get($array, 'array'))->toBe([1, 2, 3]);
        expect(data_get($array, 'null'))->toBeNull();
        expect(data_get($array, 'zero'))->toBe(0);
        expect(data_get($array, 'empty_string'))->toBe('');
    });

    it('distinguishes between null value and missing key', function () {
        $array = [
            'existing_null' => null,
            'existing_value' => 'value',
        ];

        // When key exists with null value, returns null (not default)
        expect(data_get($array, 'existing_null'))->toBeNull();
        expect(data_get($array, 'existing_null', 'default'))->toBeNull();

        // When key doesn't exist, returns default
        expect(data_get($array, 'missing'))->toBeNull();
        expect(data_get($array, 'missing', 'default'))->toBe('default');
    });

    it('handles numeric array indices', function () {
        $array = [
            'items' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
        ];

        expect(data_get($array, 'items.0.id'))->toBe(1);
        expect(data_get($array, 'items.1.name'))->toBe('Item 2');
        expect(data_get($array, 'items.2.id'))->toBeNull();
        expect(data_get($array, 'items.2.id', 'not_found'))->toBe('not_found');
    });

    it('handles complex nested structures', function () {
        $array = [
            'database' => [
                'connections' => [
                    'mysql' => [
                        'host' => 'localhost',
                        'port' => 3306,
                        'credentials' => [
                            'username' => 'root',
                            'password' => 'secret',
                        ],
                    ],
                ],
            ],
        ];

        expect(data_get($array, 'database.connections.mysql.host'))->toBe('localhost');
        expect(data_get($array, 'database.connections.mysql.port'))->toBe(3306);
        expect(data_get($array, 'database.connections.mysql.credentials.username'))->toBe('root');
        expect(data_get($array, 'database.connections.mysql.credentials.password'))->toBe('secret');
    });
});
