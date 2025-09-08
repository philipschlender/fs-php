<?php

namespace Tests;

use Fs\Enumerations\Mode;
use Fs\Exceptions\FsException;
use Fs\Models\Stream;
use Fs\Services\FsService;
use Fs\Services\FsServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class FsServiceTest extends FsTestCase
{
    protected FsServiceInterface $fsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fsService = new FsService();
    }

    #[DataProvider('dataProviderList')]
    public function testList(bool $recursive): void
    {
        $directory1 = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $file1 = sprintf('%s/%s', $directory1, $this->fakerService->getFs()->randomFile());
        $directory2 = sprintf('%s/%s', $directory1, $this->fakerService->getFs()->randomDirectory());
        $file2 = sprintf('%s/%s', $directory2, $this->fakerService->getFs()->randomFile());
        $directory3 = sprintf('%s/%s', $directory2, $this->fakerService->getFs()->randomDirectory());

        $this->fsService->makeDirectory($directory1);
        $this->fsService->writeFile($file1, $this->fakerService->getLorem()->randomText());
        $this->fsService->makeDirectory($directory2);
        $this->fsService->writeFile($file2, $this->fakerService->getLorem()->randomText());
        $this->fsService->makeDirectory($directory3);

        if (!$recursive) {
            $expectedPaths = [
                $file1,
                $directory2,
            ];
        } else {
            $expectedPaths = [
                $file1,
                $directory2,
                $file2,
                $directory3,
            ];
        }

        $offset = strlen($directory1) + 1;

        foreach ($expectedPaths as $key => $path) {
            $expectedPaths[$key] = substr($path, $offset);
        }

        $paths = iterator_to_array($this->fsService->list($directory1, $recursive));

        $this->assertCount(count($expectedPaths), $paths);
        $this->assertEqualsCanonicalizing($expectedPaths, $paths);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderList(): array
    {
        return [
            [
                'recursive' => false,
            ],
            [
                'recursive' => true,
            ],
        ];
    }

    public function testListPathMustBeDirectory(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must be a directory.', $directory));

        iterator_to_array($this->fsService->list($directory));
    }

    public function testIsDirectory(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $this->fsService->makeDirectory($directory);

        $isDirectory = $this->fsService->isDirectory($directory);

        $this->assertTrue($isDirectory);
    }

    public function testIsFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());

        $isFile = $this->fsService->isFile($file);

        $this->assertTrue($isFile);
    }

    public function testExistsDirectory(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $this->fsService->makeDirectory($directory);

        $exists = $this->fsService->exists($directory);

        $this->assertTrue($exists);
    }

    public function testExistsFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());

        $exists = $this->fsService->exists($file);

        $this->assertTrue($exists);
    }

    public function testMakeDirectory(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory(2));

        $expectedMode = 0770;

        $this->fsService->makeDirectory($directory, $expectedMode);

        $this->assertTrue($this->fsService->isDirectory($directory));
        $this->assertEquals($expectedMode, $this->fsService->getMode($directory));
    }

    public function testMakeDirectoryPathAlreadyExists(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s already exists.', $directory));

        $this->fsService->makeDirectory($directory);

        $this->fsService->makeDirectory($directory);
    }

    public function testReadFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $expectedData = $this->fakerService->getLorem()->randomText();

        $this->fsService->writeFile($file, $expectedData);

        $data = $this->fsService->readFile($file);

        $this->assertEquals($expectedData, $data);
    }

    public function testReadFilePathMustBeFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must be a file.', $file));

        $this->fsService->readFile($file);
    }

    public function testWriteFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $expectedData = $this->fakerService->getLorem()->randomText();

        $expectedMode = 0660;

        $this->fsService->writeFile($file, $expectedData, $expectedMode);

        $this->assertTrue($this->fsService->isFile($file));
        $this->assertEquals($expectedData, $this->fsService->readFile($file));
        $this->assertEquals($expectedMode, $this->fsService->getMode($file));
    }

    public function testWriteFilePathAlreadyExists(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s already exists.', $file));

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());
    }

    public function testWriteFilePathMustExist(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $file = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomFile());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must exist.', $directory));

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());
    }

    #[DataProvider('dataProviderTouchDirectory')]
    public function testTouchDirectory(?int $modificationTime = null, ?int $accessTime = null): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $this->fsService->makeDirectory($directory);

        $time = time();

        $this->fsService->touch($directory, $modificationTime, $accessTime);

        $this->assertTrue($this->fsService->isDirectory($directory));

        if (is_int($modificationTime)) {
            $this->assertEquals($modificationTime, filemtime($directory));
        } else {
            $this->assertGreaterThanOrEqual($time, filemtime($directory));
        }

        if (is_int($accessTime)) {
            $this->assertEquals($accessTime, fileatime($directory));
        } else {
            $this->assertGreaterThanOrEqual($time, fileatime($directory));
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderTouchDirectory(): array
    {
        return [
            [
                'modificationTime' => null,
                'accessTime' => null,
            ],
            [
                'modificationTime' => 0,
                'accessTime' => 0,
            ],
        ];
    }

    #[DataProvider('dataProviderTouchFile')]
    public function testTouchFile(?int $modificationTime = null, ?int $accessTime = null): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $time = time();

        $this->fsService->touch($file, $modificationTime, $accessTime);

        $this->assertTrue($this->fsService->isFile($file));

        if (is_int($modificationTime)) {
            $this->assertEquals($modificationTime, filemtime($file));
        } else {
            $this->assertGreaterThanOrEqual($time, filemtime($file));
        }

        if (is_int($accessTime)) {
            $this->assertEquals($accessTime, fileatime($file));
        } else {
            $this->assertGreaterThanOrEqual($time, fileatime($file));
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dataProviderTouchFile(): array
    {
        return [
            [
                'modificationTime' => null,
                'accessTime' => null,
            ],
            [
                'modificationTime' => 0,
                'accessTime' => 0,
            ],
        ];
    }

    public function testTouchInvalidModificationTime(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The modification time -1 must be greater than or equal to 0.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->touch($file, -1);
    }

    public function testTouchInvalidAccessTime(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The access time -1 must be greater than or equal to 0.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->touch($file, 0, -1);
    }

    public function testTouchInvalidModificationTimeAndAccessTime(): void
    {
        $this->expectException(FsException::class);
        $this->expectExceptionMessage('The modification time null must be greater than or equal to 0, when the access time 0 is greater than or equal to 0.');

        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->touch($file, null, 0);
    }

    #[DataProvider('dataProviderTouchPathMustExist')]
    public function testTouchPathMustExist(bool $isDirectory): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        if ($isDirectory) {
            $path = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $path = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must exist.', $directory));

        $this->fsService->touch($path);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderTouchPathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    public function testRemoveDirectory(): void
    {
        $directory1 = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $file1 = sprintf('%s/%s', $directory1, $this->fakerService->getFs()->randomFile());
        $directory2 = sprintf('%s/%s', $directory1, $this->fakerService->getFs()->randomDirectory());
        $file2 = sprintf('%s/%s', $directory2, $this->fakerService->getFs()->randomFile());
        $directory3 = sprintf('%s/%s', $directory2, $this->fakerService->getFs()->randomDirectory());

        $this->fsService->makeDirectory($directory1);
        $this->fsService->writeFile($file1, $this->fakerService->getLorem()->randomText());
        $this->fsService->makeDirectory($directory2);
        $this->fsService->writeFile($file2, $this->fakerService->getLorem()->randomText());
        $this->fsService->makeDirectory($directory3);

        $this->fsService->remove($directory1);

        $this->assertFalse($this->fsService->isDirectory($directory1));
        $this->assertFalse($this->fsService->isFile($file1));
        $this->assertFalse($this->fsService->isDirectory($directory2));
        $this->assertFalse($this->fsService->isFile($file2));
        $this->assertFalse($this->fsService->isDirectory($directory3));
    }

    public function testRemoveFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());

        $this->fsService->remove($file);

        $this->assertFalse($this->fsService->isFile($file));
    }

    #[DataProvider('dataProviderRemovePathMustExist')]
    public function testRemovePathMustExist(bool $isDirectory): void
    {
        if ($isDirectory) {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must exist.', $path));

        $this->fsService->remove($path);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderRemovePathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    public function testCopyDirectory(): void
    {
        $sourceDirectory1 = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $sourceFile1 = sprintf('%s/%s', $sourceDirectory1, $this->fakerService->getFs()->randomFile());
        $sourceDirectory2 = sprintf('%s/%s', $sourceDirectory1, $this->fakerService->getFs()->randomDirectory());
        $sourceFile2 = sprintf('%s/%s', $sourceDirectory2, $this->fakerService->getFs()->randomFile());
        $sourceDirectory3 = sprintf('%s/%s', $sourceDirectory2, $this->fakerService->getFs()->randomDirectory());

        $targetDirectory1 = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $targetFile1 = str_replace($sourceDirectory1, $targetDirectory1, $sourceFile1);
        $targetDirectory2 = str_replace($sourceDirectory1, $targetDirectory1, $sourceDirectory2);
        $targetFile2 = str_replace($sourceDirectory2, $targetDirectory2, $sourceFile2);
        $targetDirectory3 = str_replace($sourceDirectory2, $targetDirectory2, $sourceDirectory3);

        $this->fsService->makeDirectory($sourceDirectory1);
        $this->fsService->writeFile($sourceFile1, $this->fakerService->getLorem()->randomText());
        $this->fsService->makeDirectory($sourceDirectory2);
        $this->fsService->writeFile($sourceFile2, $this->fakerService->getLorem()->randomText());
        $this->fsService->makeDirectory($sourceDirectory3);

        $this->fsService->copy($sourceDirectory1, $targetDirectory1);

        $this->assertTrue($this->fsService->isDirectory($sourceDirectory1));
        $this->assertTrue($this->fsService->isFile($sourceFile1));
        $this->assertTrue($this->fsService->isDirectory($sourceDirectory2));
        $this->assertTrue($this->fsService->isFile($sourceFile2));
        $this->assertTrue($this->fsService->isDirectory($sourceDirectory3));
        $this->assertTrue($this->fsService->isDirectory($targetDirectory1));
        $this->assertTrue($this->fsService->isFile($targetFile1));
        $this->assertTrue($this->fsService->isDirectory($targetDirectory2));
        $this->assertTrue($this->fsService->isFile($targetFile2));
        $this->assertTrue($this->fsService->isDirectory($targetDirectory3));
        $this->assertEquals($this->fsService->getMode($sourceDirectory1), $this->fsService->getMode($targetDirectory1));
        $this->assertEquals($this->fsService->getMode($sourceFile1), $this->fsService->getMode($targetFile1));
        $this->assertEquals($this->fsService->getMode($sourceDirectory2), $this->fsService->getMode($targetDirectory2));
        $this->assertEquals($this->fsService->getMode($sourceFile2), $this->fsService->getMode($targetFile2));
        $this->assertEquals($this->fsService->getMode($sourceDirectory3), $this->fsService->getMode($targetDirectory3));
    }

    public function testCopyFile(): void
    {
        $sourceFile = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        $targetFile = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->writeFile($sourceFile, $this->fakerService->getLorem()->randomText());

        $this->fsService->copy($sourceFile, $targetFile);

        $this->assertTrue($this->fsService->isFile($sourceFile));
        $this->assertTrue($this->fsService->isFile($targetFile));
        $this->assertEquals($this->fsService->getMode($sourceFile), $this->fsService->getMode($targetFile));
    }

    #[DataProvider('dataProviderCopySourcePathMustExist')]
    public function testCopySourcePathMustExist(bool $isDirectory): void
    {
        if ($isDirectory) {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The source path %s must exist.', $sourcePath));

        $this->fsService->copy($sourcePath, $targetPath);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderCopySourcePathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderCopyTargetPathAlreadyExists')]
    public function testCopyTargetPathAlreadyExists(bool $isDirectory): void
    {
        if ($isDirectory) {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

            $this->fsService->makeDirectory($sourcePath);
            $this->fsService->makeDirectory($targetPath);
        } else {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

            $this->fsService->writeFile($sourcePath, $this->fakerService->getLorem()->randomText());
            $this->fsService->writeFile($targetPath, $this->fakerService->getLorem()->randomText());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The target path %s already exists.', $targetPath));

        $this->fsService->copy($sourcePath, $targetPath);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderCopyTargetPathAlreadyExists(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderCopyTargetPathMustExist')]
    public function testCopyTargetPathMustExist(bool $isDirectory): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        if ($isDirectory) {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
            $targetPath = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomDirectory());

            $this->fsService->makeDirectory($sourcePath);
        } else {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
            $targetPath = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomFile());

            $this->fsService->writeFile($sourcePath, $this->fakerService->getLorem()->randomText());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The target path %s must exist.', $directory));

        $this->fsService->copy($sourcePath, $targetPath);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderCopyTargetPathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    public function testMoveDirectory(): void
    {
        $sourceDirectory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $targetDirectory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $this->fsService->makeDirectory($sourceDirectory);

        $expectedMode = $this->fsService->getMode($sourceDirectory);

        $this->fsService->move($sourceDirectory, $targetDirectory);

        $this->assertFalse($this->fsService->isDirectory($sourceDirectory));
        $this->assertTrue($this->fsService->isDirectory($targetDirectory));
        $this->assertEquals($expectedMode, $this->fsService->getMode($targetDirectory));
    }

    public function testMoveFile(): void
    {
        $sourceFile = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        $targetFile = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->writeFile($sourceFile, $this->fakerService->getLorem()->randomText());

        $expectedMode = $this->fsService->getMode($sourceFile);

        $this->fsService->move($sourceFile, $targetFile);

        $this->assertFalse($this->fsService->isFile($sourceFile));
        $this->assertTrue($this->fsService->isFile($targetFile));
        $this->assertEquals($expectedMode, $this->fsService->getMode($targetFile));
    }

    #[DataProvider('dataProviderMoveSourcePathMustExist')]
    public function testMoveSourcePathMustExist(bool $isDirectory): void
    {
        if ($isDirectory) {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The source path %s must exist.', $sourcePath));

        $this->fsService->move($sourcePath, $targetPath);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderMoveSourcePathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderMoveTargetPathAlreadyExists')]
    public function testMoveTargetPathAlreadyExists(bool $isDirectory): void
    {
        if ($isDirectory) {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

            $this->fsService->makeDirectory($sourcePath);
            $this->fsService->makeDirectory($targetPath);
        } else {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
            $targetPath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

            $this->fsService->writeFile($sourcePath, $this->fakerService->getLorem()->randomText());
            $this->fsService->writeFile($targetPath, $this->fakerService->getLorem()->randomText());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The target path %s already exists.', $targetPath));

        $this->fsService->move($sourcePath, $targetPath);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderMoveTargetPathAlreadyExists(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderMoveTargetPathMustExist')]
    public function testMoveTargetPathMustExist(bool $isDirectory): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        if ($isDirectory) {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
            $targetPath = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomDirectory());

            $this->fsService->makeDirectory($sourcePath);
        } else {
            $sourcePath = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
            $targetPath = sprintf('%s/%s', $directory, $this->fakerService->getFs()->randomFile());

            $this->fsService->writeFile($sourcePath, $this->fakerService->getLorem()->randomText());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The target path %s must exist.', $directory));

        $this->fsService->move($sourcePath, $targetPath);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderMoveTargetPathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    public function testGetMimeContentType(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());

        $mimeContentType = $this->fsService->getMimeContentType($file);

        $this->assertEquals('text/plain', $mimeContentType);
    }

    public function testGetMimeContentTypePathMustBeFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must be a file.', $file));

        $this->fsService->getMimeContentType($file);
    }

    public function testGetModeOfDirectory(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $expectedMode = 0775;

        $this->fsService->makeDirectory($directory, $expectedMode);

        $mode = $this->fsService->getMode($directory);

        $this->assertEquals($expectedMode, $mode);
    }

    public function testGetModeOfFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $expectedMode = 0664;

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText(), $expectedMode);

        $mode = $this->fsService->getMode($file);

        $this->assertEquals($expectedMode, $mode);
    }

    #[DataProvider('dataProviderGetModePathMustExist')]
    public function testGetModePathMustExist(bool $isDirectory): void
    {
        if ($isDirectory) {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must exist.', $path));

        $this->fsService->getMode($path);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderGetModePathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    public function testChangeModeOfDirectory(): void
    {
        $directory = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());

        $expectedMode = 0770;

        $this->fsService->makeDirectory($directory, 0775);

        $this->fsService->changeMode($directory, $expectedMode);

        $this->assertEquals($expectedMode, $this->fsService->getMode($directory));
    }

    public function testChangeModeOfFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $expectedMode = 0660;

        $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText(), 0664);

        $this->fsService->changeMode($file, $expectedMode);

        $this->assertEquals($expectedMode, $this->fsService->getMode($file));
    }

    #[DataProvider('dataProviderChangeModePathMustExist')]
    public function testChangeModePathMustExist(bool $isDirectory): void
    {
        if ($isDirectory) {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must exist.', $path));

        $this->fsService->changeMode($path, 0777);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderChangeModePathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    public function testGetSizeOfDirectory(): void
    {
        $directory1 = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        $file1 = sprintf('%s/%s', $directory1, $this->fakerService->getFs()->randomFile());
        $directory2 = sprintf('%s/%s', $directory1, $this->fakerService->getFs()->randomDirectory());
        $file2 = sprintf('%s/%s', $directory2, $this->fakerService->getFs()->randomFile());
        $directory3 = sprintf('%s/%s', $directory2, $this->fakerService->getFs()->randomDirectory());

        $expectedSize = 32;

        $this->fsService->makeDirectory($directory1);
        $this->fsService->writeFile($file1, $this->fakerService->getCore()->randomString($expectedSize / 2));
        $this->fsService->makeDirectory($directory2);
        $this->fsService->writeFile($file2, $this->fakerService->getCore()->randomString($expectedSize / 2));
        $this->fsService->makeDirectory($directory3);

        $size = $this->fsService->getSize($directory1);

        $this->assertEquals($expectedSize, $size);
    }

    public function testGetSizeOfFile(): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        $expectedSize = 32;

        $this->fsService->writeFile($file, $this->fakerService->getCore()->randomString($expectedSize));

        $size = $this->fsService->getSize($file);

        $this->assertEquals($expectedSize, $size);
    }

    #[DataProvider('dataProviderGetSizePathMustExist')]
    public function testGetSizePathMustExist(bool $isDirectory): void
    {
        if ($isDirectory) {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomDirectory());
        } else {
            $path = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());
        }

        $this->expectException(FsException::class);
        $this->expectExceptionMessage(sprintf('The path %s must exist.', $path));

        $this->fsService->getSize($path);
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public static function dataProviderGetSizePathMustExist(): array
    {
        return [
            [
                'isDirectory' => true,
            ],
            [
                'isDirectory' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderOpenStream')]
    public function testOpenStream(Mode $mode): void
    {
        $file = sprintf('%s/%s', $this->directory, $this->fakerService->getFs()->randomFile());

        if (Mode::Read === $mode) {
            $this->fsService->writeFile($file, $this->fakerService->getLorem()->randomText());
        }

        $stream = $this->fsService->openStream($file, $mode);

        $stream->close();

        $this->assertInstanceOf(Stream::class, $stream);
    }

    /**
     * @return array<int,array<string,Mode>>
     */
    public static function dataProviderOpenStream(): array
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
}
