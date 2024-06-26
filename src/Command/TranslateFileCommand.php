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

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * TranslateFile command.
 *
 * $ bin/cake translate_file --help
 * $ bin/cake translate_file \
 *   -i input_file \
 *   -o output_file \
 *   -f source_language \
 *   -t dest_language \
 *   -e translator_engine
 *
 * Perform translation from a file using a translator engine.
 * The input file is translated from a source language to a destination language using a translator engine.
 * The output file is created with the translated content.
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
class TranslateFileCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addOption('input', [
            'short' => 'i',
            'help' => 'Input file path',
            'required' => true,
        ]);
        $parser->addOption('output', [
            'short' => 'o',
            'help' => 'Output file path',
            'required' => true,
        ]);
        $parser->addOption('from', [
            'short' => 'f',
            'help' => 'Source language',
            'required' => true,
        ]);
        $parser->addOption('to', [
            'short' => 't',
            'help' => 'Dest language',
            'required' => true,
        ]);
        $parser->addOption('translator', [
            'short' => 'e',
            'help' => 'Translator engine name',
            'required' => true,
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $i = (string)($args->getOption('input') ?? (string)$args->getOption('i'));
        if (!file_exists($i)) {
            $io->err(sprintf('Input file "%s" does not exist', $i));

            return self::CODE_ERROR;
        }
        $o = (string)($args->getOption('output') ?? $args->getOption('o'));
        $f = (string)($args->getOption('from') ?? $args->getOption('f'));
        $t = (string)($args->getOption('to') ?? $args->getOption('t'));
        $e = (string)($args->getOption('translator') ?? $args->getOption('e'));
        $io->out(sprintf('"%s" [%s] -> "%s" [%s] using "%s" engine.', $i, $f, $o, $t, $e));
        $cfg = (array)Configure::read(sprintf('Translators.%s', $e));
        if (empty($cfg)) {
            $io->err(sprintf('No translator engine "%s" is set in configuration', $e));

            return self::CODE_ERROR;
        }
        $class = $cfg['class'];
        $options = $cfg['options'];
        $translator = new $class();
        $translator->setup($options);
        $translation = $translator->translate([(string)file_get_contents($i)], $f, $t);
        $translation = json_decode($translation, true);
        $translation = $translation['translation'];
        file_put_contents($o, $translation);
        $io->out('Done. Bye!');

        return self::CODE_SUCCESS;
    }
}
