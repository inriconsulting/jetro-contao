<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

final class PatchEventTest extends ApiTestCase
{
    public function testV3AbsageUndRuecknahme(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $response = $this->call('PATCH', '/events/'.self::EVENT_ID, ['cancelled' => true]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, (int) $this->event()['jetroCancelled']);
        $this->assertSame(1, (int) $this->event()['published'], 'bleibt veröffentlicht');

        $this->call('PATCH', '/events/'.self::EVENT_ID, ['cancelled' => false]);
        $this->assertSame(0, (int) $this->event()['jetroCancelled']);
    }

    public function testV2MitCancelledSetztAbsage(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(['cancelled' => true]));

        $this->assertSame(1, (int) $this->event()['jetroCancelled']);
    }

    public function testUnbekannt404(): void
    {
        $this->assertError($this->call('PATCH', '/events/'.self::EVENT_ID, ['cancelled' => true]), 404, 'not_found');
    }

    public function testOhneBool422(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertError($this->call('PATCH', '/events/'.self::EVENT_ID, ['cancelled' => 'ja']), 422, 'validation');
    }
}
