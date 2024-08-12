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

use BEdita\Core\Model\Table\UsersTable;
use BEdita\Core\Utility\LoggedUser;
use BEdita\ImportTools\Command\AnonymizeUsersCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\EntityInterface;
use Cake\TestSuite\TestCase;
use Faker\Factory;

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
        'plugin.BEdita/Core.Relations',
        'plugin.BEdita/Core.RelationTypes',
        'plugin.BEdita/Core.Objects',
        'plugin.BEdita/Core.ObjectRelations',
        'plugin.BEdita/Core.Locations',
        'plugin.BEdita/Core.Media',
        'plugin.BEdita/Core.Profiles',
        'plugin.BEdita/Core.Users',
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
     * @covers ::updateUser()
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
     * @covers ::updateUser()
     */
    public function testExecute(): void
    {
        LoggedUser::setUserAdmin();
        $table = $this->fetchTable('Users');
        /** @var \BEdita\Core\Model\Entity\User $user */
        $user = $table->newEmptyEntity();
        $user->username = 'gustavo';
        $user->email = 'gustavo@bedita.net';
        $user->name = 'Gustavo';
        $user->surname = 'Supporto';
        $user->status = 'on';
        $table->saveOrFail($user);
        $originalUsers = $table->find()->toArray();
        $this->exec('anonymize_users --preserve ' . $user->id);
        $this->assertOutputContains('Start');
        $this->assertOutputContains('Users processed: 1');
        $this->assertOutputContains('Users saved: 1');
        $this->assertOutputContains('Users not saved: 0');
        $this->assertOutputContains('Done.');
        $users = $table->find()->toArray();
        foreach ($users as $user) {
            $tmp = array_filter($originalUsers, function ($originalUser) use ($user) {
                return $originalUser->id === $user->id;
            });
            $originalUser = array_values($tmp)[0];
            if ($originalUser->username === 'gustavo' || $originalUser->id === 1) {
                $this->assertEquals($originalUser->name, $user->name);
                $this->assertEquals($originalUser->surname, $user->surname);
                $this->assertEquals($originalUser->username, $user->username);
                $this->assertEquals($originalUser->email, $user->email);
                $this->assertEquals($originalUser->status, $user->status);
                continue;
            }
            $this->assertNotEquals($originalUser->name, $user->name);
            $this->assertNotEquals($originalUser->surname, $user->surname);
            $this->assertNotEquals($originalUser->username, $user->username);
            $this->assertNotEquals($originalUser->email, $user->email);
        }
    }

    /**
     * Test anonymize
     *
     * @return void
     * @covers ::anonymize()
     */
    public function testAnonymize(): void
    {
        LoggedUser::setUserAdmin();
        $faker = Factory::create('it_IT');
        $processed = $saved = $errors = 0;
        /** @var \BEdita\Core\Model\Table\UsersTable $table */
        $table = $this->fetchTable('Users');
        /** @var \BEdita\Core\Model\Entity\User $user */
        $user = $table->newEmptyEntity();
        $this->command->anonymize($faker, $user, $table, new ConsoleIo(), $processed, $saved, $errors);
        $this->assertEquals(0, $errors);
        $this->assertEquals(1, $processed);
        $this->assertEquals(1, $saved);
    }

    /**
     * Test anonymize exception
     *
     * @return void
     * @covers ::anonymize()
     */
    public function testAnonymizeException(): void
    {
        LoggedUser::setUserAdmin();
        $faker = Factory::create('it_IT');
        $processed = $saved = $errors = 0;
        /** @var \BEdita\Core\Model\Table\UsersTable $table */
        $table = $this->fetchTable('Users');
        /** @var \BEdita\Core\Model\Entity\User $user */
        $user = $table->newEmptyEntity();
        $myTable = new class () extends UsersTable
        {
            public function aliasField(string $field): string
            {
                return $field;
            }

            public function exists($conditions): bool
            {
                return false;
            }

            public function saveOrFail($entity, $options = []): EntityInterface
            {
                throw new \Cake\Datasource\Exception\RecordNotFoundException();
            }
        };
        $this->command->anonymize($faker, $user, $myTable, new ConsoleIo(), $processed, $saved, $errors);
        $this->assertEquals(1, $errors);
        $this->assertEquals(1, $processed);
        $this->assertEquals(0, $saved);
    }
}
