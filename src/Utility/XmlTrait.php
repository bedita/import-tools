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
use XMLReader;

/**
 * Trait for share Xml stuff.
 *
 * This provides `readXml` method to progressively read a xml file element by element.
 *
 * Usage example:
 * ```php
 * use BEdita\ImportTools\Utility\XmlTrait;
 *
 * class MyImporter
 * {
 *     use XmlTrait;
 *
 *     public function import(string $filename): void
 *     {
 *         foreach ($this->readXml($filename, 'post') as $obj) {
 *             // process $obj
 *         }
 *     }
 * }
 * ```
 */
trait XmlTrait
{
    use InstanceConfigTrait;
    use FileTrait;

    /**
     * Progressively read a Xml file
     *
     * @param string $path Path to Xml file
     * @param string $element Element name for XML files
     * @return \Generator<array<array-key, string>>
     */
    protected function readXml(string $path, string $element): \Generator
    {
        try {
            $reader = new XMLReader();
            $reader->open($path);
            $process = true;
            while ($process) {
                while (!($reader->nodeType == XMLReader::ELEMENT && $reader->name == $element)) {
                    if (!$reader->read()) {
                        $process = false;
                        break;
                    }
                }
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == $element) {
                    $xml = simplexml_load_string($reader->readOuterXml(), null, LIBXML_NOCDATA);
                    $json = json_encode($xml);
                    yield json_decode($json, true);
                    $reader->next();
                }
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Cannot open file: %s', $path), 0, $e);
        }
    }
}
