<?php

namespace App\Files;

use App\Entity\StoredFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/** File contents on disk, under DATA_DIR/files, named by an opaque key (never by the user's file name). */
class FileStorage
{
    private readonly Filesystem $fs;

    public function __construct(
        #[Autowire(env: 'resolve:DATA_DIR')] private readonly string $dataDir,
    ) {
        $this->fs = new Filesystem();
    }

    public function path(StoredFile $file): string
    {
        return rtrim($this->dataDir, '/').'/files/'.$file->getStorageKey();
    }

    public function store(StoredFile $file, File $content): void
    {
        $path = $this->path($file);
        $this->fs->mkdir(\dirname($path), 0o775);
        $content->move(\dirname($path), basename($path));
    }

    public function delete(StoredFile $file): void
    {
        $this->fs->remove($this->path($file));
    }
}
