<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2023 Atlas Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\ImportTools\Test\TestCase\Utility;

use BEdita\ImportTools\Utility\ReadTrait;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * {@see \BEdita\ImportTools\Utility\ReadTrait} Test Case
 */
#[CoversClass(ReadTrait::class)]
class ReadTraitTest extends TestCase
{
    use ReadTrait;

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '"',
        ],
    ];

    /**
     * Test `readItem` method with an invalid type.
     *
     * @return void
     */
    public function testReadItemInvalidType(): void
    {
        $expected = new InvalidArgumentException('Invalid source type "invalid"');
        $this->expectExceptionObject($expected);
        foreach ($this->readItem('invalid', 'path') as $item) {
            $item = array_filter($item);
        }
    }

    /**
     * Test `readItem` method with a file that does not exist.
     *
     * @return void
     */
    public function testReadNotFound(): void
    {
        $path = TEST_FILES . DS . 'not-found.csv';

        $expected = new RuntimeException(sprintf('Cannot open file: %s', $path));
        $this->expectExceptionObject($expected);

        $this->readItem('csv', $path)->next();
    }

    /**
     * Test `readItem` method
     *
     * @return void
     */
    public function testReadItemCsv(): void
    {
        $expected = [
            ['title' => 'The Great Gatsby', 'author' => 'Francis Scott Fitzgerald'],
            ['title' => 'Moby-Dick', 'author' => 'Herman Melville'],
            ['title' => 'Ulysses', 'author' => 'James Joyce'],
            ['title' => 'Hearth of Darkness', 'author' => 'Joseph Conrad'],
        ];

        $actual = iterator_to_array($this->readItem('csv', TEST_FILES . DS . 'authors.csv'));

        static::assertSame($expected, $actual);
    }
}
