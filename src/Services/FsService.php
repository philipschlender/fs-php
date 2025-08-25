<?php

namespace Fs\Services;

use Fs\Enumerations\Mode;
use Fs\Exceptions\FsException;
use Fs\Models\Stream;
use Fs\Models\StreamInterface;

class FsService implements FsServiceInterface
{
    /**
     * @return \Generator<string>
     *
     * @throws FsException
     */
    public function list(string $path, bool $recursive = false): \Generator
    {
        if (!$this->isDirectory($path)) {
            throw new FsException(sprintf('The path %s must be a directory.', $path));
        }

        if (!$recursive) {
            $iterator = new \DirectoryIterator($path);
        } else {
            $iterator = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);
        }

        $offset = strlen($path) + 1;

        /** @var \SplFileInfo $splFileInfo */
        foreach ($iterator as $splFileInfo) {
            if (
                str_ends_with($splFileInfo->getPathname(), '/.')
                || str_ends_with($splFileInfo->getPathname(), '/..')
            ) {
                continue;
            }

            yield substr($splFileInfo->getPathname(), $offset);
        }
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function exists(string $path): bool
    {
        return $this->isDirectory($path) || $this->isFile($path);
    }

    /**
     * @throws FsException
     */
    public function makeDirectory(string $path, int $mode = 0775): void
    {
        if ($this->exists($path)) {
            throw new FsException(sprintf('The path %s already exists.', $path));
        }

        $umask = umask(0);

        $success = @mkdir($path, $mode, true);

        umask($umask);

        if (!$success) {
            throw new FsException(sprintf('Failed to create the directory %s.', $path));
        }
    }

    /**
     * @throws FsException
     */
    public function readFile(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new FsException(sprintf('The path %s must be a file.', $path));
        }

        $stream = $this->openStream($path, Mode::Read);

        $data = $stream->read();

        $stream->close();

