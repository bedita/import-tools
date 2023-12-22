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

use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Entity\Translation;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;

class Import
{
    use CsvTrait;
    use LocatorAwareTrait;
    use LogTrait;
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
     * Objects table
     *
     * @var \BEdita\Core\Model\Table\ObjectsTable
     */
    protected $objectsTable;

    /**
     * Type table
     *
     * @var \BEdita\Core\Model\Table\ObjectsTable
     */
    protected $typeTable;

    /**
     * Translations table
     *
     * @var \BEdita\Core\Model\Table\TranslationsTable
     */
    protected $translationsTable;

    /**
     * Constructor
     *
     * @param string|null $filename Full filename path
     * @param string|null $type Entity type
     * @param string|null $parent Parent uname or ID
     * @param bool|null $dryrun Dry run mode flag
     * @return void
     */
    public function __construct(
        ?string $filename = null,
        ?string $type = 'objects',
        ?string $parent = null,
        ?bool $dryrun = false
    ) {
        $this->filename = $filename;
        $this->type = $type;
        $this->parent = $parent;
        $this->dryrun = $dryrun;
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
        $this->typeTable = $typesTable;
        /** @var \BEdita\Core\Model\Table\TranslationsTable $translationsTable */
        $translationsTable = $this->fetchTable('translations');
        $this->translationsTable = $translationsTable;
    }

    /**
     * Save objects
     *
     * @return void
     */
    public function objects(): void
    {
        foreach ($this->readCsv($this->filename) as $obj) {
            try {
                $this->object($obj);
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
    public function object(array $obj): ObjectEntity
    {
        $entity = $this->typeTable->newEmptyEntity();
        if (!empty($obj['uname'])) {
            $uname = $obj['uname'];
            if ($this->objectsTable->exists(compact('uname'))) {
                /** @var \BEdita\Core\Model\Entity\ObjectEntity $o */
                $o = $this->objectsTable->find()->where(compact('uname'))->firstOrFail();
                if ($o->type !== $this->type) {
                    throw new BadRequestException(
                        sprintf('Object uname "%s" already present with another type "%s"', $uname, $o->type)
                    );
                }
                $entity = $this->typeTable->get($this->typeTable->getId($uname));
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
     * Save translations
     *
     * @return void
     */
    public function translations(): void
    {
        foreach ($this->readCsv($this->filename) as $translation) {
            try {
                $this->translation($translation);
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
    public function translation(array $data): Translation
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
        if (!empty($data['id'])) {
            $entity->set('id', $data['id']);
        }
        if ($this->dryrun === true) {
            $this->skipped++;

            return $entity;
        }
        $this->translationsTable->saveOrFail($entity);
        $this->saved++;

        return $entity;
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
            $fields = empty($fields) ? [] : json_decode($fields, true);

            return $fields;
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
}
