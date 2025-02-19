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
use Cake\Command\Command;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\ImportTools\Command\ImportProjectCommand} Test Case
 *
 * @covers \BEdita\ImportTools\Command\ImportProjectCommand
 */
class ImportProjectCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @inheritDoc
     */
    public $fixtures = [
        'plugin.BEdita/Core.ObjectTypes',
        'plugin.BEdita/Core.Objects',
        'plugin.BEdita/Core.Locations',
        'plugin.BEdita/Core.Media',
        'plugin.BEdita/Core.Profiles',
        'plugin.BEdita/Core.Users',
        'plugin.BEdita/Core.Roles',
        'plugin.BEdita/Core.RolesUsers',
    ];

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
        ConnectionManager::drop('import');
        ConnectionManager::setConfig('import', ['url' => 'sqlite:///:memory:']);
        $this->exec(sprintf('import_project'));
        $this->assertOutputContains('Start');
        $this->assertOutputContains('Import project done.');
        $this->assertOutputContains('End');
        $this->assertExitCode(Command::CODE_SUCCESS);
    }

    /**
     * Test execute method, on checkDatasourceConfig false
     *
     * @return void
     */
    public function testExecuteMissingImportDatasource(): void
    {
        ConnectionManager::drop('import');
        $this->exec(sprintf('import_project'));
        $this->assertOutputContains('Start');
        $this->assertExitError('Unable to connect to `import` datasource, please review "Datasource" configuration');
        $this->assertOutputNotContains('Import project done.');
        $this->assertOutputNotContains('End');
    }

    /**
     * Test execute method, reviewApplications false
     *
     * @return void
     */
    public function testExecuteReviewApplicationsFalse(): void
    {
        ConnectionManager::drop('import');
        ConnectionManager::setConfig('import', ['url' => 'sqlite:///:memory:']);
        /** @var \BEdita\Core\Model\Table\ApplicationsTable $table */
        $table = $this->fetchTable('Applications', [
            'connectionName' => 'import',
        ]);
        /** @var \BEdita\Core\Model\Entity\Application $entity */
        $entity = $table->newEmptyEntity();
        $entity->name = 'test-app';
        $table->save($entity);

        $this->exec(sprintf('import_project'));
        $this->assertOutputContains('Start');
        $this->assertOutputNotContains('Import project done.');
        $this->assertOutputNotContains('End');
    }

    /**
     * Test execute method, reviewUsers false
     *
     * @return void
     */
    public function testExecuteReviewUsersFalse(): void
    {
        $this->markTestSkipped('Test skipped, some issues in fixtures with second connection import');
    }
}
