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
use Cake\Http\Exception\NotFoundException;

/**
 * Trait for share Csv stuff.
 */
trait CsvTrait
{
    use InstanceConfigTrait;

    /**
     * Progressively read a CSV file, line by line
     *
     * @param string $path Path to CSV file
     * @return \Generator<array<string, string>>
     */
    public function readCsv($path): \Generator
    {
        try {
            $fh = fopen($path, 'rb');
        } catch (\Exception $e) {
            throw new NotFoundException(sprintf('File not found: %s', $path));
        }
        $options = $this->getConfig('csv');
        $delimiter = $options['delimiter'];
        $enclosure = $options['enclosure'];
        $escape = $options['escape'];
        flock($fh, LOCK_SH);
        $header = fgetcsv($fh, 0, $delimiter, $enclosure, $escape);
        $i = 0;
        while (($row = fgetcsv($fh, 0, $delimiter, $enclosure, $escape)) !== false) {
            yield array_combine($header, $row);
            $i++;
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
