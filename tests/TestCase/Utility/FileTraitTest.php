<?php
declare(strict_types=1);

namespace BEdita\ImportTools\Test\TestCase\Utility;

use BEdita\ImportTools\Utility\FileTrait;
use Exception;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToResolveFilesystemMount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * {@see \BEdita\ImportTools\Utility\FileTrait} Test Case
 */
#[CoversClass(FileTrait::class)]
class FileTraitTest extends TestCase
{
    use FileTrait;

    /**
     * Data provider for {@see FileTraitTest::testReadFileStream()} test case.
     *
     * @return array<string, array{string|\Exception, string}>
     */
    public static function readFileStreamProvider(): array
    {
        $example = file_get_contents(TEST_FILES . DS . 'example.txt');

        return [
            'file (local)' => [$example, TEST_FILES . DS . 'example.txt'],
            'file (`file://` stream wrapper)' => [$example, 'file://' . TEST_FILES . DS . 'example.txt'],
            'file (filesystem registry)' => [$example, 'test-data://example.txt'],
            'file not found (local)' => [
                new RuntimeException(sprintf('Cannot open file: %s', TEST_FILES . DS . 'not-found.csv')),
                TEST_FILES . DS . 'not-found.csv',
            ],
            'file not found (filesystem registry)' => [
                new UnableToReadFile('Unable to read file from location: test-data://not-found.csv.'),
                'test-data://not-found.csv',
            ],
            'bad protocol (filesystem registry)' => [
                new UnableToResolveFilesystemMount('Unable to resolve the filesystem mount because the mount (unregistered-protocol) was not registered.'),
                'unregistered-protocol://example.txt',
            ],
        ];
    }

    /**
     * Test {@see \BEdita\ImportTools\Utility\FileTrait::readFileStream()} method.
     *
     * @param string|\Exception $expected Expected outcome.
     * @param string $path Path for input.
     * @return void
     */
    #[DataProvider('readFileStreamProvider')]
    public function testReadFileStream($expected, string $path): void
    {
        if ($expected instanceof Exception) {
            $this->expectExceptionObject($expected);
        }

        $return = static::readFileStream($path);
        static::assertCount(2, $return);
        static::assertArrayHasKey(0, $return);
        $stream = $return[0];
        static::assertIsResource($stream);
        static::assertIsNotClosedResource($stream);
        static::assertArrayHasKey(1, $return);
        $closingFn = $return[1];
        static::assertIsCallable($closingFn);

        $actual = stream_get_contents($stream);
        static::assertSame($expected, $actual);

        $closingFn();
        static::assertIsClosedResource($stream);
    }
}
