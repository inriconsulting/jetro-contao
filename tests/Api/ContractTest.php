<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prüft das Paket gegen die Vertrags-Fixtures aus Jetro (tests/Fixtures/jetro).
 */
final class ContractTest extends ApiTestCase
{
    /** In api 1 nicht erzeugt: 410 kommt erst, wenn /v1 abgeschaltet wird (Vertrag) */
    private const RESERVED = ['gone'];

    public function testV1EntsprichtFixture(): void
    {
        $this->assertSame(self::jetroFixture('v1-index'), $this->json($this->call('GET', '/')));
    }

    public function testV2MitBildBrauchtZuerstV4(): void
    {
        $event = self::jetroFixture('v2-event');
        $media = self::jetroFixture('v4-media');

        $this->assertError($this->call('PUT', '/events/'.self::EVENT_ID, $event), 409, 'image_missing');

        $this->assertSame(201, $this->call('PUT', '/media/'.$event['imageId'], $media)->getStatusCode());
        $this->assertSame(200, $this->call('PUT', '/media/'.$event['imageId'], $media)->getStatusCode());
        $this->assertSame(201, $this->call('PUT', '/events/'.self::EVENT_ID, $event)->getStatusCode());
        $this->assertSame(1, (int) $this->event()['addImage']);
        $this->assertSame(200, $this->call('PUT', '/events/'.self::EVENT_ID, $event)->getStatusCode());
    }

    public function testV2GanztaegigEntsprichtFixture(): void
    {
        $this->assertSame(201, $this->call('PUT', '/events/'.self::EVENT_ID, self::jetroFixture('v2-event-ganztags'))->getStatusCode());

        $row = $this->event();
        $this->assertSame(0, (int) $row['addTime']);
        $this->assertSame(mktime(0, 0, 0, 11, 6, 2026), (int) $row['startDate']);
        $this->assertSame(mktime(0, 0, 0, 11, 8, 2026), (int) $row['endDate']);
        $this->assertSame(mktime(23, 59, 59, 11, 8, 2026), (int) $row['endTime']);
        $this->assertSame('', $row['location']);
    }

    public function testV3EntsprichtFixture(): void
    {
        $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());

        $this->assertSame(200, $this->call('PATCH', '/events/'.self::EVENT_ID, self::jetroFixture('v3-patch'))->getStatusCode());
        $this->assertSame(1, (int) $this->event()['jetroCancelled']);
    }

    /**
     * @return iterable<string, array{array{status: int, code: string, field?: string}}>
     */
    public static function fehler(): iterable
    {
        foreach (self::jetroFixture('v6-fehler') as $entry) {
            yield $entry['status'].' '.$entry['code'] => [$entry];
        }
    }

    /**
     * @param array{status: int, code: string, field?: string} $expected
     */
    #[DataProvider('fehler')]
    public function testV6JederFehlerDerFixtureWirdErzeugt(array $expected): void
    {
        if (\in_array($expected['code'], self::RESERVED, true)) {
            $this->assertSame(410, $expected['status']);

            return;
        }

        $response = $this->scenario($expected['code']);

        $this->assertError($response, $expected['status'], $expected['code']);

        if (isset($expected['field'])) {
            $this->assertSame($expected['field'], $this->json($response)['field']);
        }
    }

    private function scenario(string $code): Response
    {
        $png = self::jetroFixture('v4-media');

        return match ($code) {
            'invalid_json' => $this->call('PUT', '/events/'.self::EVENT_ID, '{kaputt'),
            'auth_rejected' => $this->call('GET', '/', null, []),
            'not_found' => $this->call('PATCH', '/events/'.self::EVENT_ID, self::jetroFixture('v3-patch')),
            'image_missing' => $this->call('PUT', '/events/'.self::EVENT_ID, self::jetroFixture('v2-event')),
            'foreign' => (function (): Response {
                $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture(), ['HTTP_X_JETRO_TOKEN' => self::OTHER_TOKEN]);

                return $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
            })(),
            'file_too_large' => $this->call('PUT', '/media/'.self::EVENT_ID, ['contentBase64' => base64_encode(str_repeat("\0", 2 * 1024 * 1024 + 1))] + $png),
            'file_type_rejected' => $this->call('PUT', '/media/'.self::EVENT_ID, ['contentBase64' => base64_encode('kein Bild')] + $png),
            'validation' => (function (): Response {
                $this->db->update('tl_jetro_connection', ['defaultCalendar' => 0], ['id' => 1]);

                return $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
            })(),
            'internal' => (function (): Response {
                $this->db->executeStatement('DROP TABLE tl_calendar_events');

                return $this->call('PUT', '/events/'.self::EVENT_ID, $this->fixture());
            })(),
            default => throw new \LogicException('Kein Szenario für Fehlercode '.$code.' – Fixture aus Jetro erweitert?'),
        };
    }
}
