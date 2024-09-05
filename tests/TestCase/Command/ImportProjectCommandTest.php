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

use BEdita\ImportTools\Command\ImportProjectCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\ImportTools\Command\ImportProjectCommand} Test Case
 *
 * @coversDefaultClass \BEdita\ImportTools\Command\ImportProjectCommand
 */
class ImportProjectCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * The command used in test
     *
     * @var \BEdita\ImportTools\Command\ImportProjectCommand
     */
    protected $command = null;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
        $this->command = new ImportProjectCommand();
    }

    /**
     * Test execute method
     *
     * @return void
     * @covers ::execute()
     */
    public function testExecute(): void
    {
        $this->exec(sprintf('import_project'));
        $this->assertOutputContains('Start');
        $this->assertExitError('Unable to connect to `import` datasource, please review "Datasource" configuration');
    }
}
