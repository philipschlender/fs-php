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
            throw new FsException('The path must be a directory.');
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
            throw new FsException('The path already exists.');
        }

        $umask = umask(0);

        $success = @mkdir($path, $mode, true);

        umask($umask);

        if (!$success) {
            throw new FsException('Failed to create the directory.');
        }
    }

    /**
     * @throws FsException
     */
    public function readFile(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new FsException('The path must be a file.');
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
            throw new FsException('The path already exists.');
        }

        $pathDirectory = pathinfo($path, PATHINFO_DIRNAME);

        if (!$this->isDirectory($pathDirectory)) {
            throw new FsException('The path must exist.');
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
            throw new FsException('The modification time must be greater than or equal to 0.');
        }

        if (is_int($modificationTime) && $accessTime < 0) {
            throw new FsException('The access time must be greater than or equal to 0.');
        }

        if (is_null($modificationTime) && is_int($accessTime)) {
            throw new FsException('The modification time must be greater than or equal to 0, when the access time is greater than or equal to 0.');
        }

        $pathDirectory = pathinfo($path, PATHINFO_DIRNAME);

        if (!$this->isDirectory($pathDirectory)) {
            throw new FsException('The path must exist.');
        }

        if (!touch($path, $modificationTime, $accessTime)) {
            throw new FsException('Failed to touch the file.');
        }
    }

    /**
     * @throws FsException
     */
    public function remove(string $path): void
    {
        if (!$this->exists($path)) {
            throw new FsException('The path must exist.');
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
            throw new FsException('The source path must exist.');
        }

        if ($this->exists($targetPath)) {
            throw new FsException('The target path already exists.');
        }

        $targetPathDirectory = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!$this->isDirectory($targetPathDirectory)) {
            throw new FsException('The target path must exist.');
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
            throw new FsException('The source path must exist.');
        }

        if ($this->exists($targetPath)) {
            throw new FsException('The target path already exists.');
        }

        $targetPathDirectory = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!$this->isDirectory($targetPathDirectory)) {
            throw new FsException('The target path must exist.');
        }

        $type = $this->isDirectory($sourcePath) ? 'directory' : 'file';

        if (!rename($sourcePath, $targetPath)) {
            throw new FsException(sprintf('Failed to move the %s.', $type));
        }
    }

    /**
     * @throws FsException
     */
    public function getMimeContentType(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new FsException('The path must be a file.');
        }

        $fileMimeContentType = @mime_content_type($path);

        if (!is_string($fileMimeContentType)) {
            throw new FsException('Failed to get the mime content type of the file.');
        }

        return $fileMimeContentType;
    }

    /**
     * @throws FsException
     */
    public function getMode(string $path): int
    {
        if (!$this->exists($path)) {
            throw new FsException('The path must exist.');
        }

        $type = $this->isDirectory($path) ? 'directory' : 'file';

        $mode = @fileperms($path);

        if (!is_int($mode)) {
            throw new FsException(sprintf('Failed to get the mode of the %s.', $type));
        }

        return $mode & 0777;
    }

    /**
     * @throws FsException
     */
    public function changeMode(string $path, int $mode): void
    {
        if (!$this->exists($path)) {
            throw new FsException('The path must exist.');
        }

        $type = $this->isDirectory($path) ? 'directory' : 'file';

        $umask = umask(0);

        if (!@chmod($path, $mode)) {
            throw new FsException(sprintf('Failed to change the mode of the %s.', $type));
        }

        umask($umask);
    }

    /**
     * @throws FsException
     */
    public function getSize(string $path): int
    {
        if (!$this->exists($path)) {
            throw new FsException('The path must exist.');
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
            throw new FsException('The path must be a directory.');
        }

        $subPaths = $this->list($path, true);

        foreach ($subPaths as $subPath) {
            $subPath = sprintf('%s/%s', $path, $subPath);

            if ($this->isDirectory($subPath)) {
                if (!@rmdir($subPath)) {
                    throw new FsException('Failed to delete the directory.');
                }
            } elseif ($this->isFile($subPath)) {
                $this->removeFile($subPath);
            }
        }

        if (!@rmdir($path)) {
            throw new FsException('Failed to delete the directory.');
        }
    }

    /**
     * @throws FsException
     */
    protected function removeFile(string $path): void
    {
        if (!$this->isFile($path)) {
            throw new FsException('The path must be a file.');
        }

        if (!@unlink($path)) {
            throw new FsException('Failed to remove the file.');
        }
    }

    /**
     * @throws FsException
     */
    protected function copyDirectory(string $sourcePath, string $targetPath): void
    {
        if (!$this->isDirectory($sourcePath)) {
            throw new FsException('The source path must be a directory.');
        }

        if ($this->exists($targetPath)) {
            throw new FsException('The target path already exists.');
        }

        $subPaths = $this->list($sourcePath, true);

        foreach ($subPaths as $subPath) {
            $sourceSubPath = sprintf('%s/%s', $sourcePath, $subPath);
            $targetSubPath = sprintf('%s/%s', $targetPath, $subPath);

            if ($this->isDirectory($sourceSubPath) && !$this->isDirectory($targetSubPath)) {
                $this->makeDirectory($targetSubPath, $this->getMode($sourceSubPath));
            } elseif ($this->isFile($sourceSubPath)) {
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
            throw new FsException('The source path must be a file.');
        }

        if ($this->exists($targetPath)) {
            throw new FsException('The target path already exists.');
        }

        $sourcePathDirectory = pathinfo($sourcePath, PATHINFO_DIRNAME);
        $targetPathDirectory = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!$this->isDirectory($targetPathDirectory)) {
            $this->makeDirectory($targetPathDirectory, $this->getMode($sourcePathDirectory));
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new FsException('Failed to copy the file.');
        }

        $this->changeMode($targetPath, $this->getMode($sourcePath));
    }

    /**
     * @throws FsException
     */
    protected function getSizeOfDirectory(string $path): int
    {
        if (!$this->isDirectory($path)) {
            throw new FsException('The path must be a directory.');
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
            throw new FsException('The path must be a file.');
        }

        $fileSize = @filesize($path);

        if (!is_int($fileSize)) {
            throw new FsException('Failed to get the size of the file.');
        }

        return $fileSize;
    }
}
