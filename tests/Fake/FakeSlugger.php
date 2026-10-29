<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Fake;

use JetroTools\ContaoBundle\Api\SluggerInterface;

final class FakeSlugger implements SluggerInterface
{
    public function slug(string $text, int $calendarId): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }
}
