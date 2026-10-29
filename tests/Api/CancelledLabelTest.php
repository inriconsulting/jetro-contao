<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use JetroTools\ContaoBundle\EventListener\CancelledEventListener;
use PHPUnit\Framework\TestCase;

final class CancelledLabelTest extends TestCase
{
    public function testAbgesagterTerminZeigtHinweisEinmal(): void
    {
        $data = ['title' => 'Gottesdienst', 'link' => 'Gottesdienst', 'class' => ' foo', 'jetroCancelled' => 1];

        $data = CancelledEventListener::apply($data, 'Abgesagt');
        $data = CancelledEventListener::apply($data, 'Abgesagt');

        $this->assertSame('Abgesagt: Gottesdienst', $data['title']);
        $this->assertSame('Abgesagt: Gottesdienst', $data['link']);
        // event_full hängt class ohne Leerzeichen an: das führende Leerzeichen muss bleiben
        $this->assertSame(' foo jetro-cancelled', $data['class']);
    }

    public function testLeereKlasseBekommtFuehrendesLeerzeichen(): void
    {
        $data = CancelledEventListener::apply(['title' => 'X', 'class' => '', 'jetroCancelled' => 1], 'Abgesagt');

        $this->assertSame(' jetro-cancelled', $data['class']);
    }

    public function testNichtAbgesagterTerminBleibt(): void
    {
        $data = ['title' => 'Gottesdienst', 'jetroCancelled' => 0];

        $this->assertSame($data, CancelledEventListener::apply($data, 'Abgesagt'));
    }
}
