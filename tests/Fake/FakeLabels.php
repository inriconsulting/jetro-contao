<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Fake;

use JetroTools\ContaoBundle\Api\LabelProviderInterface;

final class FakeLabels implements LabelProviderInterface
{
    public function label(string $key, int $calendarId): string
    {
        return ['livestream' => 'Livestream', 'signup' => 'Zur Anmeldung'][$key];
    }
}
