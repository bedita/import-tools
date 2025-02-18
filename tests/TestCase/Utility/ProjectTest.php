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

use BEdita\Core\Utility\LoggedUser;
use BEdita\ImportTools\Utility\Project;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;

/**
 * {@see \BEdita\ImportTools\Utility\Project} Test Case
 *
 * @covers \BEdita\ImportTools\Utility\Project
 */
class ProjectTest extends TestCase
{
    use LocatorAwareTrait;

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
     * ConsoleIo for testing
     *
     * @var object
     */
    public object $io;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->io = new class extends ConsoleIo {
            public string $err = '';
            public string $warn = '';

            public function getErr(): string
            {
                return $this->err;
            }

            public function getWarn(): string
            {
                return $this->warn;
            }

            public function warning($message, int $newlines = 1): int
            {
                $this->warn = $message;

                return parent::warning($message, $newlines);
            }

            public function error($message, int $newlines = 1): int
            {
                $this->err = $message;

                return parent::error($message, $newlines);
            }
        };
    }

    /**
     * Test `checkDatasourceConfig` method on missing 'import' datasource
     *
     * @return void
     */
    public function testCheckDatasourceConfigUnableToConnect(): void
    {
        $project = new Project($this->io);
        ConnectionManager::drop('import');
        $actual = $project->checkDatasourceConfig();
        $this->assertFalse($actual);
        $expected = 'Unable to connect to `import` datasource, please review "Datasource" configuration';
        $this->assertSame($expected, $this->io->getErr());
    }

    /**
     * Test `checkDatasourceConfig` method on wrong connection type
     *
     * @return void
     */
    public function testCheckDatasourceConfigWrongConnectionType(): void
    {
        $project = new class ($this->io) extends Project
        {
            public function setImportConnection($connection): void
            {
                $this->importConnection = $connection;
            }
        };
        ConnectionManager::drop('import');
        ConnectionManager::setConfig('import', ['url' => 'sqlite:///:memory:']);
        $project->setImportConnection(null);
        $actual = $project->checkDatasourceConfig();
        $this->assertFalse($actual);
        $expected = 'Wrong connection type, please review "Datasource" configuration';
        $this->assertSame($expected, $this->io->getErr());
    }

    /**
     * Test `checkDatasourceConfig` method
     *
     * @return void
     */
    public function testCheckDatasourceConfig(): void
    {
        ConnectionManager::drop('import');
        ConnectionManager::setConfig('import', ['url' => getenv('db_dsn')]);
        $project = new Project($this->io);
        $actual = $project->checkDatasourceConfig();
        $this->assertTrue($actual);
        $this->assertEmpty($this->io->getErr());
    }

    /**
     * Test `loadApplications` method
     *
     * @return void
     */
    public function testLoadApplications(): void
    {
        /** @var \BEdita\Core\Model\Table\ApplicationsTable $table */
        $table = $this->fetchTable('Applications');
        /** @var \BEdita\Core\Model\Entity\Application $entity */
        $entity = $table->newEmptyEntity();
        $entity->name = 'test-app';
        $table->save($entity);
        /** @var \Cake\Database\Connection $defaultConnection */
        $defaultConnection = ConnectionManager::get('default');
        $project = new Project($this->io);
        $applications = $project->loadApplications($defaultConnection);
        $this->assertNotEmpty($applications);
        $actual = Hash::get($applications, 'test-app');
        $this->assertSame('test-app', $actual->name);
        $this->assertNotEmpty($actual->api_key);
    }

    /**
     * Test `loadUsers` method
     *
     * @return void
     */
    public function testLoadUsers(): void
    {
        /** @var \Cake\Database\Connection $defaultConnection */
        $defaultConnection = ConnectionManager::get('default');
        $project = new class ($this->io) extends Project
        {
            // mock Users table so that $this->Users->find()->select(['username', 'password_hash'])->toArray() returns mocked data
            public function __construct($io)
            {
                parent::__construct($io);
                /** @var \BEdita\Core\Model\Table\UsersTable $users */
                $users = new class {
                    public function setConnection($connection): void
                    {
                    }

                    public function find(): object
                    {
                        return $this;
                    }

                    public function select($fields): object
                    {
                        return $this;
                    }

                    public function toArray(): array
                    {
                        return [
                            new class {
                                public string $username = 'test-user';
                                public string $password_hash = 'test-password-hash';
                            },
                        ];
                    }
                };
                $this->Users = $users;
            }
        };
        $users = $project->loadUsers($defaultConnection);
        $this->assertNotEmpty($users);
        $actual = Hash::get($users, 'test-user');
        $this->assertSame('test-user', $actual->username);
        $this->assertSame('test-password-hash', $actual->password_hash);
    }

    /**
     * Test `updateApplications` method
     *
     * @return void
     */
    public function testUpdateApplications(): void
    {
        /** @var \BEdita\Core\Model\Table\ApplicationsTable $table */
        $table = $this->fetchTable('Applications');
        /** @var \BEdita\Core\Model\Entity\Application $entity */
        $entity = $table->newEmptyEntity();
        $entity->name = 'test-app';
        $table->save($entity);
        /** @var \Cake\Database\Connection $defaultConnection */
        $defaultConnection = ConnectionManager::get('default');
        $project = new Project($this->io);
        $applications = $project->loadApplications($defaultConnection);
        $updatedApplications = $applications;
        $updatedApplications['test-app']->api_key = 'new-api-key';
        $updatedApplications['test-app']->client_secret = 'new-client-secret';
        $project->updateApplications($defaultConnection, $applications);
        $applications = $project->loadApplications($defaultConnection);
        $actual = $applications['test-app'];
        $this->assertSame('new-api-key', $actual->api_key);
        $this->assertSame('new-client-secret', $actual->client_secret);
    }

    /**
     * Test `updateUsers` method
     *
     * @return void
     */
    public function testUpdateUsers(): void
    {
        LoggedUser::setUserAdmin();
        /** @var \BEdita\Core\Model\Table\UsersTable $table */
        $table = $this->fetchTable('Users');
        $user = $table->newEntity([]);
        $data = [
            'username' => 'some_unique_value',
            'password_hash' => 'password',
            'email' => 'my@email.com',
            'status' => 'draft',
        ];
        $table->patchEntity($user, $data);
        $table->save($user);
        $hash = $user->password_hash;
        unset($user->password_hash);
        $users = [$user->username => $user];
        /** @var \Cake\Database\Connection $defaultConnection */
        $defaultConnection = ConnectionManager::get('default');
        $project = new Project($this->io);
        // do not change password hash, skip update user
        $project->updateUsers($defaultConnection, $users);
        $users = $project->loadUsers($defaultConnection);
        $actual = Hash::get($users, 'some_unique_value');
        $this->assertNotEmpty($actual);
        $this->assertSame($hash, $actual->password_hash);

        // pass password_hash, update user
        $user->password_hash = $hash;
        $users = [$user->username => $user];
        $project->updateUsers($defaultConnection, $users);
        $users = $project->loadUsers($defaultConnection);
        $actual = Hash::get($users, 'some_unique_value');
        $this->assertNotEmpty($actual);
        $this->assertNotSame($hash, $actual->password_hash);
    }

    /**
     * Test `reviewApplications` method on missing applications
     *
     * @return void
     */
    public function testReviewApplicationsMissing(): void
    {
        $project = new class ($this->io) extends Project {
            public function loadApplications($connection): array
            {
                return $connection->configName() === 'test-import' ? ['test-app' => (object)['name' => 'test-app']] : [];
            }
        };
        $actual = $project->reviewApplications();
        $this->assertFalse($actual);
        $this->assertSame('Some applications are missing on current project: test-app', $this->io->getErr());
    }

    /**
     * Test `reviewApplications` method
     *
     * @return void
     */
    public function testReviewApplications(): void
    {
        $project = new class ($this->io) extends Project {
            public function loadApplications($connection): array
            {
                return [];
            }
        };
        $actual = $project->reviewApplications();
        $this->assertTrue($actual);
        $this->assertEmpty($this->io->getErr());
    }

    /**
     * Test `reviewUsers` method on missing users
     *
     * @return void
     */
    public function testReviewUsersMissing(): void
    {
        $this->io->setInteractive(false);
        $project = new class ($this->io) extends Project {
            public function loadUsers($connection): array
            {
                return $connection->configName() === 'test-import' ? ['test-user' => (object)['username' => 'test-user']] : [];
            }
        };
        $actual = $project->reviewUsers();
        $this->assertFalse($actual);
        $this->assertSame('Some users are missing in current project [1]', $this->io->getWarn());
        $this->assertSame('Aborting.', $this->io->getErr());
    }

    /**
     * Test `reviewUsers` method
     *
     * @return void
     */
    public function testReviewUsers(): void
    {
        $project = new class ($this->io) extends Project {
            public function loadUsers($connection): array
            {
                return [];
            }
        };
        $actual = $project->reviewUsers();
        $this->assertTrue($actual);
    }
}
