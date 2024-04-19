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

namespace BEdita\ImportTools\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\ImportTools\Command\TranslateObjectsCommand} Test Case
 *
 * @coversDefaultClass \BEdita\ImportTools\Command\TranslateObjectsCommand
 */
class TranslateObjectsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    /**
     * Data provider for `testExecute` test case.
     *
     * @return array
     */
    public function executeProvider(): array
    {
        $conf = [
            'TranslateObjects' => [
                'langsMap' => [
                    'en' => 'en-US',
                    'it' => 'it-IT',
                    'de' => 'de-DE',
                ],
                'status' => 'draft',
                'dryRun' => false,
            ],
        ];

        return [
            'missing from' => [
                'translate_objects',
                ['Missing required option. The `from` option is required and has no default value'],
                1,
                $conf,
                [],
            ],
            'wrong from' => [
                'translate_objects --from ita',
                ['"ita" is not a valid value for --from. Please use one of "en, it, de"'],
                1,
                $conf,
                [],
            ],
            'missing to' => [
                'translate_objects --from en',
                ['Missing required option. The `to` option is required and has no default value'],
                1,
                $conf,
                [],
            ],
            'wrong to' => [
                'translate_objects --from en --to ita',
                ['"ita" is not a valid value for --to. Please use one of "en, it, de"'],
                1,
                $conf,
                [],
            ],
            'missing translator engine setup' => [
                'translate_objects --from en --to it',
                ['Translator deepl not found'],
                1,
                $conf,
                [],
            ],
            'continue? n' => [
                'translate_objects --from en --to it',
                ['Bye'],
                1,
                $conf + [
                    'Translators.deepl' => [
                        'class' => 'BEdita\ImportTools\Test\TestCase\Core\I18n\DummyTranslator',
                        'options' => ['auth_key' => 'secret'],
                    ],
                ],
                ['n'],
            ],
            'continue? Y + dry-run yes' => [
                'translate_objects --from en --to it --dry-run 1',
                [
                    'Translating objects from en to it [dry-run yes]',
                    'Processed 0 objects (0 errors)',
                    'Done',
                ],
                0,
                $conf + [
                    'Translators.deepl' => [
                        'class' => 'BEdita\ImportTools\Test\TestCase\Core\I18n\DummyTranslator',
                        'options' => ['auth_key' => 'secret'],
                    ],
                ],
                ['Y'],
            ],
        ];
    }

    /**
     * Test `execute` with code error on no file
     *
     * @param string $cmd Command to execute
     * @param array $expected Expected messages in output
     * @param int $error Expected exit code
     * @param array $config Configuration to set
     * @param array $input Input to provide
     * @return void
     * @dataProvider executeProvider
     * @covers ::buildOptionParser()
     * @covers ::execute()
     * @covers ::__construct()
     */
    public function testExecute(string $cmd, array $expected, int $error, array $config, array $input): void
    {
        foreach ($config as $key => $value) {
            Configure::write($key, $value);
        }
        $this->exec($cmd, $input);
        if ($error === 0) {
            $this->assertExitSuccess();
            foreach ($expected as $exp) {
                $this->assertOutputContains($exp);
            }
        } else {
            $this->assertExitError();
            foreach ($expected as $exp) {
                $this->assertErrorContains($exp);
            }
        }
    }

    /**
     * Test `processObjects` method
     *
     * @return void
     * @covers ::processObjects()
     */
    public function testProcessObjects(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `objectsIterator` method
     *
     * @return void
     * @covers ::objectsIterator()
     */
    public function testObjectsIterator(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `translate` method
     *
     * @return void
     * @covers ::translate()
     */
    public function testTranslate(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `translatableFields` method
     *
     * @return void
     * @covers ::translatableFields()
     */
    public function testTranslatableFields(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test `singleTranslation` method
     *
     * @return void
     * @covers ::singleTranslation()
     */
    public function testSingleTranslation(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
