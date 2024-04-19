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

use BEdita\Core\Utility\LoggedUser;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Database\Expression\QueryExpression;
use Cake\Utility\Hash;

/**
 * TranslateObjects command.
 * Translate objects from a language to another using a translator engine.
 * The translator engine is defined in the configuration.
 * The configuration must contain the translator engine class and options
 * I.e.:
 * 'Translators' => [
 *   'deepl' => [
 *      'name' => 'DeepL',
 *      'class' => '\BEdita\I18n\Deepl\Core\Translator',
 *      'options' => [
 *        'auth_key' => '************',
 *      ],
 *   ],
 * ],
 */
class TranslateObjectsCommand extends Command
{
    use InstanceConfigTrait;

    protected $dryRun;
    protected $defaultStatus;
    protected $translatableFields = [];
    protected $translator;
    protected $langsMap = [
        'en' => 'en-US',
        'it' => 'it-IT',
        'de' => 'de-DE',
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOption('from', [
            'short' => 'f',
            'help' => 'Language to translate from',
            'required' => true,
            'choices' => ['en', 'it', 'de'],
        ]);
        $parser->addOption('to', [
            'short' => 't',
            'help' => 'Language to translate to',
            'required' => true,
            'choices' => ['en', 'it', 'de'],
        ]);
        $parser->addOption('engine', [
            'short' => 'e',
            'help' => 'Translator engine',
            'default' => 'deepl',
            'required' => true,
            'choices' => ['aws', 'deepl', 'google', 'microsoft'],
        ]);
        $parser->addOption('status', [
            'short' => 's',
            'help' => 'Status for new translations',
            'choices' => ['draft', 'on', 'off'],
            'default' => 'draft',
        ]);
        $parser->addOption('dry-run', [
            'short' => 'd',
            'help' => 'Dry run',
            'boolean' => true,
            'default' => false,
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->dryRun = $args->getOption('dry-run');

        // parameter engine
        $engine = $args->getOption('engine') ?? 'deepl';

        // setup translator engine
        $cfg = Configure::read(sprintf('Translators.%s', $engine));
        if (empty($cfg)) {
            $io->abort(sprintf('Translator %s not found', $engine));
        }
        $class = (string)Hash::get($cfg, 'class');
        $options = (array)Hash::get($cfg, 'options');
        $this->translator = new $class();
        $this->translator->setup($options);

        // parameters: lang from, lang to
        $from = $args->getOption('from');
        $to = $args->getOption('to');
        $to = $this->langsMap['en'];
        $io->out(sprintf('Translating objects from %s to %s [dry-run %s]', $from, $to, $this->dryRun ? 'yes' : 'no'));
        if ($io->ask('Do you want to continue [Y/n]?', 'n') !== 'Y') {
            $io->abort('Bye');
        }

        $ok = $error = 0;
        $conditions = [];
        $this->defaultStatus = $args->getOption('status');
        foreach ($this->objectsIterator($conditions, $from, $to) as $object) {
            try {
                $io->verbose(sprintf('Translating object %s', $object->id));
                if (!$this->dryRun) {
                    $this->translate($object, $from, $to);
                }
                $io->verbose(sprintf('Translated object %s', $object->id));
                $ok++;
            } catch (\Exception $e) {
                $io->error(sprintf('Error translating object %s: %s', $object->id, $e->getMessage()));
                $error++;
            }
        }
        $io->out(sprintf('Processed %d objects (%d errors)', $ok + $error, $error));
        $io->out('Done');
    }

    /**
     * Get objects as iterable.
     *
     * @param array $conditions The conditions to filter objects.
     * @param string $lang The language to use to find objects.
     * @param string $to The language to translate objects to.
     * @return iterable
     */
    private function objectsIterator(array $conditions, string $lang, string $to): iterable
    {
        $table = $this->fetchTable('objects');
        $conditions = array_merge(
            $conditions,
            [
                $table->aliasField('deleted') => 0,
                $table->aliasField('lang') => $lang,
            ]
        );
        $query = $table->find('all')
            ->where($conditions)
            ->notMatching('Translations', function ($q) use ($to) {
                return $q->where(['Translations.lang' => $to]);
            })
            ->orderAsc($table->aliasField('id'))
            ->limit(500);
        $lastId = 0;
        while (true) {
            $q = clone $query;
            $q = $q->where(fn (QueryExpression $exp): QueryExpression => $exp->gt($table->aliasField('id'), $lastId));
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

    /**
     * Translate object translatable fields and store translation in translations table.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to translate
     * @param string $from The language to translate from
     * @param string $to The language to translate to
     * @return mixed
     */
    protected function translate($object, $from, $to)
    {
        $translatableFields = $this->translatableFields($object->type);
        if (empty($translatableFields)) {
            return;
        }
        $translatedFields = [];
        foreach ($translatableFields as $field) {
            if (empty($object->get($field))) {
                continue;
            }
            $translatedFields[$field] = $this->singleTranslation($object->get($field), $from, $to);
        }
        $translation = [
            'object_id' => $object->id,
            'lang' => array_flip($this->langsMap)[$to],
            'translated_fields' => json_encode($translatedFields),
            'status' => $this->defaultStatus,
        ];
        $table = $this->fetchTable('Translations');
        $entity = $table->newEntity($translation);
        $entity->set('translated_fields', json_decode($translation['translated_fields'], true));
        $entity->set('status', $this->defaultStatus);
        LoggedUser::setUserAdmin();
        $table->saveOrFail($entity);
    }

    /**
     * Get translatable fields by object type.
     *
     * @param string $type The object type
     * @return array
     */
    protected function translatableFields(string $type): array
    {
        if (array_key_exists($type, $this->translatableFields)) {
            return $this->translatableFields[$type];
        }
        /** @var \BEdita\Core\Model\Entity\ObjectType $objectType */
        $objectType = $this->fetchTable('ObjectTypes')->find()->where(['name' => $type])->firstOrFail();
        $schema = $objectType->get('schema');
        $this->translatableFields[$type] = (array)Hash::get($schema, 'translatable');

        return $this->translatableFields[$type];
    }

    /**
     * Translate a single text.
     *
     * @param mixed $text The text to translate
     * @param string $from The language to translate from
     * @param string $to The language to translate to
     * @return string
     */
    protected function singleTranslation($text, string $from, string $to): string
    {
        $response = $this->translator->translate([$text], $from, $to);
        $response = json_decode($response, true);

        return (string)Hash::get($response, 'translation.0');
    }
}
