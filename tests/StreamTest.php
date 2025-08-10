<?php

namespace Tests;

use Fs\Enumerations\Mode;
use Fs\Enumerations\Whence;
use Fs\Exceptions\FsException;
use Fs\Models\Stream;
use PHPUnit\Framework\Attributes\DataProvider;

class StreamTest extends FsTestCase
{
    #[DataProvider('dataProviderConstruct')]
    public function testConstruct(Mode $mode): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        if (Mode::Read === $mode) {
            $stream = new Stream($file, Mode::Write);

            $stream->close();
        }

        $stream = new Stream($file, $mode);

        $stream->close();

        $this->assertInstanceOf(Stream::class, $stream);
    }

    /**
     * @return array<int,array<string,Mode>>
     */
    public static function dataProviderConstruct(): array
    {
        return [
            [
                'mode' => Mode::Read,
            ],
            [
                'mode' => Mode::Write,
            ],
            [
                'mode' => Mode::Append,
            ],
        ];
    }

    public function testConstructFailedToOpen(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('Failed to open the file %s.', $file));

        new Stream($file, Mode::Read);
    }

    #[DataProvider('dataProviderIsOpen')]
    public function testIsOpen(Mode $mode): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        if (Mode::Read === $mode) {
            $stream = new Stream($file, Mode::Write);

            $stream->close();
        }

        $stream = new Stream($file, $mode);

        $isOpen = $stream->isOpen();

        $stream->close();

        $this->assertTrue($isOpen);
    }

    /**
     * @return array<int,array<string,Mode>>
     */
    public static function dataProviderIsOpen(): array
    {
        return [
            [
                'mode' => Mode::Read,
            ],
            [
                'mode' => Mode::Write,
            ],
            [
                'mode' => Mode::Append,
            ],
        ];
    }

    #[DataProvider('dataProviderIsReadable')]
    public function testIsReadable(Mode $mode, bool $expectedIsReadable): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        if (Mode::Read === $mode) {
            $stream = new Stream($file, Mode::Write);

            $stream->close();
        }

        $stream = new Stream($file, $mode);

        $isReadable = $stream->isReadable();

        $stream->close();

        $this->assertEquals($expectedIsReadable, $isReadable);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderIsReadable(): array
    {
        return [
            [
                'mode' => Mode::Read,
                'expectedIsReadable' => true,
            ],
            [
                'mode' => Mode::Write,
                'expectedIsReadable' => true,
            ],
            [
                'mode' => Mode::Append,
                'expectedIsReadable' => true,
            ],
        ];
    }

    public function testIsReadableStreamClosed(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $isReadable = $stream->isReadable();

        $this->assertFalse($isReadable);
    }

    #[DataProvider('dataProviderRead')]
    public function testRead(string $data, ?int $length, string $expectedData): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->write($data);

        $stream->rewind();

        $dataRead = $stream->read($length);

        $stream->close();

        $this->assertEquals($expectedData, $dataRead);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderRead(): array
    {
        $data = 'abc';

        return [
            [
                'data' => $data,
                'length' => null,
                'expectedData' => $data,
            ],
            [
                'data' => $data,
                'length' => 1,
                'expectedData' => 'a',
            ],
        ];
    }

    public function testReadStreamNotReadable(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be readable.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->read();
    }

    public function testReadInvalidLength(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The length 0 must be greater than or equal to 1.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->read(0);
    }

    #[DataProvider('dataProviderIsWritable')]
    public function testIsWritable(Mode $mode, bool $expectedIsWritable): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        if (Mode::Read === $mode) {
            $stream = new Stream($file, Mode::Write);

            $stream->close();
        }

        $stream = new Stream($file, $mode);

        $isWritable = $stream->isWritable();

        $stream->close();

        $this->assertEquals($expectedIsWritable, $isWritable);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderIsWritable(): array
    {
        return [
            [
                'mode' => Mode::Read,
                'expectedIsWritable' => false,
            ],
            [
                'mode' => Mode::Write,
                'expectedIsWritable' => true,
            ],
            [
                'mode' => Mode::Append,
                'expectedIsWritable' => true,
            ],
        ];
    }

    public function testIsWritableStreamClosed(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $isWritable = $stream->isWritable();

        $this->assertFalse($isWritable);
    }

    public function testWrite(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $data = $this->fakerService->getCore()->randomString();

        $stream = new Stream($file, Mode::Write);

        $stream->write($data);

        $stream->rewind();

        $dataRead = $stream->read();

        $stream->close();

        $this->assertEquals($data, $dataRead);
    }

    public function testWriteStreamNotWritable(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be writable.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->write($this->fakerService->getCore()->randomString());
    }

    public function testIsSeekable(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $isSeekable = $stream->isSeekable();

        $stream->close();

        $this->assertTrue($isSeekable);
    }

    public function testIsSeekableStreamClosed(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $isSeekable = $stream->isSeekable();

        $this->assertFalse($isSeekable);
    }

    #[DataProvider('dataProviderSeek')]
    public function testSeek(string $data, int $offset, Whence $whence, string $expectedData): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->write($data);

        $stream->seek($offset, $whence);

        $dataRead = $stream->read(1);

        $stream->close();

        $this->assertEquals($expectedData, $dataRead);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderSeek(): array
    {
        $data = 'abc';

        return [
            [
                'data' => $data,
                'offset' => 0,
                'whence' => Whence::Start,
                'expectedData' => 'a',
            ],
            [
                'data' => $data,
                'offset' => -2,
                'whence' => Whence::Current,
                'expectedData' => 'b',
            ],
            [
                'data' => $data,
                'offset' => -1,
                'whence' => Whence::End,
                'expectedData' => 'c',
            ],
        ];
    }

    public function testSeekStreamNotSeekable(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be seekable.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->seek($this->fakerService->getCore()->randomInteger(), Whence::Start);
    }

    public function testTell(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $position = $stream->tell();

        $stream->close();

        $this->assertEquals(0, $position);
    }

    public function testTellStreamClosed(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be open.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->tell();
    }

    public function testEof(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->read();

        $eof = $stream->eof();

        $stream->close();

        $this->assertTrue($eof);
    }

    public function testEofStreamClosed(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be open.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->eof();
    }

    public function testRewind(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->write($this->fakerService->getCore()->randomString());

        $stream->rewind();

        $position = $stream->tell();

        $stream->close();

        $this->assertEquals(0, $position);
    }

    public function testRewindStreamClosed(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be open.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->rewind();
    }

    public function testGetSize(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $size = $stream->getSize();

        $stream->close();

        $this->assertEquals(0, $size);
    }

    public function testGetSizeStreamClosed(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The stream must be open.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->getSize();
    }

    public function testClose(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $this->assertFalse($stream->isOpen());
    }

    public function testCloseStreamClosed(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $stream = new Stream($file, Mode::Write);

        $stream->close();

        $stream->close();

        $this->assertFalse($stream->isOpen());
    }
}
