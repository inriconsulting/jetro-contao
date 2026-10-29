<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Fake;

use JetroTools\ContaoBundle\Storage\MediaStorageInterface;

final class FakeMediaStorage implements MediaStorageInterface
{
    /** @var array<string, string> uuid => Pfad */
    public array $files = [];

    public function store(string $id, string $extension, string $content): string
    {
        $uuid = random_bytes(16);
        $this->files[$uuid] = 'files/jetro/'.$id.'.'.$extension;

        return $uuid;
    }

    public function exists(string $uuid): bool
    {
        return isset($this->files[$uuid]);
    }
}
