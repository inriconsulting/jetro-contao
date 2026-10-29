<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

interface LabelProviderInterface
{
    /**
     * @param 'livestream'|'signup' $key
     */
    public function label(string $key, int $calendarId): string;
}
