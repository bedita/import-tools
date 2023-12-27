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

use BEdita\Core\Model\Action\SetRelatedObjectsAction;
use BEdita\Core\Model\Entity\ObjectEntity;

/**
 * Trait for share Tree stuff.
 */
trait TreeTrait
{
    /**
     * Set parent folder
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $entity Entity
     * @param string $folder Folder uname or ID
     * @return void
     */
    protected function setParent(ObjectEntity $entity, string $folder): void
    {
        /** @var \BEdita\Core\Model\Table\FoldersTable $foldersTable */
        $foldersTable = $this->fetchTable('Folders');
        $parentId = $foldersTable->getId($folder);
        $parentEntity = $foldersTable->get($parentId);
        $association = $entity->getTable()->associations()->getByProperty('parents');
        $action = new SetRelatedObjectsAction(compact('association'));
        $relatedEntities = [$parentEntity];
        $action(compact('entity', 'relatedEntities'));
    }
}
