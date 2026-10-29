<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

final class IndexTest extends ApiTestCase
{
    public function testV1MeldetNameVersionUndApi(): void
    {
        $response = $this->call('GET', '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['name' => 'jetro-contao', 'version' => '1.0.0', 'api' => 1], $this->json($response));
    }

    public function testNurKopfXJetroTokenGenuegt(): void
    {
        $response = $this->call('GET', '/', null, ['HTTP_X_JETRO_TOKEN' => self::TOKEN]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNurKopfAuthorizationGenuegt(): void
    {
        $response = $this->call('GET', '/', null, ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOhneOderMitFalschemTokenAuthRejected(): void
    {
        $this->assertError($this->call('GET', '/', null, []), 401, 'auth_rejected');
        $this->assertError($this->call('GET', '/', null, ['HTTP_X_JETRO_TOKEN' => self::TOKEN.'x']), 401, 'auth_rejected');
    }

    public function testWiderrufenesTokenAuthRejected(): void
    {
        $this->db->update('tl_jetro_connection', ['tokenHash' => ''], ['id' => 1]);

        $this->assertError($this->call('GET', '/'), 401, 'auth_rejected');
    }

    public function testUnbekannterPfadNotFoundAlsJson(): void
    {
        $this->assertError($this->call('GET', '/gibt-es-nicht'), 404, 'not_found');
        $this->assertError($this->call('PUT', '/events/keine-uuid', $this->fixture()), 404, 'not_found');
    }

    public function testProtokollOhneTokenUndHoechstens50EintraegeJeVerbindung(): void
    {
        for ($i = 0; $i < 55; ++$i) {
            $this->call('GET', '/');
        }

        $rows = $this->db->fetchAllAssociative('SELECT * FROM tl_jetro_log');
        $this->assertCount(50, $rows);
        $this->assertSame('GET', $rows[0]['method']);
        $this->assertSame('/api/jetro/v1/', $rows[0]['path']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function testAbgewieseneAnfragenVerdraengenKeineEchten(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->call('GET', '/');
        }

        for ($i = 0; $i < 60; ++$i) {
            $this->call('GET', '/', null, []);
        }

        $this->assertSame(5, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_jetro_log WHERE pid = 1'));
        $this->assertSame(10, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_jetro_log WHERE pid = 0'));
    }
}
