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

use BEdita\ImportTools\Command\ImportCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\ImportTools\Command\ImportCommand} Test Case
 *
 * @coversDefaultClass \BEdita\ImportTools\Command\ImportCommand
 */
class ImportCommandTest extends TestCase
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
     * @var \BEdita\ImportTools\Command\ImportCommand
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
        $this->command = new ImportCommand();
    }

    /**
     * Test buildOptionParser method
     *
     * @return void
     * @covers ::buildOptionParser()
     */
    public function testBuildOptionParser(): void
    {
        $this->exec('import --help');
        $this->assertOutputContains('cake import [options]');
        $this->assertOutputContains('--dryrun, -d');
        $this->assertOutputContains('dry run mode');
        $this->assertOutputContains('--file, -f');
        $this->assertOutputContains('CSV file to import <comment>(required)</comment>');
        $this->assertOutputContains('--help, -h');
        $this->assertOutputContains('Display this help.');
        $this->assertOutputContains('--parent, -p');
        $this->assertOutputContains('destination folder uname');
        $this->assertOutputContains('--quiet, -q');
        $this->assertOutputContains('Enable quiet output.');
        $this->assertOutputContains('--type, -t');
        $this->assertOutputContains('entity type to import <comment>(required)</comment>');
        $this->assertOutputContains('--verbose, -v');
        $this->assertOutputContains('Enable verbose output.');
    }

    /**
     * Test execute method
     *
     * @return void
     * @covers ::execute()
     */
    public function testExecute(): void
    {
        $filename = TEST_FILES . DS . 'articles1.csv';
        $this->exec(sprintf('import -f %s -t documents --dryrun 1', $filename));
        $this->assertOutputContains('Start');
        $this->assertOutputContains('File:');
        $this->assertOutputContains('articles1.csv');
        $this->assertOutputContains('Type: documents');
        $this->assertOutputContains('Parent: none');
        $this->assertOutputContains('Dry run mode: yes');
        $this->assertOutputContains('Processed: 3, Saved: 0, Skipped: 3, Errors: 0');
        $this->assertOutputContains('Done, bye!');
    }
}
