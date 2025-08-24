<?php

namespace Fs\Models;

use Fs\Enumerations\Whence;
use Fs\Exceptions\FsException;

interface StreamInterface
{
    public function isOpen(): bool;

    public function isReadable(): bool;

    /**
     * @throws FsException
     */
    public function read(?int $length = null): string;

    public function isWritable(): bool;

    /**
     * @throws FsException
     */
    public function write(string $data): int;

    public function isSeekable(): bool;

    /**
     * @throws FsException
     */
    public function seek(int $offset, Whence $whence): void;

    /**
     * @throws FsException
     */
    public function tell(): int;

    /**
     * @throws FsException
     */
    public function eof(): bool;

    /**
     * @throws FsException
     */
    public function rewind(): void;

    /**
     * @throws FsException
     */
    public function getSize(): int;

    /**
     * @throws FsException
     */
    public function lock(bool $block = true): void;

    /**
     * @throws FsException
     */
    public function unlock(): void;

    /**
     * @throws FsException
     */
    public function close(): void;
}
