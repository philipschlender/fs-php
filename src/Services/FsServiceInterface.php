<?php

namespace Fs\Services;

use Fs\Enumerations\Mode;
use Fs\Exceptions\FsException;
use Fs\Models\StreamInterface;

interface FsServiceInterface
{
    /**
     * @return \Generator<string>
     *
     * @throws FsException
     */
    public function list(string $path, bool $recursive = false): \Generator;

    public function isDirectory(string $path): bool;

    public function isFile(string $path): bool;

    public function exists(string $path): bool;

    /**
     * @throws FsException
     */
    public function makeDirectory(string $path, int $mode = 0775): void;

    /**
     * @throws FsException
     */
    public function readFile(string $path): string;

    /**
     * @throws FsException
     */
    public function writeFile(string $path, string $data, int $mode = 0664): void;

    /**
     * @throws FsException
     */
    public function touch(string $path, ?int $modificationTime = null, ?int $accessTime = null): void;

    /**
     * @throws FsException
     */
    public function remove(string $path): void;

    /**
     * @throws FsException
     */
    public function copy(string $sourcePath, string $targetPath): void;

    /**
     * @throws FsException
     */
    public function move(string $sourcePath, string $targetPath): void;

    /**
     * @throws FsException
     */
    public function getMimeContentType(string $path): string;

    /**
     * @throws FsException
     */
    public function getMode(string $path): int;

    /**
     * @throws FsException
     */
    public function changeMode(string $path, int $mode): void;

    /**
     * @throws FsException
     */
    public function getSize(string $path): int;

    /**
     * @throws FsException
     */
    public function openStream(string $path, Mode $mode): StreamInterface;
}
