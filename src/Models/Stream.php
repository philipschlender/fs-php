<?php

namespace Fs\Models;

use Fs\Exceptions\FsException;
use Io\Enumerations\Mode;
use Io\Models\Stream as IoStream;

class Stream extends IoStream implements StreamInterface
{
    /**
     * @throws FsException
     */
    public function lock(bool $block = true): void
    {
        if (!$this->isOpen) {
            throw new FsException('The stream must be open.');
        }

        $operation = match ($this->mode) {
            Mode::Read => LOCK_SH,
            Mode::Write => LOCK_EX,
            Mode::Append => LOCK_EX,
        };

        if (!$block) {
            $operation = $operation | LOCK_NB;
        }

        if (!flock($this->stream, $operation)) {
            throw new FsException('Failed to lock the stream.');
        }
    }

    /**
     * @throws FsException
     */
    public function unlock(): void
    {
        if (!$this->isOpen) {
            throw new FsException('The stream must be open.');
        }

        if (!flock($this->stream, LOCK_UN)) {
            throw new FsException('Failed to unlock the stream.');
        }
    }
}
