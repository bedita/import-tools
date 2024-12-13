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

namespace BEdita\ImportTools\Utility;

use BEdita\Core\Model\Action\SaveEntityAction;
use BEdita\Core\Model\Action\SetRelatedObjectsAction;
use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Entity\Translation;
use BEdita\Core\Model\Table\ObjectsTable;
use BEdita\Core\Model\Table\TranslationsTable;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use DOMDocument;
use DOMXPath;

/**
 * Import utility
 *
 * This class provides functions to import data from csv files into BEdita.
 *
 * Public methods are:
 *
 * - `saveObjects`: read data from csv and save objects
 * - `saveObject`: save a single object
 * - `saveTranslations`: read data from csv and save translations
 * - `saveTranslation`: save a single translation
 * - `translatedFields`: get translated fields for a given object
 *
 * Usage example:
 * ```php
 * use BEdita\ImportTools\Utility\Import;
 *
 * class MyImporter
 * {
 *     public function import(string $filename, string $type, ?string $parent, ?bool $dryrun): void
 *     {
 *         $import = new Import($filename, $type, $parent, $dryrun);
 *         $import->saveObjects();
 *     }
 * }
 * ```
 */
class Import
{
    use LocatorAwareTrait;
    use LogTrait;
    use ReadTrait;
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
    public bool $dryrun = false;

    /**
     * Full filename path
     *
     * @var string|null
     */
    public ?string $filename = '';

    /**
     * Parent uname or ID
     *
     * @var string|null
     */
    public ?string $parent = '';

    /**
     * Number of processed entities
     *
     * @var int
     */
    public int $processed = 0;

    /**
     * Number of saved entities
     *
     * @var int
     */
    public int $saved = 0;

    /**
     * Number of errors
     *
     * @var int
     */
    public int $errors = 0;

    /**
     * Errors details
     *
     * @var array
     */
    public array $errorsDetails = [];

    /**
     * Number of skipped
     *
     * @var int
     */
    public int $skipped = 0;

    /**
     * Entity type
     *
     * @var string
     */
    public string $type = '';

    /**
     * Source type
     *
     * @var string
     */
    public string $sourceType = 'csv';

    /**
     * Source mapping
     *
     * @var array
     */
    public array $sourceMapping = [];

    /**
     * Objects table
     *
     * @var \BEdita\Core\Model\Table\ObjectsTable
     */
    protected ObjectsTable $objectsTable;

    /**
     * Type table
     *
     * @var \BEdita\Core\Model\Table\ObjectsTable
     */
    protected ObjectsTable $typeTable;

    /**
     * Translations table
     *
     * @var \BEdita\Core\Model\Table\TranslationsTable
     */
    protected TranslationsTable $translationsTable;

    /**
     * Assoc flag, for csv import
     *
     * @var bool
     */
    protected bool $assoc = true;

    /**
     * Element name, for xml import
     *
     * @var string
     */
    protected string $element = 'post';

    /**
     * Constructor
     *
     * @param string|null $filename Full filename path
     * @param string|null $type Entity type
     * @param string|null $parent Parent uname or ID
     * @param bool|null $dryrun Dry run mode flag
     * @param array|null $options Options
     * @return void
     */
    public function __construct(
        ?string $filename = null,
        ?string $type = 'objects',
        ?string $parent = null,
        ?bool $dryrun = false,
        ?array $options = ['mapping' => [], 'type' => 'csv', 'assoc' => true, 'element' => 'post']
    ) {
        $this->filename = $filename;
        $this->type = $type;
        $this->parent = $parent;
        $this->dryrun = $dryrun;
        $this->sourceMapping = Hash::get($options, 'mapping', []);
        $this->sourceType = Hash::get($options, 'type', 'csv');
        $this->assoc = Hash::get($options, 'assoc', true);
        $this->element = Hash::get($options, 'element', 'post');
        $this->processed = 0;
        $this->saved = 0;
        $this->errors = 0;
        $this->skipped = 0;
        $this->errorsDetails = [];
        /** @var \BEdita\Core\Model\Table\ObjectsTable $objectsTable */
        $objectsTable = $this->fetchTable('objects');
        $this->objectsTable = $objectsTable;
        /** @var \BEdita\Core\Model\Table\ObjectsTable $typesTable */
        $typesTable = $this->fetchTable($this->type);
        $this->typeTable = $typesTable instanceof ObjectsTable ? $typesTable : $objectsTable;
        /** @var \BEdita\Core\Model\Table\TranslationsTable $translationsTable */
        $translationsTable = $this->fetchTable('translations');
        $this->translationsTable = $translationsTable;
    }

