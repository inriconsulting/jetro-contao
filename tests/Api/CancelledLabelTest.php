<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use JetroTools\ContaoBundle\EventListener\CancelledEventListener;
use PHPUnit\Framework\TestCase;

final class CancelledLabelTest extends TestCase
{
    public function testAbgesagterTerminZeigtHinweisEinmal(): void
    {
        $data = ['title' => 'Gottesdienst', 'link' => 'Gottesdienst', 'class' => 'event', 'jetroCancelled' => 1];

        $data = CancelledEventListener::apply($data, 'Abgesagt');
        $data = CancelledEventListener::apply($data, 'Abgesagt');

        $this->assertSame('Abgesagt: Gottesdienst', $data['title']);
        $this->assertSame('Abgesagt: Gottesdienst', $data['link']);
        $this->assertSame('event jetro-cancelled', $data['class']);
    }

    public function testNichtAbgesagterTerminBleibt(): void
    {
        $data = ['title' => 'Gottesdienst', 'jetroCancelled' => 0];

        $this->assertSame($data, CancelledEventListener::apply($data, 'Abgesagt'));
    }
}
