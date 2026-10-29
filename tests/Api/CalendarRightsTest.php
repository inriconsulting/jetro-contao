<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use JetroTools\ContaoBundle\EventListener\DataContainerListener;
use PHPUnit\Framework\TestCase;

final class CalendarRightsTest extends TestCase
{
    private const ALL = [1 => 'Gottesdienste', 2 => 'Jugend', 3 => 'Senioren'];

    public function testRedakteurSiehtNurErlaubteKalender(): void
    {
        $this->assertSame([2 => 'Jugend'], DataContainerListener::allowedCalendars(self::ALL, false, ['2']));
    }

    public function testRedakteurOhneKalenderrechteSiehtNichts(): void
    {
        $this->assertSame([], DataContainerListener::allowedCalendars(self::ALL, false, null));
    }

    public function testAdminSiehtAlle(): void
    {
        $this->assertSame(self::ALL, DataContainerListener::allowedCalendars(self::ALL, true, []));
    }
}
