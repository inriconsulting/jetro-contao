<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Storage;

interface MediaStorageInterface
{
    /**
     * Legt files/jetro/<id>.<ext> an, gleicht die Dateiverwaltung ab und gibt die UUID (binär) zurück.
     */
    public function store(string $id, string $extension, string $content): string;

    public function exists(string $uuid): bool;
}