    /**
     * Save media
     *
     * @param \Cake\ORM\Table $mediaTable Media table
     * @param array $mediaData Media data
     * @param array $streamData Stream data
     * @return \Cake\Datasource\EntityInterface|bool
     */
    public function saveMedia($mediaTable, array $mediaData, array $streamData): EntityInterface|bool
    {
        // create media
        $media = $mediaTable->newEntity($mediaData);
        if ($this->dryrun === true) {
            $this->skipped++;

            return $media;
        }
        // create media
        $action = new SaveEntityAction(['table' => $mediaTable]);
        $entity = $media;
        $data = $mediaData;
        $entity = $action(compact('entity', 'data'));
        $id = $entity->id;

        // create stream and attach it to the media
        $streamsTable = $this->fetchTable('Streams');
        $entity = $streamsTable->newEmptyEntity();
        $action = new SaveEntityAction(['table' => $streamsTable]);
        $data = $streamData;
        $entity->set('object_id', $id);

        return $action(compact('entity', 'data'));
    }

    /**
     * Save objects
     *
     * @return void
     */
    public function saveObjects(): void
    {
        foreach ($this->readItem($this->sourceType, $this->filename, $this->assoc, $this->element) as $obj) {
            try {
                $data = $this->transform($obj, $this->sourceMapping);
                $this->saveObject($data);
            } catch (\Exception $e) {
                $this->errorsDetails[] = $e->getMessage();
                $this->errors++;
            } finally {
                $this->processed++;
            }
        }
    }

    /**
     * Save object
     *
     * @param array $obj Object data
     * @return \BEdita\Core\Model\Entity\ObjectEntity
     */
    public function saveObject(array $obj): ObjectEntity
    {
        $entity = $this->typeTable->newEmptyEntity();
        if (!empty($obj['uname']) || !empty($obj['id'])) {
            $uname = (string)Hash::get($obj, 'uname');
            $identifier = empty($uname) ? 'id' : 'uname';
            $conditions = [$identifier => (string)Hash::get($obj, $identifier)];
            if ($this->objectsTable->exists($conditions)) {
                /** @var \BEdita\Core\Model\Entity\ObjectEntity $o */
                $o = $this->objectsTable->find()->where($conditions)->firstOrFail();
                if ($o->type !== $this->type) {
                    throw new BadRequestException(
                        sprintf(
                            'Object "%s" already present with another type "%s"',
                            $conditions[$identifier],
                            $o->type
                        )
                    );
                }
                $entity = $o->getTable()->find('type', [$this->type])->where($conditions)->firstOrFail();
            }
        }
        $entity = $this->typeTable->patchEntity($entity, $obj);
        $entity->set('type', $this->type);
        if ($this->dryrun === true) {
            $this->skipped++;

            return $entity;
        }
        $this->typeTable->saveOrFail($entity);
        if (!empty($this->parent)) {
            $this->setParent($entity, $this->parent);
        }
        $this->saved++;

        return $entity;
    }

    /**
     * Set related objects to an entity by relation
     *
     * @param string $relation Relation name
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Entity
     * @param array $relatedEntities Related entities
     * @return array|int|false
     */
    public function setRelated(string $relation, ObjectEntity $entity, array $relatedEntities): array|int|false
    {
        if (empty($relatedEntities)) {
            return false;
        }
        $association = $entity->getTable()->associations()->getByProperty($relation);
        $action = new SetRelatedObjectsAction(compact('association'));

        return $action(['entity' => $entity, 'relatedEntities' => $relatedEntities]);
    }

    /**
     * Save translations
     *
     * @return void
     */
    public function saveTranslations(): void
    {
        foreach ($this->readItem($this->sourceType, $this->filename, $this->assoc, $this->element) as $translation) {
            try {
                $this->saveTranslation($translation);
            } catch (\Exception $e) {
                $this->errorsDetails[] = $e->getMessage();
                $this->errors++;
            } finally {
                $this->processed++;
            }
        }
    }

