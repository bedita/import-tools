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

namespace BEdita\ImportTools\Utility;

use Cake\Console\ConsoleIo;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;

/**
 * Project utility
 */
class Project
{
    use LocatorAwareTrait;

    /**
     * @var \BEdita\Core\Model\Table\ApplicationsTable
     */
    protected $Applications;

    /**
     * @var \BEdita\Core\Model\Table\UsersTable
     */
    protected $Users;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        /** @var \BEdita\Core\Model\Table\ApplicationsTable $applications */
        $applications = $this->fetchTable('Applications');
        $this->Applications = $applications;

        /** @var \BEdita\Core\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        $this->Users = $users;
    }

    /**
     * Check if `import` and `default` datasources are correctly configured
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return bool
     */
    public function checkDatasourceConfig(ConsoleIo $io): bool
    {
        if (!in_array('import', ConnectionManager::configured())) {
            $io->error('Unable to connect to `import` datasource, please review "Datasource" configuration');

            return false;
        }
        $importConnection = ConnectionManager::get('import');
        $defaultConnection = ConnectionManager::get('default');
        if (!$importConnection instanceof Connection || !$defaultConnection instanceof Connection) {
            $io->error('Wrong connection type, please review "Datasource" configuration');

            return false;
        }

        return true;
    }

    /**
     * Load applications on a given connection
     * Return an array having `name` as key,
     *
     * @param \Cake\Database\Connection $connection The Connection
     * @return array
     */
    public function loadApplications(Connection $connection): array
    {
        $this->Applications->setConnection($connection);
        $apps = $this->Applications->find()->select(['name', 'api_key', 'client_secret'])->toArray();

        return Hash::combine($apps, '{n}.name', '{n}');
    }

    /**
     * Load users on a given connection
     * Return an array having `username` as key,
     *
     * @param \Cake\Database\Connection $connection The Connection
     * @return array
     */
    public function loadUsers(Connection $connection): array
    {
        $this->Users->setConnection($connection);
        $users = $this->Users->find()->select(['username', 'password_hash'])->toArray();

        return Hash::combine($users, '{n}.username', '{n}');
    }

    /**
     * Update applications api keys using api keys provided in input array
     *
     * @param \Cake\Database\Connection $connection The connection
     * @param array $applications Application data
     * @return void
     */
    public function updateApplications(Connection $connection, array $applications): void
    {
        $this->Applications->setConnection($connection);
        foreach ($applications as $name => $application) {
            /** @var \BEdita\Core\Model\Entity\Application $entity */
            $entity = $this->Applications->find()->where(['name' => $name])->firstOrFail();
            $entity->api_key = $application->api_key;
            $entity->client_secret = $application->client_secret;
            $this->Applications->saveOrFail($entity);
        }
    }

    /**
     * Update applications api keys using api keys provided in input array
     *
     * @param \Cake\Database\Connection $connection The connection
     * @param array $users Users data
     * @return void
     */
    public function updateUsers(Connection $connection, array $users): void
    {
        foreach ($users as $username => $user) {
            if (empty($user->password_hash)) {
                continue;
            }
            $query = sprintf(
                "UPDATE users SET password_hash = '%s' WHERE username = '%s'",
                $user->password_hash,
                $username
            );
            $connection->execute($query);
        }
    }

    /**
     * Review applications and update api keys
     *
     * @param \Cake\Database\Connection $defaultConnection The default connection
     * @param \Cake\Database\Connection $importConnection The import connection
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return bool
     */
    public function reviewApplications(Connection $defaultConnection, Connection $importConnection, ConsoleIo $io): bool
    {
        $current = $this->loadApplications($defaultConnection);
        $import = $this->loadApplications($importConnection);
        $missing = array_diff(array_keys($import), array_keys($current));
        if (!empty($missing)) {
            $io->error(sprintf('Some applications are missing on current project: %s', implode(' ', $missing)));

            return false;
        }
        $update = array_intersect_key($current, $import);
        $this->updateApplications($importConnection, $update);

        return true;
    }

    /**
     * Review users and update password hashes
     *
     * @param \Cake\Database\Connection $defaultConnection The default connection
     * @param \Cake\Database\Connection $importConnection The import connection
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return bool
     */
    public function reviewUsers(Connection $defaultConnection, Connection $importConnection, ConsoleIo $io): bool
    {
        $current = $this->loadUsers($defaultConnection);
        $import = $this->loadUsers($importConnection);
        $missing = array_diff(array_keys($import), array_keys($current));
        if (!empty($missing)) {
            $io->warning(sprintf('Some users are missing in current project [%d]', count($missing)));
            if ($io->askChoice('Do you want to proceed?', ['y', 'n'], 'n') === 'n') {
                $io->error('Aborting.');

                return false;
            }
        }
        $update = array_intersect_key($current, $import);
        $this->updateUsers($importConnection, $update);

        return true;
    }
}
