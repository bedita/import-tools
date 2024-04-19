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

namespace BEdita\ImportTools\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * {@see \App\Command\TranslateObjectsCommand} Test Case
 *
 * @coversDefaultClass \App\Command\TranslateObjectsCommand
 */
class TranslateObjectsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    /**
     * Test `execute` with code error on no file
     *
     * @return void
     * @covers ::buildOptionParser()
     * @covers ::execute()
     */
    public function testExecute(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `objectsIterator` method
     *
     * @return void
     * @covers ::objectsIterator()
     */
    public function testObjectsIterator(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `translate` method
     *
     * @return void
     * @covers ::translate()
     */
    public function testTranslate(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `translatableFields` method
     *
     * @return void
     * @covers ::translatableFields()
     */
    public function testTranslatableFields(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `singleTranslation` method
     *
     * @return void
     * @covers ::singleTranslation()
     */
    public function testSingleTranslation(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
