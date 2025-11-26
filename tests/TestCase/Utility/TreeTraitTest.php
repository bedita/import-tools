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

use BEdita\Core\Model\Enum\ObjectEntityStatus;
use BEdita\Core\Utility\LoggedUser;
use BEdita\ImportTools\Utility\TreeTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * {@see \BEdita\ImportTools\Utility\TreeTrait} Test Case
 */
#[CoversClass(TreeTrait::class)]
class TreeTraitTest extends TestCase
{
    use LocatorAwareTrait;
    use TreeTrait;

    /**
     * @inheritDoc
     */
    public array $fixtures = [
        'plugin.BEdita/Core.ObjectTypes',
        'plugin.BEdita/Core.PropertyTypes',
        'plugin.BEdita/Core.Properties',
        'plugin.BEdita/Core.Objects',
        'plugin.BEdita/Core.Trees',
        'plugin.BEdita/Core.Users',
    ];

    /**
     * ObjectTypesTable instance
     *
     * @var \BEdita\Core\Model\Table\ObjectTypesTable
     */
    protected $ObjectTypes = null;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        /** @var \BEdita\Core\Model\Table\ObjectTypesTable $objectTypes */
        $objectTypes = $this->fetchTable('ObjectTypes');
        $this->ObjectTypes = $objectTypes;

        LoggedUser::setUserAdmin();
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        $this->getTableLocator()->clear();

        parent::tearDown();
    }

    /**
     * Test `setParent` method
     *
     * @return void
     */
    public function testSetParent(): void
    {
        $uname = 'import-tools-test-folder';
        /** @var \BEdita\Core\Model\Table\FoldersTable $foldersTable */
        $foldersTable = $this->fetchTable('Folders');
        /** @var \BEdita\Core\Model\Entity\Folder $parent */
        $parent = $foldersTable->newEntity(['uname' => $uname, 'status' => ObjectEntityStatus::On]);
        $parent = $foldersTable->save($parent);
        /** @var \BEdita\Core\Model\Entity\Folder $parent */
        $parent = $foldersTable->find()->where(['uname' => $uname])->contain('Children')->first();
        $childrenCount = count($parent->children);

        /** @var \BEdita\Core\Model\Table\ObjectsTable $objectsTable */
        $objectsTable = $this->fetchTable('Objects');
        /** @var \BEdita\Core\Model\Entity\ObjectEntity $child */
        $child = $objectsTable->newEntity(['title' => 'test child', 'status' => ObjectEntityStatus::On]);
        $child->type = 'documents';
        $child = $objectsTable->save($child);

        // set parent
        $this->setParent($child, $uname);

        // verify that folder is parent for child
        /** @var \BEdita\Core\Model\Entity\Folder $parent */
        $parent = $foldersTable->find()->where(['uname' => $uname])->contain('Children')->first();
        $this->assertSame($childrenCount + 1, count($parent->children));
        $this->assertSame($child->id, $parent->children[0]->id);
    }
}
