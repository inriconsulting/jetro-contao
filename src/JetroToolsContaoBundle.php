<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class JetroToolsContaoBundle extends Bundle
{
    /** Wird bei jedem Release angehoben; Jetro prüft die Mindestversion (R3) */
    public const VERSION = '1.0.0';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