    /**
     * Save translation
     *
     * @param array $data Translation data
     * @return \BEdita\Core\Model\Entity\Translation
     * @throws \Cake\Http\Exception\BadRequestException
     */
    public function saveTranslation(array $data): Translation
    {
        $uname = (string)Hash::get($data, 'object_uname');
        if (!$this->objectsTable->exists(compact('uname'))) {
            throw new BadRequestException(sprintf('Object "%s" not found', $uname));
        }
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $o */
        $o = $this->objectsTable->find()->where(compact('uname'))->firstOrFail();
        $objectId = $o->id;
        /** @var \BEdita\Core\Model\Entity\Translation $entity */
        $entity = $this->translationsTable->find()
            ->where([
                'object_id' => $objectId,
                'lang' => $data['lang'],
            ])
            ->first();
        $translation = [
            'object_id' => $objectId,
        ];
        if ($entity != null) {
            $entity = $this->translationsTable->patchEntity($entity, $translation);
        } else {
            $entity = $this->translationsTable->newEntity($translation);
        }
        $entity->set('translated_fields', $this->translatedFields($data));
        $entity->set('status', $this->getConfig('defaults')['status']);
        $entity->set('lang', $data['lang']);
        if ($this->dryrun === true) {
            $this->skipped++;

            return $entity;
        }
        $this->translationsTable->saveOrFail($entity);
        $this->saved++;

        return $entity;
    }

    /**
     * Transform data into BEdita object data
     *
     * @param array $obj The source data
     * @param array $mapping The mapping
     * @return array
     */
    public function transform(array $obj, array $mapping): array
    {
        if (empty($mapping)) {
            return $obj;
        }
        $data = [];
        foreach ($mapping as $key => $value) {
            if (!array_key_exists($key, $obj)) {
                continue;
            }
            $data = Hash::insert($data, $value, Hash::get($obj, $key));
        }

        return $data;
    }

    /**
     * Get translated fields
     *
     * @param array $source Source data
     * @return array
     */
    public function translatedFields(array $source): array
    {
        $fields = (string)Hash::get($source, 'translated_fields');
        if (!empty($fields)) {
            return json_decode($fields, true);
        }
        $fields = [];
        foreach ($source as $key => $value) {
            if (in_array($key, ['id', 'object_uname', 'lang'])) {
                continue;
            }
            $subkey = strpos($key, 'translation_') === 0 ? substr($key, 12) : $key;
            $fields[$subkey] = $value;
        }

        return $fields;
    }

    /**
     * Find object by key and identifier.
     *
     * @param \Cake\ORM\Table $table Table instance.
     * @param string $extraKey Extra key.
     * @param string $extraValue Extra value.
     * @return \Cake\ORM\Query|null
     * @codeCoverageIgnore as JSON_UNQUOTE and JSON_EXTRACT are not available for sqlite
     */
    public function findImported(Table $table, string $extraKey, string $extraValue): ?Query
    {
        return $table->find('available')->where(function (QueryExpression $exp) use ($table, $extraKey, $extraValue): QueryExpression {
            return $exp->and([
                $exp->isNotNull($table->aliasField('extra')),
                $exp->eq(
                    new FunctionExpression(
                        'JSON_UNQUOTE',
                        [
                            new FunctionExpression(
                                'JSON_EXTRACT',
                                ['extra' => 'identifier', sprintf('$.%s', $extraKey)]
                            ),
                        ]
                    ),
                    new FunctionExpression('JSON_UNQUOTE', [json_encode($extraValue)])
                ),
            ]);
        });
    }

    /**
     * Clean HTML from attributes, preserve some (using xpath expression)
     *
     * @param string $html HTML content
     * @param string $expression XPath expression
     * @return string
     */
    public function cleanHtml(string $html, string $expression = "//@*[local-name() != 'href' and local-name() != 'id' and local-name() != 'src']"): string
    {
        $dom = new DOMDocument();
        $metaUtf8 = '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
        $dom->loadHTML($metaUtf8 . $html, LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query($expression);
        foreach ($nodes as $node) {
            /** @var \DOMElement $element */
            $element = $node->parentNode;
            $element->removeAttribute($node->nodeName);
        }
        $body = $dom->documentElement->lastChild;
        $content = $dom->saveHTML($body);
        $content = preg_replace('/<\\/?body(\\s+.*?>|>)/', '', $content);

        return $content;
    }
}
