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

use Cake\Core\InstanceConfigTrait;
use Generator;

/**
 * Trait for share Csv stuff.
 *
 * This provides `readCsv` method to progressively read a csv file line by line.
 *
 * Usage example:
 * ```php
 * use BEdita\ImportTools\Utility\CsvTrait;
 *
 * class MyImporter
 * {
 *     use CsvTrait;
 *
 *     public function import(string $filename): void
 *     {
 *         foreach ($this->readCsv($filename) as $obj) {
 *             // process $obj
 *         }
 *     }
 * }
 * ```
 */
trait CsvTrait
{
    use InstanceConfigTrait;
    use FileTrait;

    /**
     * Progressively read a CSV file, line by line
     *
     * @param string $path Path to CSV file
     * @param bool $assoc If `true` uses first CSV row as column names, thus yielding associative arrays. Otherwise, all rows are yielded and columns are indexed by their positions.
     * @return \Generator<array<array-key, string>>
     */
    protected function readCsv(string $path, bool $assoc = true): Generator
    {
        $delimiter = $this->getConfig('csv.delimiter', ',');
        $enclosure = $this->getConfig('csv.enclosure', '"');
        $escape = $this->getConfig('csv.escape', '\\');

        [$fh, $close] = static::readFileStream($path);

        try {
            flock($fh, LOCK_SH);

            $header = $assoc ? fgetcsv($fh, 0, $delimiter, $enclosure, $escape) : null;
            $i = 0;
            while (($row = fgetcsv($fh, 0, $delimiter, $enclosure, $escape)) !== false) {
                yield $i++ => $header !== null ? array_combine($header, $row) : $row;
            }
        } finally {
            $close();
        }
    }
}
