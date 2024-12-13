<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2024 Atlas Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\ImportTools\Test\TestCase\Utility;

use BEdita\ImportTools\Utility\XmlTrait;
use PHPUnit\Framework\TestCase;

/**
 * {@see \BEdita\ImportTools\Utility\XmlTrait} Test Case
 *
 * @covers \BEdita\ImportTools\Utility\XmlTrait
 */
class XmlTraitTest extends TestCase
{
    use XmlTrait;

    /**
     * Test `readXml` method with a file that does not exist.
     *
     * @return void
     */
    public function testReadNotFound(): void
    {
        $path = TEST_FILES . DS . 'not-found.xml';

        $expected = new \RuntimeException(sprintf('Cannot open file: %s', $path));
        $this->expectExceptionObject($expected);

        $this->readXml($path, 'post')->next();
    }

    /**
     * Test `readXml` method
     *
     * @return void
     */
    public function testReadXml(): void
    {
        $expected = [
            ['title' => 'The Great Gatsby', 'author' => 'Francis Scott Fitzgerald'],
            ['title' => 'Moby-Dick', 'author' => 'Herman Melville'],
            ['title' => 'Ulysses', 'author' => 'James Joyce'],
            ['title' => 'Hearth of Darkness', 'author' => 'Joseph Conrad'],
        ];

        $actual = iterator_to_array($this->readXml(TEST_FILES . DS . 'authors.xml', 'post'));

        static::assertSame($expected, $actual);
    }
}
