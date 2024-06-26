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

use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Utility\JsonSchema;
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
 *
 * $ bin/cake translate_objects --help
 * $ bin/cake translate_objects \
 *  --from source_language \
 *  --to dest_language \
 *  --engine translator_engine \
 * [--status translation_status] \
 * [--dry-run] \
 * [--limit number_of_objects] \
 * [--object-type object_type]
 *
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

    protected $_defaultConfig = [
        'langsMap' => [
            'en' => 'en-US',
            'it' => 'it',
            'de' => 'de',
            'es' => 'es',
            'fr' => 'fr',
            'pt' => 'pt-PT',
        ],
        'status' => 'draft',
        'dryRun' => false,
    ];
    protected $ok;
    protected $error;
    protected $io;
    protected $dryRun;
    protected $defaultStatus;
    protected $translatableFields = [];
    protected $translator;
    protected $langsMap;
    protected $limit;
    protected $type;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
        $cfg = (array)Configure::read('TranslateObjects');
        $cfg = array_merge($this->_defaultConfig, $cfg);
        $this->defaultStatus = (string)Hash::get($cfg, 'status');
        $this->setDryRun(Hash::get($cfg, 'dryRun') === 1);
        $this->langsMap = (array)Hash::get($cfg, 'langsMap');
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $langs = array_keys($this->langsMap);
        $parser = parent::buildOptionParser($parser);
        $parser->addOption('from', [
            'short' => 'f',
            'help' => 'Language to translate from',
            'required' => true,
            'choices' => $langs,
        ]);
        $parser->addOption('to', [
            'short' => 't',
            'help' => 'Language to translate to',
            'required' => true,
            'choices' => $langs,
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
        $parser->addOption('limit', [
            'short' => 'l',
            'help' => 'Limit number of objects to translate',
        ]);
        $parser->addOption('object-type', [
            'short' => 'o',
            'help' => 'Object type to translate',
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): void
    {
        $this->setDryRun($args->getOption('dry-run'));
        $this->setIo($io);

        // parameter engine
        $engine = $args->getOption('engine') ?? 'deepl';

        // setup translator engine
        $cfg = Configure::read(sprintf('Translators.%s', $engine));
        if (empty($cfg)) {
            $this->getIo()->abort(sprintf('Translator %s not found', $engine));
        }
        $this->setTranslator($cfg);

        // parameters: lang from, lang to
        $from = $args->getOption('from');
        $to = $args->getOption('to');
        $this->limit = $args->getOption('limit') ?? null;
        $this->type = $args->getOption('object-type') ?? null;
        $this->getIo()->out(
            sprintf(
                'Translating objects from %s to %s [dry-run %s / limit %s / %s]',
                $from,
                $to,
                $this->dryRun ? 'yes' : 'no',
                $this->limit ? sprintf('limit %s', $this->limit) : 'unlimited',
                $this->type ? sprintf('type %s', $this->type) : 'all types'
            )
        );
        $to = $this->langsMap[$to];
        if ($this->getIo()->ask('Do you want to continue [Y/n]?', 'n') !== 'Y') {
            $this->getIo()->abort('Bye');
        }
        $this->ok = $this->error = 0;
        $this->defaultStatus = $args->getOption('status');
        $this->processObjects($from, $to);
        $this->getIo()->out($this->results());
        $this->getIo()->out('Done');
    }

    /**
     * Set console io.
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    public function setIo(ConsoleIo $io): void
    {
        $this->io = $io;
    }

    /**
     * Get console io.
     *
     * @return \Cake\Console\ConsoleIo
     */
    public function getIo(): ConsoleIo
    {
        return $this->io;
    }

    /**
     * Set dry run.
     *
     * @param bool $dryRun The dry run flag
     * @return void
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Get dry run.
     *
     * @return bool
     */
    public function getDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Get results.
     *
     * @return string
     */
    public function results(): string
    {
        return sprintf('Processed %d objects (%d errors)', $this->ok + $this->error, $this->error);
    }

    /**
     * Set translator engine.
     *
     * @param array $cfg The translator configuration
     * @return void
     */
    public function setTranslator(array $cfg): void
    {
        $class = (string)Hash::get($cfg, 'class');
        $options = (array)Hash::get($cfg, 'options');
        $this->translator = new $class();
        $this->translator->setup($options);
    }

    /**
     * Process objects to translate.
     *
     * @param string $from The language to translate from
     * @param string $to The language to translate to
     * @return void
     */
    public function processObjects(string $from, string $to): void
    {
        $conditions = [];
        foreach ($this->objectsIterator($conditions, $from, $to) as $object) {
            if ($this->limit !== null && ($this->ok + $this->error >= $this->limit)) {
                break;
            }
            $this->processObject($object, $from, $to);
        }
    }

    /**
     * Process single object.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object to translate
     * @param string $from The language to translate from
     * @param string $to The language to translate to
     * @return void
     */
    public function processObject(ObjectEntity $object, string $from, string $to): void
    {
        try {
            $this->getIo()->verbose(sprintf('Translating object %s', $object->id));
            if (!$this->dryRun) {
                $this->translate($object, $from, $to);
            }
            $this->getIo()->verbose(sprintf('Translated object %s', $object->id));
            $this->ok++;
        } catch (\Exception $e) {
            $this->getIo()->error(sprintf('Error translating object %s: %s', $object->id, $e->getMessage()));
            $this->error++;
        }
    }

    /**
     * Get objects as iterable.
     *
     * @param array $conditions The conditions to filter objects.
     * @param string $lang The language to use to find objects.
     * @param string $to The language to translate objects to.
     * @return iterable
     */
    public function objectsIterator(array $conditions, string $lang, string $to): iterable
    {
        /** @var \BEdita\Core\Model\Table\ObjectsTable $table */
        $table = $this->fetchTable('objects');
        if ($this->type !== null) {
            $conditions[$table->aliasField('object_type_id')] = $table->objectType($this->type)->id;
        }
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
     * @return void
     */
    public function translate($object, $from, $to): void
    {
        $id = $object->id;
        $type = $object->type;
        $object = $this->fetchTable($type)->find()->where(compact('id'))->firstOrFail();
        $translatableFields = $this->translatableFields($type);
        if (empty($translatableFields)) {
            return;
        }
        $translatedFields = [];
        $fields = $values = $jsonFields = $jsonValues = [];
        foreach ($translatableFields as $field) {
            if (empty($object->get($field))) {
                continue;
            }
            $val = $object->get($field);
            if (is_array($val)) {
                $jsonFields[] = $field;
                $jsonValues[] = json_encode($val);
            } else {
                $fields[] = $field;
                $values[] = $val;
            }
        }
        if (empty($fields) && empty($jsonFields)) {
            return;
        }
        $tr = $this->multiTranslation($values, $from, $to);
        foreach ($tr as $i => $t) {
            $translatedFields[$fields[$i]] = $t;
        }
        $tr = $this->multiTranslation($jsonValues, $from, $to);
        foreach ($tr as $i => $t) {
            $translatedFields[$jsonFields[$i]] = json_decode($t, true);
        }
        $translation = [
            'object_id' => $id,
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
    public function translatableFields(string $type): array
    {
        if (array_key_exists($type, $this->translatableFields)) {
            return $this->translatableFields[$type];
        }
        $schema = JsonSchema::typeSchema($type);
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
    public function singleTranslation($text, string $from, string $to): string
    {
        $response = $this->translator->translate([$text], $from, $to);
        $response = json_decode($response, true);

        return (string)Hash::get($response, 'translation.0');
    }

    /**
     * Translate multiple texts.
     *
     * @param array $texts The texts to translate
     * @param string $from The language to translate from
     * @param string $to The language to translate to
     * @return array
     */
    public function multiTranslation($texts, string $from, string $to): array
    {
        $response = $this->translator->translate($texts, $from, $to);
        $response = json_decode($response, true);

        return (array)Hash::get($response, 'translation');
    }
}
