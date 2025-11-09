<?php

namespace Tests;

use Fs\Exceptions\FsException;
use Fs\Models\Stream;
use Io\Enumerations\Mode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

class StreamTest extends FsTestCase
{
    #[DataProvider('dataProviderLock')]
    #[DoesNotPerformAssertions]
    public function testLock(Mode $mode, bool $block): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFsGenerator()->randomFile());

        if (Mode::Read === $mode) {
            $stream = new Stream($file, Mode::Write);

            $stream->close();
        }

        $stream = new Stream($file, $mode);

        $stream->lock($block);

        $stream->unlock();

        $stream->close();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderLock(): array
    {
        return [
            [
                'mode' => Mode::Read,
                'block' => true,
            ],
            [
                'mode' => Mode::Write,
                'block' => true,
            ],
            [
                'mode' => Mode::Append,
                'block' => true,
            ],
            [
                'mode' => Mode::Read,
                'block' => false,
            ],
            [
                'mode' => Mode::Write,
                'block' => false,
            ],
            [
                'mode' => Mode::Append,
                'block' => false,
            ],
        ];
    }

    public function testLockStreamClosed(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be open.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFsGenerator()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->lock();
    }

    public function testLockMultipleLocks(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('Failed to lock the stream.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFsGenerator()->randomFile());

        $stream1 = new Stream($file, Mode::Write);
        $stream2 = new Stream($file, Mode::Write);

        $stream1->lock();
        $stream2->lock(false);
    }

    #[DoesNotPerformAssertions]
    public function testUnlock(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFsGenerator()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->lock();

        $stream->unlock();

        $stream->close();
    }

    public function testUnlockStreamClosed(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be open.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFsGenerator()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->unlock();
    }
}
