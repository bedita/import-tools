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

namespace BEdita\ImportTools\Utility;

use Cake\Core\InstanceConfigTrait;
use Generator;
use InvalidArgumentException;

/**
 * Trait for reading files.
 */
trait ReadTrait
{
    use InstanceConfigTrait;
    use FileTrait;
    use CsvTrait;
    use XmlTrait;

    /**
     * Read a CSV or XML file.
     *
     * @param string $sourceType Source type: 'csv' or 'xml'
     * @param string $path Path to file
     * @param bool $assoc If `true` uses first CSV row as column names, thus yielding associative arrays. Otherwise, all rows are yielded and columns are indexed by their positions.
     * @param string $element Element name for XML files
     * @return \Generator<array<array-key, string>>
     */
    protected function readItem(
        string $sourceType,
        string $path,
        bool $assoc = true,
        string $element = 'post',
    ): Generator {
        if (!in_array($sourceType, ['csv', 'xml'])) {
            throw new InvalidArgumentException(sprintf('Invalid source type "%s"', $sourceType));
        }
        $method = 'read' . ucfirst($sourceType);
        $params = $sourceType === 'csv' ? [$path, $assoc] : [$path, $element];
        yield from $this->$method(...$params);
    }
}
