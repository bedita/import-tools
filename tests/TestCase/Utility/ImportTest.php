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
namespace BEdita\ImportTools\Test\TestCase\Utility;

use BEdita\Core\Filesystem\Adapter\LocalAdapter;
use BEdita\Core\Filesystem\FilesystemRegistry;
use BEdita\Core\Utility\LoggedUser;
use BEdita\ImportTools\Utility\Import;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use League\Flysystem\StorageAttributes;

/**
 * {@see \BEdita\ImportTools\Utility\Import} Test Case
 *
 * @covers \BEdita\ImportTools\Utility\Import
 */
class ImportTest extends TestCase
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
        'plugin.BEdita/Core.Relations',
        'plugin.BEdita/Core.RelationTypes',
        'plugin.BEdita/Core.PropertyTypes',
        'plugin.BEdita/Core.Properties',
        'plugin.BEdita/Core.Config',
        'plugin.BEdita/Core.Trees',
        'plugin.BEdita/Core.ObjectRelations',
    ];

    public function setUp(): void
    {
        parent::setUp();
        LoggedUser::setUserAdmin();
    }

    /**
     * Test constructor
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $filename = 'articles1.csv';
        $type = 'documents';
        $parent = null;
        $dryrun = true;
        $import = new Import($filename, $type, $parent, $dryrun);
        static::assertEquals($filename, $import->filename);
        static::assertEquals($type, $import->type);
        static::assertEquals($parent, $import->parent);
        static::assertEquals($dryrun, $import->dryrun);
        static::assertEquals(0, $import->processed);
        static::assertEquals(0, $import->saved);
        static::assertEquals(0, $import->errors);
        static::assertEquals(0, $import->skipped);
    }

    /**
     * Data provider for save objects test case.
     *
     * @return array
     */
    public function saveObjectsProvider(): array
    {
        return [
            'wrong type' => [
                TEST_FILES . DS . 'articles1.csv',
                'documentzzzz',
                '',
                false,
                [
                    'filename' => TEST_FILES . DS . 'articles1.csv',
                    'type' => 'documentzzzz',
                    'parent' => '',
                    'dryrun' => false,
                    'processed' => 3,
                    'saved' => 0,
                    'errors' => 3,
                    'skipped' => 0,
                ],
            ],
            'process objects with dry run true' => [
                TEST_FILES . DS . 'articles1.csv',
                'documents',
                '',
                true,
                [
                    'filename' => TEST_FILES . DS . 'articles1.csv',
                    'type' => 'documents',
                    'parent' => '',
                    'dryrun' => true,
                    'processed' => 3,
                    'saved' => 0,
                    'errors' => 0,
                    'skipped' => 3,
                ],
            ],
            'process objects with dry run false' => [
                TEST_FILES . DS . 'articles1.csv',
                'documents',
                '',
                false,
                [
                    'filename' => TEST_FILES . DS . 'articles1.csv',
                    'type' => 'documents',
                    'parent' => '',
                    'dryrun' => false,
                    'processed' => 3,
                    'saved' => 3,
                    'errors' => 0,
                    'skipped' => 0,
                ],
            ],
        ];
    }

    /**
     * Test `saveObjects` method
     *
     * @param string $filename Filename
     * @param string $type Type
     * @param string $parent Parent
     * @param bool $dryrun Dry run
     * @param array $expected Expected
     * @return void
     * @dataProvider saveObjectsProvider
     */
    public function testSaveObjects(string $filename, string $type, string $parent, bool $dryrun, array $expected): void
    {
        $import = new Import($filename, $type, $parent, $dryrun);
        $import->saveObjects();
        foreach ($expected as $key => $value) {
            static::assertEquals($value, $import->$key);
        }
    }

    /**
     * Data provider for save object test case.
     *
     * @return array
     */
    public function saveObjectProvider(): array
    {
        $data = [
            'title' => 'test title',
            'description' => 'test description',
            'body' => 'test body',
            'status' => 'on',
            'uname' => 'test-uname',
            'lang' => 'en',
        ];

        return [
            'process object with dry run true' => [
                TEST_FILES . DS . 'articles1.csv',
                'documents',
                '',
                true,
                $data,
                $data,
            ],
            'process object with dry run false' => [
                TEST_FILES . DS . 'articles1.csv',
                'documents',
                '',
                false,
                $data,
                $data,
            ],
            'process object with parent' => [
                TEST_FILES . DS . 'articles1.csv',
                'documents',
                'parent-uname',
                false,
                $data,
                $data,
            ],
        ];
    }

    /**
     * Test `saveObject` method
     *
     * @param string $f The Filename
     * @param string $t The Type
     * @param string $p The Parent uname
     * @param bool $dr Dry run
     * @param array $data Data
     * @param array $expected Expected
     * @return void
     * @dataProvider saveObjectProvider
     */
    public function testSaveObject(string $f, string $t, string $p, bool $dr, array $data, array $expected): void
    {
        if ($p !== '') {
            /** @var \BEdita\Core\Model\Table\FoldersTable $foldersTable */
            $foldersTable = $this->fetchTable('folders');
            if (!$foldersTable->exists(['uname' => $p])) {
                /** @var \BEdita\Core\Model\Entity\ObjectEntity $folder */
                $folder = $foldersTable->newEntity(['uname' => $p, 'status' => 'on']);
                $folder->type = 'folders';
                $folder = $foldersTable->save($folder);
                $p = $folder->uname;
            }
        }
        $import = new Import($f, $t, $p, $dr);
        $actual = $import->saveObject($data);
        foreach ($expected as $key => $value) {
            static::assertEquals($value, $actual->$key);
        }
        if ($dr === true) {
            static::assertEquals(1, $import->skipped);
        } else {
            static::assertEquals(1, $import->saved);
        }
    }

    /**
     * Test `saveObject` method when exists
     *
     * @return void
     * @dataProvider saveObjectProvider
     */
    public function testSaveObjectWhenExists(): void
    {
        $import = new Import(TEST_FILES . DS . 'articles1.csv', 'documents', '', false);
        $data = [
            'title' => 'test title',
            'description' => 'test description',
            'body' => 'test body',
            'status' => 'on',
            'uname' => 'test-uname',
            'lang' => 'en',
        ];
        /** @var \BEdita\Core\Model\Table\ObjectsTable $objectsTable */
        $objectsTable = $this->fetchTable('objects');
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $doc */
        $doc = $objectsTable->newEntity($data);
        $doc->type = 'documents';
        $objectsTable->save($doc);
        $actual = $import->saveObject($data);
        foreach ($data as $key => $value) {
            static::assertEquals($value, $actual->$key);
        }
        static::assertEquals(1, $import->saved);
    }

    /**
     * Test `saveObject` method when exists
     *
     * @return void
     * @dataProvider saveObjectProvider
     */
    public function testSaveObjectWhenExistsWrongType(): void
    {
        $exception = new BadRequestException(
            'Object "test-uname" already present with another type "events"'
        );
        $this->expectExceptionObject($exception);
        $import = new Import(TEST_FILES . DS . 'articles1.csv', 'documents', '', false);
        $data = [
            'title' => 'test title',
            'description' => 'test description',
            'body' => 'test body',
            'status' => 'on',
            'uname' => 'test-uname',
            'lang' => 'en',
        ];
        /** @var \BEdita\Core\Model\Table\ObjectsTable $objectsTable */
        $objectsTable = $this->fetchTable('objects');
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $doc */
        $doc = $objectsTable->newEntity($data);
        $doc->type = 'events';
        $objectsTable->save($doc);
        /** @var \BEdita\ImportTools\Utility\Import $actual */
        $actual = $import->saveObject($data);
        static::assertEquals(0, $actual->saved);
        static::assertEquals(0, $actual->errors);
    }

    /**
     * Test `saveMedia` method
     *
     * @return void
     */
    public function testSaveMedia(): void
    {
        $directory = 'default://';
        FilesystemRegistry::dropAll();
        FilesystemRegistry::setConfig('default', [
            'className' => LocalAdapter::class,
            'path' => TEST_FILES,
        ]);
        $mountManager = FilesystemRegistry::getMountManager();
        $recursive = false;
        $default = collection($mountManager->listContents($directory, $recursive)->toArray())
            ->reject(function (StorageAttributes $object) {
                return $object->isDir();
            })
            ->map(function (StorageAttributes $object) use ($mountManager) {
                $path = $object->path();
                $contents = fopen('php://memory', 'wb+');
                fwrite($contents, $mountManager->read($path));
                fseek($contents, 0);

                return compact('contents', 'path');
            })
            ->compile();
        $imagename = 'gustavo-supporto.jpg';
        $filepath = TEST_FILES . DS . $imagename;
        $import = new Import();
        $id = LoggedUser::id();
        $imageData = [
            'title' => $imagename,
            'status' => 'on',
            'media_property' => false,
            'created_by' => $id,
            'modified_by' => $id,
        ];
        $streamData = [
            'file_name' => $imagename,
            'mime_type' => mime_content_type($filepath),
            'contents' => file_get_contents($filepath),
        ];
        /** @var \BEdita\Core\Model\Table\MediaTable $mediaTable */
        $mediaTable = $this->fetchTable('Images');
        /** @var \BEdita\Core\Model\Entity\Stream $stream */
        $stream = $import->saveMedia(
            $mediaTable,
            $imageData,
            $streamData
        );
        static::assertEquals($imagename, $stream->file_name);
        static::assertEquals('image/jpeg', $stream->mime_type);
    }

    /**
     * Test `saveMedia` method with dryrun true
     *
     * @return void
     */
    public function testSaveMediaDryRun(): void
    {
        $imagename = 'gustavo-supporto.jpg';
        $filepath = TEST_FILES . DS . $imagename;
        $import = new Import(null, 'images', null, true);
        $imageData = [
            'title' => $imagename,
            'status' => 'on',
        ];
        $streamData = [
            'file_name' => $imagename,
            'mime_type' => mime_content_type($filepath),
            'contents' => file_get_contents($filepath),
        ];
        /** @var \BEdita\Core\Model\Table\MediaTable $mediaTable */
        $mediaTable = $this->fetchTable('Images');
        /** @var \BEdita\Core\Model\Entity\Media $media */
        $media = $import->saveMedia(
            $mediaTable,
            $imageData,
            $streamData
        );
        static::assertEquals($imagename, $media->title);
        static::assertEquals('on', $media->status);
    }

    /**
     * Test `setRelated` method
     *
     * @return void
     */
    public function testSetRelated(): void
    {
        /** @var \BEdita\Core\Model\Table\ObjectsTable $objectsTable */
        $objectsTable = $this->fetchTable('Objects');
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $entity */
        $entity = $objectsTable->newEntity(['title' => 'test one', 'status' => 'on']);
        $entity->type = 'documents';
        $entity = $objectsTable->save($entity);

        /** @var \BEdita\Core\Model\Entity\ObjectEntity $related */
        $related = $objectsTable->newEntity(['title' => 'test two', 'status' => 'on']);
        $related->type = 'documents';
        $related = $objectsTable->save($related);

        $import = new Import();
        $actual = $import->setRelated('test', $entity, [$related]);
        $actual = $objectsTable->get($actual[0]);
        static::assertEquals('test two', $actual->title);
        static::assertEquals('on', $actual->status);
    }

    /**
     * Data provider for save translations with error test case.
     *
     * @return array
     */
    public function saveTranslationsWithErrorProvider(): array
    {
        $filename = TEST_FILES . DS . 'translations1.csv';
        $type = 'translations';
        $parent = '';

        return [
            'process translations with dry run true with 404 errors' => [
                $filename,
                $type,
                $parent,
                true,
                [
                    'filename' => $filename,
                    'type' => $type,
                    'parent' => $parent,
                    'dryrun' => true,
                    'processed' => 3,
                    'saved' => 0,
                    'errors' => 3,
                    'skipped' => 0,
                    'errorsDetails' => [
                        'Object "dummy-article-1" not found',
                        'Object "dummy-article-2" not found',
                        'Object "dummy-article-3" not found',
                    ],
                ],
            ],
            'process translations with dry run false with 404 errors' => [
                $filename,
                $type,
                $parent,
                false,
                [
                    'filename' => $filename,
                    'type' => $type,
                    'parent' => $parent,
                    'dryrun' => false,
                    'processed' => 3,
                    'saved' => 0,
                    'errors' => 3,
                    'skipped' => 0,
                    'errorsDetails' => [
                        'Object "dummy-article-1" not found',
                        'Object "dummy-article-2" not found',
                        'Object "dummy-article-3" not found',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test `saveTranslations` method on error
     *
     * @param string $f Filename
     * @param string $t Type
     * @param string $p Parent
     * @param bool $dr Dry run
     * @param array $exp Expected
     * @return void
     * @dataProvider saveTranslationsWithErrorProvider
     */
    public function testSaveTranslationsWithError(string $f, string $t, string $p, bool $dr, array $exp): void
    {
        $import = new Import($f, $t, $p, $dr);
        $import->saveTranslations();
        foreach ($exp as $key => $value) {
            static::assertEquals($value, $import->$key);
        }
    }

    /**
     * Data provider for save translations test case.
     *
     * @return array
     */
    public function saveTranslationsProvider(): array
    {
        return [
            'import articles and translations with dry run true' => [
                TEST_FILES . DS . 'articles1.csv',
                TEST_FILES . DS . 'translations1.csv',
                true,
                [
                    'filename' => TEST_FILES . DS . 'translations1.csv',
                    'type' => 'translations',
                    'parent' => '',
                    'dryrun' => true,
                    'processed' => 3,
                    'saved' => 0,
                    'errors' => 0,
                    'skipped' => 3,
                    'errorsDetails' => [],
                ],
            ],
            'import articles and translations with dry run false' => [
                TEST_FILES . DS . 'articles2.csv',
                TEST_FILES . DS . 'translations2.csv',
                false,
                [
                    'filename' => TEST_FILES . DS . 'translations2.csv',
                    'type' => 'translations',
                    'parent' => '',
                    'dryrun' => false,
                    'processed' => 3,
                    'saved' => 3,
                    'errors' => 0,
                    'skipped' => 0,
                    'errorsDetails' => [],
                ],
            ],
        ];
    }

    /**
     * Test `saveTranslations` method
     *
     * @param string $arf Articles filename
     * @param string $trf Translations filename
     * @param bool $trdr Translations dry run
     * @param array $exp Expected
     * @return void
     * @dataProvider saveTranslationsProvider
     */
    public function testSaveTranslations(string $arf, string $trf, bool $trdr, array $exp): void
    {
        $import = new Import($arf, 'documents', '', false);
        $import->saveObjects();
        $import = new Import($trf, 'translations', '', $trdr);
        $import->saveTranslations();
        foreach ($exp as $key => $value) {
            static::assertEquals($value, $import->$key);
        }
    }

    /**
     * Data provider for save translation test case.
     *
     * @return array
     */
    public function saveTranslationProvider(): array
    {
        $data = [
            'translation_title' => 'titolo test',
            'translation_description' => 'descrizione test',
            'translation_body' => 'body test',
            'object_uname' => 'some-new-article',
            'lang' => 'it',
        ];

        return [
            'process translation with dry run true' => [
                TEST_FILES . DS . 'translations1.csv',
                true,
                $data,
                $data,
            ],
            'process translation with dry run false' => [
                TEST_FILES . DS . 'translations1.csv',
                false,
                $data,
                $data,
            ],
        ];
    }

    /**
     * Test `saveTranslation` method
     *
     * @return void
     * @dataProvider saveTranslationProvider
     */
    public function testSaveTranslation(string $f, bool $dr, array $data, array $expected): void
    {
        $uname = (string)Hash::get($data, 'object_uname');
        $objectsTable = $this->fetchTable('objects');
        if (!$objectsTable->exists(compact('uname'))) {
            /** @var \BEdita\Core\Model\Entity\ObjectEntity $doc */
            $doc = $objectsTable->newEntity(['uname' => $uname, 'status' => 'on']);
            $doc->type = 'documents';
            $objectsTable->save($doc);
        }
        $import = new Import($f, 'translations', '', $dr);
        $actual = $import->saveTranslation($data);
        foreach ($expected as $key => $value) {
            if (in_array($key, ['id', 'object_uname', 'lang'])) {
                continue;
            }
            $subkey = strpos($key, 'translation_') === 0 ? substr($key, 12) : $key;
            static::assertEquals($value, $actual->translated_fields[$subkey]);
        }
        if ($dr === true) {
            static::assertEquals(1, $import->skipped);
        } else {
            static::assertEquals(1, $import->saved);
        }
    }

    public function translatedFieldsProvider(): array
    {
        return [
            'translated fields by prefix translation_' => [
                [
                    'translation_title' => 'titolo test',
                    'translation_description' => 'descrizione test',
                    'translation_body' => 'body test',
                    'object_uname' => 'some-new-article',
                    'lang' => 'it',
                ],
                [
                    'title' => 'titolo test',
                    'description' => 'descrizione test',
                    'body' => 'body test',
                ],
            ],
            'translated fields by translated_fields' => [
                [
                    'translated_fields' => '{"title":"titolo test","description":"descrizione test","body":"body test"}',
                    'object_uname' => 'some-new-article',
                    'lang' => 'it',
                    'id' => 1,
                ],
                [
                    'title' => 'titolo test',
                    'description' => 'descrizione test',
                    'body' => 'body test',
                ],
            ],
        ];
    }

    /**
     * Test `translatedFields` method
     *
     * @return void
     * @dataProvider translatedFieldsProvider
     */
    public function testTranslatedFields(array $data, array $expected): void
    {
        $import = new Import();
        $actual = $import->translatedFields($data);
        static::assertEquals($expected, $actual);
    }

    /**
     * Test `cleanHtml` method
     *
     * @return void
     */
    public function testCleanHtml(): void
    {
        $import = new Import();
        $html = '<div class="some-class">test <a href="some-url">me</a> <img src="an-image"></div>';
        $expected = '<div>test <a href="some-url">me</a> <img src="an-image"></div>';
        $actual = $import->cleanHtml($html);
        static::assertEquals($expected, $actual);
    }

    /**
     * Test `transform` method
     *
     * @return void
     */
    public function testTransform(): void
    {
        $import = new Import();
        $mapping = [
            't' => 'title',
            'd' => 'description',
            'b' => 'body',
            's' => 'status',
            'u' => 'uname',
            'l' => 'lang',
            'e' => 'extra',
        ];
        $data = [
            't' => 'test title',
            'd' => 'test description',
            'b' => 'test body',
            's' => 'on',
            'u' => 'test-uname',
            'l' => 'en',
        ];
        $expected = [
            'title' => 'test title',
            'description' => 'test description',
            'body' => 'test body',
            'status' => 'on',
            'uname' => 'test-uname',
            'lang' => 'en',
        ];
        $actual = $import->transform($data, $mapping);
        static::assertEquals($expected, $actual);
    }
}
