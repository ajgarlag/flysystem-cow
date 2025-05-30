<?php

declare(strict_types=1);

/*
 * AJGL Flysystem COW adapter
 *
 * Copyright (C) Antonio J. García Lagar <aj@garcialagar.es>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ajgl\Flysystem\Cow;

use League\Flysystem\CalculateChecksumFromStream;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

final class CowFilesystemAdapter implements FilesystemAdapter, PublicUrlGenerator, ChecksumProvider, TemporaryUrlGenerator
{
    use CalculateChecksumFromStream;

    private const SOFT_DELETED_FILES_DEFAULT_PATH = '.soft_deleted_files.json';
    private const SOFT_DELETED_DIRECTORIES_DEFAULT_PATH = '.soft_deleted_directories.json';

    private ReadOnlyFilesystemAdapter $base;
    private FilesystemAdapter $top;

    public function __construct(
        FilesystemAdapter $base,
        ?FilesystemAdapter $top = null,
        private string $softDeletedFilesPath = self::SOFT_DELETED_FILES_DEFAULT_PATH,
        private string $softDeletedDirectoriesPath = self::SOFT_DELETED_DIRECTORIES_DEFAULT_PATH,
    ) {
        if (!$base instanceof ReadOnlyFilesystemAdapter) {
            $base = new ReadOnlyFilesystemAdapter($base);
        }
        $this->base = $base;
        $this->top = $top ?? new InMemoryFilesystemAdapter();
    }

    public function getBaseAdapter(): ReadOnlyFilesystemAdapter
    {
        return $this->base;
    }

    public function getTopAdapter(): FilesystemAdapter
    {
        return $this->top;
    }

    private function getFileReadAdapter(string $path): FilesystemAdapter
    {
        if ($this->isSoftDeletedFile($path) || $this->top->fileExists($path)) {
            return $this->top;
        }

        return $this->base;
    }

    private function isSoftDeletedFile(string $path): bool
    {
        $softDeletedFiles = $this->getSoftDeletedPaths($this->softDeletedFilesPath);

        return array_key_exists($this->prepareFilePath($path), $softDeletedFiles);
    }

    /**
     * @return array<string,null>
     */
    private function getSoftDeletedPaths(string $softDeletedFilePath): array
    {
        if (!$this->top->fileExists($softDeletedFilePath)) {
            return [];
        }

        /** @var array<string,null> */
        $softDeletedFiles = json_decode($this->top->read($softDeletedFilePath), true);

        return $softDeletedFiles;
    }

    /**
     * @param array<string,null> $softDeletedFiles
     */
    private function writeSoftDeletedPaths(string $softDeletedFilePath, array $softDeletedFiles): void
    {
        $content = json_encode($softDeletedFiles);
        if (false === $content) {
            throw new \RuntimeException('Unable to JSON encode soft deleted paths.');
        }

        $this->top->write($softDeletedFilePath, $content, new Config());
    }

    public function fileExists(string $path): bool
    {
        return $this->getFileReadAdapter($path)->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->top->directoryExists($path) || (!$this->isSoftDeletedDirectory($path) && $this->base->directoryExists($path));
    }

    private function isSoftDeletedDirectory(string $path): bool
    {
        $directoryPath = $this->prepareDirectoryPath($path);

        foreach ($this->getSoftDeletedPaths($this->softDeletedDirectoriesPath) as $softDeletedDirectory => $value) {
            if ($softDeletedDirectory === $directoryPath || str_starts_with($directoryPath, $softDeletedDirectory)) {
                return true;
            }
        }

        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->top->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->top->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->getFileReadAdapter($path)->read($path);
    }

    public function readStream(string $path)
    {
        return $this->getFileReadAdapter($path)->readStream($path);
    }

    public function delete(string $path): void
    {
        if ($this->base->fileExists($path)) {
            $this->softDeleteFile($path);
        }

        if ($this->top->fileExists($path)) {
            $this->top->delete($path);
        }
    }

    private function softDeleteFile(string $path): void
    {
        $softDeletedFiles = $this->getSoftDeletedPaths($this->softDeletedFilesPath);
        $softDeletedFiles[$this->prepareFilePath($path)] = null;
        try {
            $this->writeSoftDeletedPaths($this->softDeletedFilesPath, $softDeletedFiles);
        } catch (\RuntimeException $e) {
            UnableToDeleteFile::atLocation($path, '', $e);
        }
    }

    private function prepareFilePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    public function deleteDirectory(string $path): void
    {
        if ($this->base->directoryExists($path)) {
            $this->softDeleteDirectory($path);
        }

        if ($this->top->directoryExists($path)) {
            $this->top->deleteDirectory($path);
        }
    }

    private function softDeleteDirectory(string $path): void
    {
        $softDeletedDirectories = $this->getSoftDeletedPaths($this->softDeletedDirectoriesPath);
        $softDeletedDirectories[$this->prepareDirectoryPath($path)] = null;
        try {
            $this->writeSoftDeletedPaths($this->softDeletedDirectoriesPath, $softDeletedDirectories);
        } catch (\RuntimeException $e) {
            UnableToDeleteDirectory::atLocation($path, '', $e);
        }
    }

    private function prepareDirectoryPath(string $path): string
    {
        return rtrim($this->prepareFilePath($path), '/') . '/';
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->top->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if (!$this->top->fileExists($path)) {
            try {
                $this->top->writeStream($path, $this->base->readStream($path), new Config());
            } catch (UnableToReadFile $e) {
                UnableToSetVisibility::atLocation($path, '', $e);
            }
        }

        $this->top->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->getFileReadAdapter($path)->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->getFileReadAdapter($path)->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->getFileReadAdapter($path)->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->getFileReadAdapter($path)->fileSize($path);
    }

    public function listContents(string $path, bool $deep, int $index = 0): iterable
    {
        $directoryPath = $this->prepareDirectoryPath($path);
        if ($this->top->directoryExists($directoryPath)) {
            foreach ($this->top->listContents($directoryPath, false) as $storageAttributes) {
                if ($storageAttributes->isDir()) {
                    if ($deep) {
                        foreach ($this->listContents($storageAttributes->path(), $deep, $index) as $index => $s) {
                            /** @var int $index */
                            yield $index++ => $s;
                        }
                    }
                }

                if ($storageAttributes->path() === $this->softDeletedFilesPath || $storageAttributes->path() === $this->softDeletedDirectoriesPath) {
                    continue;
                }

                yield $index++ => $storageAttributes;
            }
        }

        if ($this->base->directoryExists($directoryPath) && !$this->isSoftDeletedDirectory($directoryPath)) {
            foreach ($this->base->listContents($directoryPath, false) as $storageAttributes) {
                if ($storageAttributes->isDir()) {
                    if ($this->top->directoryExists($storageAttributes->path()) || $this->isSoftDeletedDirectory($storageAttributes->path())) {
                        continue;
                    }
                    if ($deep) {
                        foreach ($this->listContents($storageAttributes->path(), $deep, $index) as $index => $s) {
                            /** @var int $index */
                            yield $index++ => $s;
                        }
                    }
                }

                if ($storageAttributes->isFile() && ($this->top->fileExists($storageAttributes->path()) || $this->isSoftDeletedFile($storageAttributes->path()))) {
                    continue;
                }

                yield $index++ => $storageAttributes;
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        if ($this->top->fileExists($source) || $this->isSoftDeletedFile($source)) {
            $this->top->move($source, $destination, $config);

            return;
        }

        try {
            $this->top->writeStream($destination, $this->base->readStream($source), $config);
        } catch (UnableToReadFile $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
        $this->softDeleteFile($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if ($this->top->fileExists($source) || $this->isSoftDeletedFile($source)) {
            $this->top->copy($source, $destination, $config);

            return;
        }

        try {
            $this->top->writeStream($destination, $this->base->readStream($source), $config);
        } catch (UnableToReadFile $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function publicUrl(string $path, Config $config): string
    {
        if ($this->top instanceof PublicUrlGenerator && ($this->top->fileExists($path) || $this->isSoftDeletedFile($path))) {
            return $this->top->publicUrl($path, $config);
        }

        return $this->base->publicUrl($path, $config);
    }

    public function checksum(string $path, Config $config): string
    {
        if ($this->top instanceof ChecksumProvider && ($this->top->fileExists($path) || $this->isSoftDeletedFile($path))) {
            return $this->top->checksum($path, $config);
        }

        return $this->calculateChecksumFromStream($path, $config);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        if ($this->top instanceof TemporaryUrlGenerator && ($this->top->fileExists($path) || $this->isSoftDeletedFile($path))) {
            return $this->top->temporaryUrl($path, $expiresAt, $config);
        }

        return $this->base->temporaryUrl($path, $expiresAt, $config);
    }
}
