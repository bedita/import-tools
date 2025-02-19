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

namespace BEdita\ImportTools\Command;

use BEdita\Core\Model\Entity\User;
use BEdita\Core\Model\Table\UsersTable;
use BEdita\Core\Utility\LoggedUser;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Faker\Factory;
use Faker\Generator;

/**
 * Anonymize users command.
 */
class AnonymizeUsersCommand extends Command
{
    /**
     * Users to preserve by id.
     *
     * @var array
     */
    protected array $preserveUsers = [
        1, // admin
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->addOptions([
                'id' => [
                    'help' => 'User id',
                ],
                'preserve' => [
                    'help' => 'Users to preserve by id',
                    'default' => '1',
                ],
            ]);
    }

    /**
     * {@inheritDoc}
     *
     * Anonymize Users command.
     *
     * $ bin/cake anonymize_users --help
     *
     * Usage:
     * cake anonymize_users [options]
     *
     * Options:
     *
     * --id           User id
     * --preserve     Users to preserve by id
     * --help, -h     Display this help.
     * --verbose, -v  Enable verbose output.
     *
     * # basic
     * $ bin/cake anonymize_users --id 2
     * $ bin/cake anonymize_users --preserve 1,2,3
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $io->success('Start.');
        LoggedUser::setUserAdmin();
        /** @var \BEdita\Core\Model\Table\UsersTable $table */
        $table = $this->fetchTable('Users');
        $this->preserveUsers = array_map('intval', explode(',', $args->getOption('preserve')));
        $query = $table->find()
            ->where([
                $table->aliasField('locked') => 0,
                $table->aliasField('deleted') => 0,
                $table->aliasField('id') . ' NOT IN' => $this->preserveUsers,
            ]);
        $id = $args->getOption('id');
        if ($id) {
            $query = $query->where([$table->aliasField('id') => $id]);
        }
        $faker = Factory::create('it_IT');
        $processed = $saved = $errors = 0;
        /** @var \BEdita\Core\Model\Entity\User $user */
        foreach ($this->objectsGenerator($query, $table) as $user) {
            $this->anonymize($faker, $user, $table, $io, $processed, $saved, $errors);
        }
        $io->out(sprintf('Users processed: %s', $processed));
        $io->out(sprintf('Users saved: %s', $saved));
        $io->out(sprintf('Users not saved: %s', $errors));
        $io->success('Done.');

        return Command::CODE_SUCCESS;
    }

    /**
     * Update user.
     *
     * @param \Faker\Generator $faker Faker generator
     * @param \BEdita\Core\Model\Entity\User $user User entity
     * @param \BEdita\Core\Model\Table\UsersTable $table Users table
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param int $processed Processed users
     * @param int $saved Saved users
     * @param int $errors Errors
     * @return void
     */
    public function anonymize(Generator $faker, User $user, UsersTable $table, ConsoleIo $io, int &$processed, int &$saved, int &$errors): void
    {
        $io->verbose(sprintf('Processing user %s [username: %s, email: %s]', $user->id, $user->username, $user->email));
        $user->name = $faker->firstName();
        $user->surname = $faker->lastName();
        $user->email = sprintf('%s.%s.%d@%s', Text::slug($user->name), Text::slug($user->surname), $user->id, $faker->safeEmailDomain());
        $user->uname = sprintf('user-%s', Text::uuid());
        $user->username = $user->email;
        $processed++;
        try {
            $table->saveOrFail($user);
            $this->log(sprintf('[OK] User %s updated', $user->id), 'debug');
            $saved++;
            $io->verbose(sprintf('Saved %s as [username: %s, email: %s]', $user->id, $user->username, $user->email));
        } catch (\Exception $e) {
            $this->log(sprintf('[KO] User %s not updated', $user->id), 'error');
            $errors++;
            $io->verbose(sprintf('Error %s as [username: %s, email: %s]', $user->id, $user->username, $user->email));
        }
    }

    /**
     * Objects generator.
     *
     * @param \Cake\ORM\Query $query Query object
     * @param \Cake\ORM\Table $table Table object
     * @param int $limit The page size
     * @return \Generator
     */
    protected function objectsGenerator(Query $query, Table $table, int $limit = 1000): \Generator
    {
        $lastId = 0;
        while (true) {
            $q = clone $query;
            $q = $q->where(fn(QueryExpression $exp): QueryExpression => $exp->gt($table->aliasField('id'), $lastId));
            $q = $q->limit($limit);
            $results = $q->all();
            if ($results->isEmpty()) {
                break;
            }
            foreach ($results as $entity) {
                $lastId = $entity->id;

                yield $entity;
            }
        }
    }
}
