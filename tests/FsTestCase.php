<?php

namespace Tests;

abstract class FsTestCase extends TestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = 'tmp';

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $subPaths = $this->list($this->directory);

            foreach ($subPaths as $subPath) {
                $subPath = sprintf('%s/%s', $this->directory, $subPath);

                if (is_dir($subPath)) {
                    rmdir($subPath);
                } elseif (is_file($subPath)) {
                    unlink($subPath);
                }
            }

            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * @return array<int,string>
     */
    protected function list(string $path): array
    {
        $iterator = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        $offset = strlen($path) + 1;

        $paths = [];

        /** @var \SplFileInfo $splFileInfo */
        foreach ($iterator as $splFileInfo) {
            if (
                str_ends_with($splFileInfo->getPathname(), '/.')
                || str_ends_with($splFileInfo->getPathname(), '/..')
            ) {
                continue;
            }

            $paths[] = substr($splFileInfo->getPathname(), $offset);
        }

        return $paths;
    }
}
