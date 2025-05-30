<?php

declare(strict_types=1);

namespace Ajgl\Flysystem\Cow\Tests;

use Ajgl\Flysystem\Cow\CowFilesystemAdapter;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

final class CowFilesystemAdapterTest extends FilesystemAdapterTestCase
{
    private static InMemoryFilesystemAdapter $base;

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new CowFilesystemAdapter(self::$base);
    }

    protected function setUp(): void
    {
        self::$base = new InMemoryFilesystemAdapter();
        self::clearFilesystemAdapterCache();
    }

    public function generating_a_public_url(): void
    {
        $this->markTestSkipped('In memory adapter does not supply public URls');
    }

    public function generating_a_temporary_url(): void
    {
        $this->markTestSkipped('In memory adapter does not supply temporary URls');
    }

    /**
     * @test
     */
    public function checking_if_a_soft_deleted_file_exists(): void
    {
        self::$base->write('1.txt', '1', new Config());
        $this->assertTrue($this->adapter()->fileExists('1.txt'));
        $this->adapter()->delete('1.txt');
        $this->assertFalse($this->adapter()->fileExists('1.txt'));
        $this->assertTrue(self::$base->fileExists('1.txt'));
    }

    /**
     * @test
     */
    public function checking_if_a_soft_deleted_directory_exists(): void
    {
        self::$base->createDirectory('2', new Config());
        $this->assertTrue($this->adapter()->directoryExists('2'));
        $this->adapter()->deleteDirectory('2');
        $this->assertFalse($this->adapter()->directoryExists('2'));
        $this->assertTrue(self::$base->directoryExists('2'));
    }

    /**
     * @test
     */
    public function checking_if_a_soft_deleted_child_directory_exists(): void
    {
        self::$base->createDirectory('3/3', new Config());
        $this->assertTrue($this->adapter()->directoryExists('3/3'));
        $this->adapter()->deleteDirectory('3');
        $this->assertFalse($this->adapter()->directoryExists('3/3'));
        $this->assertTrue(self::$base->directoryExists('3/3'));
    }

    /**
     * @test
     */
    public function checking_if_a_soft_deleted_parent_directory_exists(): void
    {
        self::$base->createDirectory('3/3', new Config());
        $this->assertTrue($this->adapter()->directoryExists('3'));
        $this->adapter()->deleteDirectory('3/3');
        $this->assertFalse($this->adapter()->directoryExists('3/3'));
        $this->assertTrue(self::$base->directoryExists('3/3'));
        $this->assertTrue($this->adapter()->directoryExists('3'));
    }

    /**
     * @test
     */
    public function overwriting_a_base_file(): void
    {
        self::$base->write('1.txt', '1', new Config());
        $this->assertSame('1', $this->adapter()->read('1.txt'));
        $this->adapter()->write('1.txt', 'one', new Config());
        $this->assertSame('one', $this->adapter()->read('1.txt'));
        $this->assertSame('1', self::$base->read('1.txt'));
    }

    /**
     * @test
     */
    public function mergedListContent(): void
    {
        self::$base->write('1.txt', '1', new Config());
        self::$base->write('2/2.txt', '22', new Config());
        self::$base->write('3/3/3.txt', '333', new Config());
        $this->adapter()->deleteDirectory('3/3');
        $this->adapter()->write('3/2.txt', '32', new Config());
        $this->adapter()->write('3/2/1.txt', '321', new Config());
        $this->adapter()->write('4/3/2/1.txt', '4321', new Config());
        $expetedDirectories = ['2' => null, '3' => null, '3/2' => null, '4' => null, '4/3' => null, '4/3/2' => null];
        $expetedFiles = ['1.txt' => null, '2/2.txt' => null, '3/2.txt' => null,'3/2/1.txt' => null,'4/3/2/1.txt' => null];

        foreach ($this->adapter()->listContents('/', true) as $k => $storageAttributes) {
            $this->assertTrue(array_key_exists($storageAttributes->path(), $storageAttributes->isDir() ? $expetedDirectories : $expetedFiles), sprintf('Path "%s" of type "%s" does not exists.', $storageAttributes->path(), $storageAttributes->type()));
            if ($storageAttributes->isDir()) {
                unset($expetedDirectories[$storageAttributes->path()]);
            } else {
                unset($expetedFiles[$storageAttributes->path()]);
            }
        }

        $this->assertCount(0, $expetedDirectories);
        $this->assertCount(0, $expetedFiles);
    }
}
