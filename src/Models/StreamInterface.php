<?php

namespace Fs\Models;

use Fs\Exceptions\FsException;
use Io\Models\StreamInterface as IoStreamInterface;

interface StreamInterface extends IoStreamInterface
{
    /**
     * @throws FsException
     */
    public function lock(bool $block = true): void;

    /**
     * @throws FsException
     */
    public function unlock(): void;
}
