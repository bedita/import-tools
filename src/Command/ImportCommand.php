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

use BEdita\Core\Model\Table\RolesTable;
use BEdita\Core\Model\Table\UsersTable;
use BEdita\Core\Utility\LoggedUser;
use BEdita\ImportTools\Utility\CsvTrait;
use BEdita\ImportTools\Utility\TreeTrait;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Http\Exception\BadRequestException;
use Cake\Utility\Hash;

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
    use CsvTrait;
    use TreeTrait;

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'defaults' => [
            'status' => 'on',
        ],
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '"',
        ],
    ];

    /**
     * Dry run mode flag
     *
     * @var bool
     */
    protected bool $dryrun = false;

    /**
     * Full filename path
     *
     * @var string|null
     */
    protected ?string $filename = '';

    /**
     * Parent uname or ID
     *
     * @var string|null
     */
    protected ?string $parent = '';

    /**
     * Number of processed entities
     *
     * @var int
     */
    protected int $processed = 0;

    /**
     * Number of saved entities
     *
     * @var int
     */
    protected int $saved = 0;

    /**
     * Entity type
     *
     * @var string
     */
    protected string $type = '';

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
        $this->filename = $args->getOption('file');
        if (!file_exists($this->filename)) {
            $io->out(sprintf('Bad csv source file name "%s"', $this->filename));

            return;
        }
        $this->type = $args->getOption('type');
        $this->parent = $args->getOption('parent');
        if ($args->getOption('dryrun')) {
            $this->dryrun = true;
        }
        $io->out('---------------------------------------');
        $io->out('Start');
        $io->out(sprintf('File: %s', $this->filename));
        $io->out(sprintf('Type: %s', $this->type));
        $io->out(sprintf('Parent: %s', empty($this->parent) ? 'none' : $this->parent));
        $io->out(sprintf('Dry run mode: %s', $this->dryrun === true ? 'yes' : 'no'));
        LoggedUser::setUser(['id' => UsersTable::ADMIN_USER, 'roles' => [['id' => RolesTable::ADMIN_ROLE]]]);
        $method = $this->type !== 'translations' ? 'objects' : 'translations';
        $this->$method();
        $io->out(sprintf('Processed: %d, Saved: %d', $this->processed, $this->saved));
        $io->out('Done, bye!');
        $io->out('---------------------------------------');
    }

    /**
     * Save objects
     *
     * @return void
     */
    protected function objects(): void
    {
        $objectsTable = $this->fetchTable('objects');
        $table = $this->fetchTable($this->type);
        foreach ($this->readCsv($this->filename) as $obj) {
            $this->processed++;
            $entity = $table->newEmptyEntity();
            if (!empty($obj['uname'])) {
                $uname = $obj['uname'];
                if ($objectsTable->exists(compact('uname'))) {
                    /** @var \BEdita\Core\Model\Entity\ObjectEntity $o */
                    $o = $objectsTable->find()->where(compact('uname'))->firstOrFail();
                    if ($o->type !== $this->type) {
                        throw new BadRequestException(
                            sprintf('Object uname "%s" already present with another type "%s"', $uname, $o->type)
                        );
                    }
                    $entity = $table->get($table->getId($uname));
                }
            }
            if ($this->dryrun === true) {
                continue;
            }
            $entity = $table->patchEntity($entity, $obj);
            $entity->set('type', $this->type);
            $table->saveOrFail($entity);
            if (isset($this->parent)) {
                $this->setParent($entity, $this->parent);
            }
            $this->saved++;
        }
    }

    /**
     * Save translations
     *
     * @return void
     */
    protected function translations(): void
    {
        foreach ($this->readCsv($this->filename) as $translation) {
            $this->processed++;
            $this->translationFields($translation);
            if ($this->dryrun === true) {
                continue;
            }
            $this->saveTranslation($translation);
            $this->saved++;
        }
    }

    /**
     * Setup translations fields
     *
     * @param array $translation Translation data
     * @return void
     */
    protected function translationFields(array &$translation): void
    {
        $objectUname = (string)Hash::get($translation, 'object_uname');
        $objectEntity = $this->fetchTable('Objects')->find('unameId', [$objectUname])->firstOrFail();
        $entity = $this->fetchTable('Translations')->find()->where([
            'object_id' => $objectEntity->id,
            'lang' => $translation['lang'],
        ])->first();
        if ($entity) {
            $translation['id'] = $entity->id;
        }
        unset($translation['object_uname']);
        $translation['object_id'] = $objectEntity->id;
    }

    /**
     * Save translation
     *
     * @param array $translation Translation data
     * @return void
     */
    protected function saveTranslation(array &$translation): void
    {
        $table = $this->fetchTable('Translations');
        $entity = $table->newEntity($translation);
        $entity->set('translated_fields', json_decode($translation['translated_fields'], true));
        $entity->set('status', $this->getConfig('defaults')['status']);
        if (!empty($translation['id'])) {
            $entity->set('id', $translation['id']);
        }
        $table->saveOrFail($entity);
    }
}
