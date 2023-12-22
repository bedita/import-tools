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

namespace BEdita\ImportTools\Command;

use BEdita\Core\Utility\LoggedUser;
use BEdita\ImportTools\Utility\Import;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Import command.
 *
 * $ bin/cake import --help
 *
 * Usage:
 * cake import [options]
 *
 * Options:
 *
 * --dryrun, -d   dry run mode
 * --file, -f     CSV file to import (required)
 * --help, -h     Display this help.
 * --parent, -p   destination folder uname
 * --quiet, -q    Enable quiet output.
 * --type, -t     entity type to import (required)
 * --verbose, -v  Enable verbose output.
 *
 * # basic
 * $ bin/cake import --file documents.csv --type documents
 * $ bin/cake import -f documents.csv -t documents
 *
 * # dry-run
 * $ bin/cake import --file articles.csv --type articles --dryrun yes
 * $ bin/cake import -f articles.csv -t articles -d yes
 *
 * # destination folder
 * $ bin/cake import --file news.csv --type news --parent my-folder-uname
 * $ bin/cake import -f news.csv -t news -p my-folder-uname
 *
 * # translations
 * $ bin/cake import --file translations.csv --type translations
 * $ bin/cake import -f translations.csv -t translations
 */
class ImportCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOption('file', [
                'help' => 'CSV file to import',
                'required' => true,
                'short' => 'f',
            ])
            ->addOption('type', [
                'help' => 'entity type to import',
                'required' => true,
                'short' => 't',
            ])
            ->addOption('parent', [
                'help' => 'destination folder uname',
                'required' => false,
                'short' => 'p',
            ])
            ->addOption('dryrun', [
                'help' => 'dry run mode',
                'required' => false,
                'short' => 'd',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $filename = $args->getOption('file');
        if (!file_exists($filename)) {
            $io->out(sprintf('Bad csv source file name "%s"', $filename));

            return;
        }
        $type = $args->getOption('type');
        $parent = $args->getOption('parent');
        $dryrun = $args->getOption('dryrun') ? true : false;
        $io->out('---------------------------------------');
        $io->out('Start');
        $io->out(sprintf('File: %s', $filename));
        $io->out(sprintf('Type: %s', $type));
        $io->out(sprintf('Parent: %s', empty($parent) ? 'none' : $parent));
        $io->out(sprintf('Dry run mode: %s', $dryrun === true ? 'yes' : 'no'));
        LoggedUser::setUserAdmin();
        $import = new Import($filename, $type, $parent, $dryrun);
        $method = $type !== 'translations' ? 'objects' : 'translations';
        $import->$method();
        $io->out(
            sprintf(
                'Processed: %d, Saved: %d, Errors: %d',
                $import->processed,
                $import->saved,
                $import->errors
            )
        );
        $io->out('Done, bye!');
        $io->out('---------------------------------------');
    }
}
