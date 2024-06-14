# BEdita/ImportTools plugin for CakePHP apps using BEdita

[![Github Actions](https://github.com/bedita/import-tools/workflows/php/badge.svg)](https://github.com/bedita/import-tools/actions?query=workflow%3Aphp)
[![codecov](https://codecov.io/gh/bedita/import-tools/branch/main/graph/badge.svg)](https://codecov.io/gh/bedita/import-tools)
[![phpstan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg)](https://phpstan.org)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bedita/import-tools/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/bedita/import-tools/?branch=main)
[![image](https://img.shields.io/packagist/v/bedita/import-tools.svg?label=stable)](https://packagist.org/packages/bedita/import-tools)
[![image](https://img.shields.io/github/license/bedita/import-tools.svg)](https://github.com/bedita/import-tools/blob/main/LICENSE.LGPL)

## Installation

First, if `vendor` directory has not been created, you have to install composer dependencies using:

```bash
composer install
```

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```bash
composer require bedita/import-tools
```

## Commands

### ImportCommand

This command provides a tool to import data from csv file.

Usage examples:
```bash
# basic
$ bin/cake import --file documents.csv --type documents
$ bin/cake import -f documents.csv -t documents

# dry-run
$ bin/cake import --file articles.csv --type articles --dryrun yes
$ bin/cake import -f articles.csv -t articles -d yes

# destination folder
$ bin/cake import --file news.csv --type news --parent my-folder-uname
$ bin/cake import -f news.csv -t news -p my-folder-uname

# translations
$ bin/cake import --file translations.csv --type translations
$ bin/cake import -f translations.csv -t translations
```

### TranslateFileCommand

This command provides a tool to translate the content of a file from one language to another, using a translator service (i.e., set in `config/app_local.php`).

Translator service configuration example:
```php
'Translators' => [
    'deepl' => [
        'name' => 'DeepL',
        'class' => '\BEdita\I18n\Deepl\Core\Translator',
        'options' => [
            'auth_key' => '************',
        ],
    ],
]
```

Usage example:
```bash
$ bin/cake translate_file \
  --input articles-en.txt \
  --output articles-it.txt \
  --from en \
  --to it \
  --translator deepl
```

### TranslateObjectsCommand

This command provides a tool to translate the content of objects from one language to another, using a translator service (i.e., set in `config/app_local.php`, as described above).

Usage examples:
```bash
# basic
$ bin/cake translate_objects \
  --from en \
  --to it \
  --engine deepl

# dry-run
$ bin/cake translate_objects \
  --from en \
  --to it \
  --engine deepl \
  --dry-run yes

# limit
$ bin/cake translate_objects \
  --from en \
  --to it \
  --engine deepl \
  --limit 10

# status
$ bin/cake translate_objects \
  --from en \
  --to it \
  --engine deepl \
  --status draft

# type
$ bin/cake translate_objects \
  --from en \
  --to it \
  --engine deepl \
  --type articles
```

## Utilities

You can find some utility classes in `src/Utility` folder.

### CsvTrait

This trait provides `readCsv` method to progressively read a csv file line by line.

Usage example:
```php
use BEdita\ImportTools\Utility\CsvTrait;

class MyImporter
{
    use CsvTrait;

    public function import(string $filename): void
    {
        foreach ($this->readCsv($filename) as $obj) {
            // process $obj
        }
    }
}
```

### FileTrait

This trait provides `readFileStream` method to open "read-only" file stream (you can use local filesystem or adapter).

Usage example:
```php
use BEdita\ImportTools\Utility\FileTrait;

class MyImporter
{
    use FileTrait;

    public function read(string $file): void
    {
        [$fh, $close] = $this->readFileStream($path);

        try {
            flock($fh, LOCK_SH);
            // do your stuff
        } finally {
            $close();
        }
    }
}
```

### TreeTrait

This trait provides `setParent` method to save the parent for a specified entity.

Usage example:
```php
use BEdita\ImportTools\Utility\TreeTrait;

class MyImporter
{
    use TreeTrait;

    public function import(string $filename, string $destination): void
    {
        foreach ($this->readCsv($filename) as $obj) {
            $this->setParent($obj, $destination);
        }
    }
}
```

### Import

This class provides functions to import data from csv files into BEdita.

Public methods are:

- `saveObjects`: read data from csv and save objects
- `saveObject`: save a single object
- `saveTranslations`: read data from csv and save translations
- `saveTranslation`: save a single translation
- `translatedFields`: get translated fields for a given object

Usage example:
```php
use BEdita\ImportTools\Utility\Import;

class MyImporter
{
    public function import(string $filename, string $type, ?string $parent, ?bool $dryrun): void
    {
        $import = new Import($filename, $type, $parent, $dryrun);
        $import->saveObjects();
    }
}
```
