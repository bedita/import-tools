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

use BEdita\ImportTools\Utility\CsvTrait;
use BEdita\ImportTools\Utility\XmlTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * {@see \BEdita\ImportTools\Utility\CsvTrait} Test Case
 */
#[CoversClass(CsvTrait::class)]
#[UsesClass(XmlTrait::class)]
class CsvTraitTest extends TestCase
{
    use CsvTrait;

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
     * Test `readCsv` method with a file that does not exist.
     *
     * @return void
     */
    public function testReadNotFound(): void
    {
        $path = TEST_FILES . DS . 'not-found.csv';

        $expected = new RuntimeException(sprintf('Cannot open file: %s', $path));
        $this->expectExceptionObject($expected);

        $this->readCsv($path)->next();
    }

    /**
     * Test `readCsv` method
     *
     * @return void
     */
    public function testReadCsv(): void
    {
        $expected = [
            ['title' => 'The Great Gatsby', 'author' => 'Francis Scott Fitzgerald'],
            ['title' => 'Moby-Dick', 'author' => 'Herman Melville'],
            ['title' => 'Ulysses', 'author' => 'James Joyce'],
            ['title' => 'Hearth of Darkness', 'author' => 'Joseph Conrad'],
        ];

        $actual = iterator_to_array($this->readCsv(TEST_FILES . DS . 'authors.csv'));

        static::assertSame($expected, $actual);
    }
}