        return $data;
    }

    /**
     * @throws FsException
     */
    public function writeFile(string $path, string $data, int $mode = 0664): void
    {
        if ($this->exists($path)) {
            throw new FsException(sprintf('The path %s already exists.', $path));
        }

        $pathDirectory = pathinfo($path, PATHINFO_DIRNAME);

        if (!$this->isDirectory($pathDirectory)) {
            throw new FsException(sprintf('The path %s must exist.', $pathDirectory));
        }

        $stream = $this->openStream($path, Mode::Write);

        $stream->write($data);

        $stream->close();

        $this->changeMode($path, $mode);
    }

    /**
     * @throws FsException
     */
    public function touch(string $path, ?int $modificationTime = null, ?int $accessTime = null): void
    {
        if (is_int($modificationTime) && $modificationTime < 0) {
            throw new FsException(sprintf('The modification time %d must be greater than or equal to 0.', $modificationTime));
        }

        if (is_int($modificationTime) && $accessTime < 0) {
            throw new FsException(sprintf('The access time %d must be greater than or equal to 0.', $accessTime));
        }

        if (is_null($modificationTime) && is_int($accessTime)) {
            throw new FsException(sprintf('The modification time null must be greater than or equal to 0, when the access time %d is greater than or equal to 0.', $accessTime));
        }

        $pathDirectory = pathinfo($path, PATHINFO_DIRNAME);

        if (!$this->isDirectory($pathDirectory)) {
            throw new FsException(sprintf('The path %s must exist.', $pathDirectory));
        }

        if (!touch($path, $modificationTime, $accessTime)) {
            throw new FsException(sprintf('Failed to touch the file %s.', $path));
        }
    }

    /**
     * @throws FsException
     */
    public function remove(string $path): void
    {
        if (!$this->exists($path)) {
            throw new FsException(sprintf('The path %s must exist.', $path));
        }

        if ($this->isDirectory($path)) {
            $this->removeDirectory($path);

            return;
        }

        $this->removeFile($path);
    }

    /**
     * @throws FsException
     */
    public function copy(string $sourcePath, string $targetPath): void
    {
        if (!$this->exists($sourcePath)) {
            throw new FsException(sprintf('The source path %s must exist.', $sourcePath));
        }

        if ($this->exists($targetPath)) {
            throw new FsException(sprintf('The target path %s already exists.', $targetPath));
        }

        $targetPathDirectory = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!$this->isDirectory($targetPathDirectory)) {
            throw new FsException(sprintf('The target path %s must exist.', $targetPathDirectory));
        }

        if ($this->isDirectory($sourcePath)) {
            $this->copyDirectory($sourcePath, $targetPath);

            return;
        }

        $this->copyFile($sourcePath, $targetPath);
    }

    /**
     * @throws FsException
     */
    public function move(string $sourcePath, string $targetPath): void
    {
        if (!$this->exists($sourcePath)) {
            throw new FsException(sprintf('The source path %s must exist.', $sourcePath));
        }

        if ($this->exists($targetPath)) {
            throw new FsException(sprintf('The target path %s already exists.', $targetPath));
        }

        $targetPathDirectory = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!$this->isDirectory($targetPathDirectory)) {
            throw new FsException(sprintf('The target path %s must exist.', $targetPathDirectory));
        }

        $type = $this->isDirectory($sourcePath) ? 'directory' : 'file';

        if (!rename($sourcePath, $targetPath)) {
            throw new FsException(sprintf('Failed to move the %s %s to %s.', $type, $sourcePath, $targetPath));
        }
    }

    /**
     * @throws FsException
     */
    public function getMimeContentType(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new FsException(sprintf('The path %s must be a file.', $path));
        }

        $fileMimeContentType = @mime_content_type($path);

        if (!is_string($fileMimeContentType)) {
            throw new FsException(sprintf('Failed to get the mime content type of the file %s.', $path));
        }

        return $fileMimeContentType;
    }

    /**
     * @throws FsException
     */
    public function getMode(string $path): int
    {
        if (!$this->exists($path)) {
            throw new FsException(sprintf('The path %s must exist.', $path));
        }

        $type = $this->isDirectory($path) ? 'directory' : 'file';

        $mode = @fileperms($path);

        if (!is_int($mode)) {
            throw new FsException(sprintf('Failed to get the mode of the %s %s.', $type, $path));
        }

        return $mode & 0777;
    }

    /**
     * @throws FsException
     */
    public function changeMode(string $path, int $mode): void
    {
        if (!$this->exists($path)) {
            throw new FsException(sprintf('The path %s must exist.', $path));
        }

        $type = $this->isDirectory($path) ? 'directory' : 'file';

        $umask = umask(0);

        if (!@chmod($path, $mode)) {
            throw new FsException(sprintf('Failed to change the mode of the %s %s.', $type, $path));
        }

        umask($umask);
    }

    /**
     * @throws FsException
     */
    public function getSize(string $path): int
    {
        if (!$this->exists($path)) {
            throw new FsException(sprintf('The path %s must exist.', $path));
        }

        if ($this->isDirectory($path)) {
            return $this->getSizeOfDirectory($path);
        }

        return $this->getSizeOfFile($path);
    }

    /**
     * @throws FsException
     */
    public function openStream(string $path, Mode $mode): StreamInterface
    {
        return new Stream($path, $mode);
    }

    /**
     * @throws FsException
     */
    protected function removeDirectory(string $path): void
    {
        if (!$this->isDirectory($path)) {
            throw new FsException(sprintf('The path %s must be a directory.', $path));
        }

        $subPaths = $this->list($path, true);

        foreach ($subPaths as $subPath) {
            $subPath = sprintf('%s/%s', $path, $subPath);

            if ($this->isDirectory($subPath)) {
                if (!@rmdir($subPath)) {
                    throw new FsException(sprintf('Failed to delete the directory %s.', $subPath));
                }
            } elseif ($this->isFile($subPath)) {
                $this->removeFile($subPath);
            }
        }

        if (!@rmdir($path)) {
            throw new FsException(sprintf('Failed to delete the directory %s.', $path));
        }
    }

    /**
     * @throws FsException
     */
    protected function removeFile(string $path): void
    {
        if (!$this->isFile($path)) {
            throw new FsException(sprintf('The path %s must be a file.', $path));
        }

        if (!@unlink($path)) {
            throw new FsException(sprintf('Failed to remove the file %s.', $path));
        }
    }

    /**
     * @throws FsException
     */
    protected function copyDirectory(string $sourcePath, string $targetPath): void
    {
        if (!$this->isDirectory($sourcePath)) {
            throw new FsException(sprintf('The source path %s must be a directory.', $sourcePath));
        }

        if ($this->exists($targetPath)) {
            throw new FsException(sprintf('The target path %s already exists.', $targetPath));
        }

        $subPaths = $this->list($sourcePath, true);

        foreach ($subPaths as $subPath) {
            $sourceSubPath = sprintf('%s/%s', $sourcePath, $subPath);
            $targetSubPath = sprintf('%s/%s', $targetPath, $subPath);

            if ($this->isFile($sourceSubPath)) {
                $this->copyFile($sourceSubPath, $targetSubPath);
            }
        }
    }

    /**
     * @throws FsException
     */
    protected function copyFile(string $sourcePath, string $targetPath): void
    {
        if (!$this->isFile($sourcePath)) {
            throw new FsException(sprintf('The source path %s must be a file.', $sourcePath));
        }

        if ($this->exists($targetPath)) {
            throw new FsException(sprintf('The target path %s already exists.', $targetPath));
        }

        $sourcePathDirectory = pathinfo($sourcePath, PATHINFO_DIRNAME);
        $targetPathDirectory = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!$this->isDirectory($targetPathDirectory)) {
            $this->makeDirectory($targetPathDirectory, $this->getMode($sourcePathDirectory));
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new FsException(sprintf('Failed to copy the file %s to %s.', $sourcePath, $targetPath));
        }

        $this->changeMode($targetPath, $this->getMode($sourcePath));
    }

    /**
     * @throws FsException
     */
    protected function getSizeOfDirectory(string $path): int
    {
        if (!$this->isDirectory($path)) {
            throw new FsException(sprintf('The path %s must be a directory.', $path));
        }

        $size = 0;

        $subPaths = $this->list($path, true);

        foreach ($subPaths as $subPath) {
            $subPath = sprintf('%s/%s', $path, $subPath);

            if ($this->isFile($subPath)) {
                $size += $this->getSizeOfFile($subPath);
            }
        }

        return $size;
    }

    /**
     * @throws FsException
     */
    protected function getSizeOfFile(string $path): int
    {
        if (!$this->isFile($path)) {
            throw new FsException(sprintf('The path %s must be a file.', $path));
        }

        $fileSize = @filesize($path);

        if (!is_int($fileSize)) {
            throw new FsException(sprintf('Failed to get the size of the file %s.', $path));
        }

        return $fileSize;
    }
}
