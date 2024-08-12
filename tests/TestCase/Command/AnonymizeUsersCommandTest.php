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
namespace BEdita\ImportTools\Test\TestCase\Command;

use BEdita\ImportTools\Command\AnonymizeUsersCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\ImportTools\Command\AnonymizeUsersCommand} Test Case
 *
 * @coversDefaultClass \BEdita\ImportTools\Command\AnonymizeUsersCommand
 */
class AnonymizeUsersCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @inheritDoc
     */
    public $fixtures = [
        'plugin.BEdita/Core.ObjectTypes',
        'plugin.BEdita/Core.PropertyTypes',
        'plugin.BEdita/Core.Properties',
        'plugin.BEdita/Core.Objects',
        'plugin.BEdita/Core.Users',
        'plugin.BEdita/Core.Trees',
    ];

    /**
     * The command used in test
     *
     * @var \BEdita\ImportTools\Command\AnonymizeUsersCommand
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
        $this->command = new AnonymizeUsersCommand();
    }

    /**
     * Test buildOptionParser method
     *
     * @return void
     * @covers ::buildOptionParser()
     */
    public function testBuildOptionParser(): void
    {
        $this->exec('anonymize_users --help');
        $this->assertOutputContains('cake anonymize_users [-h] [--id] [--preserve 1] [-q] [-v]');
        $this->assertOutputContains('--id');
        $this->assertOutputContains('User id');
        $this->assertOutputContains('--preserve');
        $this->assertOutputContains('Users to preserve by id');
        $this->assertOutputContains('--help, -h');
        $this->assertOutputContains('Display this help.');
        $this->assertOutputContains('--verbose, -v');
        $this->assertOutputContains('Enable verbose output.');
    }

    /**
     * Test execute method
     *
     * @return void
     * @covers ::execute()
     * @covers ::objectsIterator()
     */
    public function testExecuteById(): void
    {
        $this->exec('anonymize_users --id 999999999');
        $this->assertOutputContains('Start');
        $this->assertOutputContains('Users processed: 0');
        $this->assertOutputContains('Users saved: 0');
        $this->assertOutputContains('Users not saved: 0');
        $this->assertOutputContains('Done.');
    }

    /**
     * Test execute method
     *
     * @return void
     * @covers ::execute()
     * @covers ::objectsIterator()
     */
    public function testExecute(): void
    {
        $this->exec('anonymize_users');
        $this->assertOutputContains('Start');
        $this->assertOutputContains('Users processed: 0');
        $this->assertOutputContains('Users saved: 0');
        $this->assertOutputContains('Users not saved: 0');
        $this->assertOutputContains('Done.');
    }
}
