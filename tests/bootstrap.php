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

/**
 * Test suite bootstrap for BEdita/ImportTools.
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */

use BEdita\Core\Filesystem\Adapter\LocalAdapter;
use BEdita\Core\Filesystem\FilesystemRegistry;
use BEdita\Core\ORM\Locator\TableLocator;
use BEdita\ImportTools\Test\TestApp\Application;
use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Cache\Engine\NullEngine;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Engine\ConsoleLog;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Utility\Security;
use Migrations\TestSuite\Migrator;

$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

require_once 'vendor/cakephp/cakephp/src/basics.php';
require_once 'vendor/autoload.php';

define('ROOT', $root . DS . 'tests' . DS);
define('APP', ROOT . 'TestApp' . DS);
define('TMP', sys_get_temp_dir() . DS);
define('LOGS', ROOT . DS . 'logs' . DS);
define('CONFIG', ROOT . DS . 'config' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CORE_PATH', $root . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'BEdita\ImportTools\Test\TestApp',
    'encoding' => 'UTF-8',
    'paths' => [
        'plugins' => [ROOT . 'Plugin' . DS],
        'templates' => [APP . 'Template' . DS],
    ],
]);

Log::setConfig([
    'debug' => [
        'engine' => ConsoleLog::class,
        'levels' => ['notice', 'info', 'debug'],
    ],
    'error' => [
        'engine' => ConsoleLog::class,
        'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
    ],
]);

Cache::drop('_bedita_object_types_');
Cache::drop('_bedita_core_');
Cache::setConfig([
    '_cake_core_' => ['engine' => ArrayEngine::class],
    '_cake_model_' => ['engine' => ArrayEngine::class],
    '_bedita_object_types_' => ['className' => NullEngine::class],
    '_bedita_core_' => ['className' => NullEngine::class],
]);

ConnectionManager::drop('test');
if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}
ConnectionManager::setConfig('test', ['url' => getenv('db_dsn')]);
ConnectionManager::alias('test', 'default');
ConnectionManager::setConfig('test-import', ['url' => getenv('db_dsn')]);
ConnectionManager::alias('test-import', 'import');

if (!TableRegistry::getTableLocator() instanceof TableLocator) {
    TableRegistry::setTableLocator(new TableLocator());
}

Security::setSalt('wIYveuyasdNTn3ikclAP6msatcNj76a6iuOG');

(new Migrator())->runMany([
    ['plugin' => 'BEdita/Core', 'connection' => 'test'],
    ['plugin' => 'BEdita/Core', 'connection' => 'test-import'],
]);

const TEST_FILES = __DIR__ . DS . 'files';
const WWW_ROOT = TEST_FILES; // Necessary to avoid warning when instantiating LocalAdapter.

FilesystemRegistry::setConfig('test-data', [
    'className' => LocalAdapter::class,
    'path' => TEST_FILES,
]);

$app = new Application(dirname(__DIR__) . '/config');
$app->bootstrap();
$app->pluginBootstrap();

Router::reload();
Router::fullBaseUrl('http://localhost');
Plugin::getCollection()->add(new \BEdita\ImportTools\Plugin(['middleware' => true]));

// clear all before running tests
TableRegistry::getTableLocator()->clear();
Cache::clearAll();
