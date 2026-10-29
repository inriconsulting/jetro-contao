<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

interface SluggerInterface
{
    public function slug(string $text, int $calendarId): string;
}
