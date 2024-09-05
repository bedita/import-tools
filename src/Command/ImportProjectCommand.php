<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2024 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\ImportTools\Command;

use BEdita\ImportTools\Utility\Project;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/**
 * Command to prepare `applications` and `users` to import a project in a new environment.
 * Since applications api keys and users passwords will generally differ this command will try to
 * sync those values, when possible, in order to enable migration.
 *
 * The new project database you want to import must be reachable via an `import` key Datasource configuration.
 * This new project will be modified this way:
 *  - new project `applications` must be present as name on the current project DB => current api keys are saved in the new imported project
 *  - users password hashes are changed (if set) in the new imported project using current users password hashes on records with the same `username`
 *
 * After this command is finished the new imported project can be used in the current environment and replace current project/database.
 */
class ImportProjectCommand extends Command
{
    /**
     * {@inheritDoc}
     *
     * Main command execution:
     * - applications and users are loaded from current and imported project
     * - applications api keys and users passwords are updated to be used in current environment
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $io->out('Start');
        $project = new Project($io);
        if ($project->checkDatasourceConfig() === false) {
            $this->abort();
        }
        if ($project->reviewApplications() === false) {
            $this->abort();
        }
        if ($project->reviewUsers() === false) {
            $this->abort();
        }
        $io->success('Import project done.');
        $io->out('End');

        return static::CODE_SUCCESS;
    }
}
