<?php

namespace Fs\Models;

use Fs\Enumerations\Mode;
use Fs\Enumerations\Whence;
use Fs\Exceptions\FsException;

class Stream implements StreamInterface
{
    /**
     * @var resource
     */
    protected $stream;

    protected bool $isOpen;

    /**
     * @throws FsException
     */
    public function __construct(protected string $path, protected Mode $mode)
    {
        $this->stream = $this->open($path, $mode);
        $this->isOpen = true;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function isReadable(): bool
    {
        if (!$this->isOpen) {
            return false;
        }

        return match ($this->mode) {
            Mode::Read => true,
            Mode::Write => true,
            Mode::Append => true,
        };
    }

    /**
     * @throws FsException
     */
    public function read(?int $length = null): string
    {
        if (!$this->isReadable()) {
            throw new FsException('The stream must be readable.');
        }

        if (is_int($length) && $length < 1) {
            throw new FsException(sprintf('The length %d must be greater than or equal to 1.', $length));
        }

        $data = '';

        while (true) {
            if ($this->eof()) {
                break;
            }

            $dataChunk = fread($this->stream, $length ?? 8192);

            if (!is_string($dataChunk)) {
                throw new FsException('Failed to read a data chunk of the stream.');
            }

            $data = sprintf('%s%s', $data, $dataChunk);

            if (is_int($length)) {
                break;
            }
        }

        return $data;
    }

    public function isWritable(): bool
    {
        if (!$this->isOpen) {
            return false;
        }

        return match ($this->mode) {
            Mode::Read => false,
            Mode::Write => true,
            Mode::Append => true,
        };
    }

    /**
     * @throws FsException
     */
    public function write(string $data): int
    {
        if (!$this->isWritable()) {
            throw new FsException('The stream must be writable.');
        }

        $numberOfBytes = @fwrite($this->stream, $data);

        if (!is_int($numberOfBytes)) {
            throw new FsException('Failed to write the data to the stream.');
        }

        return $numberOfBytes;
    }

    public function isSeekable(): bool
    {
        if (!$this->isOpen) {
            return false;
        }

        $metaData = stream_get_meta_data($this->stream);

        return $metaData['seekable'];
    }

    /**
     * @throws FsException
     */
    public function seek(int $offset, Whence $whence): void
    {
        if (!$this->isSeekable()) {
            throw new FsException('The stream must be seekable.');
        }

        $whenceInt = match ($whence) {
            Whence::Start => SEEK_SET,
            Whence::Current => SEEK_CUR,
            Whence::End => SEEK_END,
        };

        if (0 !== fseek($this->stream, $offset, $whenceInt)) {
            throw new FsException('Failed to set the position of the pointer on the stream.');
        }
    }

    /**
     * @throws FsException
     */
    public function tell(): int
    {
        if (!$this->isOpen) {
            throw new FsException('The stream must be open.');
        }

        $position = ftell($this->stream);

        if (!is_int($position)) {
            throw new FsException('Failed to get the position of the pointer of the stream.');
        }

        return $position;
    }

    /**
     * @throws FsException
     */
    public function eof(): bool
    {
        if (!$this->isOpen) {
            throw new FsException('The stream must be open.');
        }

        return feof($this->stream);
    }

    /**
     * @throws FsException
     */
    public function rewind(): void
    {
        if (!$this->isOpen) {
            throw new FsException('The stream must be open.');
        }

        if (!rewind($this->stream)) {
            throw new FsException('Failed to rewind the stream.');
        }
    }

    /**
     * @throws FsException
     */
    public function getSize(): int
    {
        if (!$this->isOpen) {
            throw new FsException('The stream must be open.');
        }

        $statistics = fstat($this->stream);

        if (!is_array($statistics)) {
            throw new FsException('Failed to get the statistics of the stream.');
        }

        return $statistics['size'];
    }

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

    /**
     * @throws FsException
     */
    public function close(): void
    {
        if (!$this->isOpen) {
            return;
        }

        if (!fclose($this->stream)) {
            throw new FsException('Failed to close the stream.');
        }

        $this->isOpen = false;
    }

    /**
     * @return resource
     *
     * @throws FsException
     */
    protected function open(string $path, Mode $mode)
    {
        $modeString = match ($mode) {
            Mode::Read => 'rb',
            Mode::Write => 'wb+',
            Mode::Append => 'ab+',
        };

        $stream = @fopen($path, $modeString);

        if (!is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new FsException(sprintf('Failed to open the file %s.', $path));
        }

        return $stream;
    }
}
