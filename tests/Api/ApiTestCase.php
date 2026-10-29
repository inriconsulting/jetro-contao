<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use JetroTools\ContaoBundle\Api\ApiController;
use JetroTools\ContaoBundle\Api\EventWriter;
use JetroTools\ContaoBundle\Api\PayloadValidator;
use JetroTools\ContaoBundle\Storage\ConnectionRepository;
use JetroTools\ContaoBundle\Storage\MediaRepository;
use JetroTools\ContaoBundle\Storage\RequestLog;
use JetroTools\ContaoBundle\Tests\Fake\FakeLabels;
use JetroTools\ContaoBundle\Tests\Fake\FakeMediaStorage;
use JetroTools\ContaoBundle\Tests\Fake\FakeSlugger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends TestCase
{
    protected const TOKEN = 'jt_test_token_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    protected const OTHER_TOKEN = 'jt_other_token_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    protected const EVENT_ID = '0192f3a4-5b6c-7d8e-9f01-23456789abcd';
    protected const NOW = 1798761600; // 2027-01-01 00:00 UTC, liegt nach dem Fixture-Termin

    protected Connection $db;
    protected FakeMediaStorage $media;
    protected int $now = self::NOW;
    private ApiController $controller;

    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Berlin');

        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ([
            'CREATE TABLE tl_calendar (id INTEGER PRIMARY KEY, title TEXT, jumpTo INTEGER DEFAULT 0)',
            "CREATE TABLE tl_calendar_events (id INTEGER PRIMARY KEY, pid INTEGER, tstamp INTEGER DEFAULT 0, title TEXT DEFAULT '', alias TEXT DEFAULT '', addTime INTEGER DEFAULT 0, startTime INTEGER DEFAULT 0, endTime INTEGER DEFAULT 0, startDate INTEGER DEFAULT 0, endDate INTEGER NULL, location TEXT DEFAULT '', address TEXT NULL, teaser TEXT NULL, addImage INTEGER DEFAULT 0, singleSRC BLOB NULL, published INTEGER DEFAULT 0, featured INTEGER DEFAULT 0, jetroConnection INTEGER DEFAULT 0, jetroId TEXT NULL UNIQUE, jetroCancelled INTEGER DEFAULT 0)",
            'CREATE TABLE tl_content (id INTEGER PRIMARY KEY, pid INTEGER, ptable TEXT)',
            'CREATE TABLE tl_calendar_events_member (id INTEGER PRIMARY KEY, pid INTEGER)',
            "CREATE TABLE tl_jetro_connection (id INTEGER PRIMARY KEY, tstamp INTEGER DEFAULT 0, title TEXT DEFAULT '', tokenHash TEXT DEFAULT '', tokenCreated INTEGER DEFAULT 0, defaultCalendar INTEGER DEFAULT 0, showCredit INTEGER DEFAULT 1)",
            "CREATE TABLE tl_jetro_calendar (id INTEGER PRIMARY KEY, pid INTEGER, tstamp INTEGER DEFAULT 0, jetroKey TEXT DEFAULT '', calendar INTEGER DEFAULT 0)",
            "CREATE TABLE tl_jetro_media (id INTEGER PRIMARY KEY, pid INTEGER, tstamp INTEGER DEFAULT 0, jetroId TEXT NULL UNIQUE, uuid BLOB NULL)",
            "CREATE TABLE tl_jetro_log (id INTEGER PRIMARY KEY, pid INTEGER DEFAULT 0, tstamp INTEGER DEFAULT 0, method TEXT DEFAULT '', path TEXT DEFAULT '', status INTEGER DEFAULT 0, code TEXT DEFAULT '')",
        ] as $sql) {
            $this->db->executeStatement($sql);
        }

        $this->db->insert('tl_calendar', ['id' => 1, 'title' => 'Termine']);
        $this->db->insert('tl_calendar', ['id' => 2, 'title' => 'Gottesdienste']);
        $this->db->insert('tl_jetro_connection', ['id' => 1, 'title' => 'Jetro', 'tokenHash' => hash('sha256', self::TOKEN), 'defaultCalendar' => 1]);
        $this->db->insert('tl_jetro_connection', ['id' => 2, 'title' => 'Andere', 'tokenHash' => hash('sha256', self::OTHER_TOKEN), 'defaultCalendar' => 1]);

        $this->media = new FakeMediaStorage();
        $connections = new ConnectionRepository($this->db);

        $this->controller = new ApiController(
            $connections,
            new EventWriter($this->db, $connections, new MediaRepository($this->db, $this->media), new FakeSlugger(), new FakeLabels(), fn () => $this->now),
            new MediaRepository($this->db, $this->media),
            new PayloadValidator(),
            new RequestLog($this->db),
            new NullLogger(),
            '1.0.0',
        );
    }

    /**
     * @param array<string, string> $headers
     */
    protected function call(string $method, string $path, array|string|null $body = null, array|null $headers = null): Response
    {
        $headers ??= ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'HTTP_X_JETRO_TOKEN' => self::TOKEN];
        $content = \is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body;
        $request = Request::create('/api/jetro/v1'.$path, $method, [], [], [], $headers + ['CONTENT_TYPE' => 'application/json'], $content);

        return $this->controller->handle($request, ltrim($path, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<mixed>
     */
    protected static function jetroFixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/jetro/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * v2-event aus Jetro ohne Bild; die Bildfälle prüfen eigene Tests mit v4-media.
     *
     * @return array<string, mixed>
     */
    protected function fixture(array $override = []): array
    {
        return array_replace(self::jetroFixture('v2-event'), ['imageId' => null], $override);
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function event(string $id = self::EVENT_ID): array|false
    {
        return $this->db->fetchAssociative('SELECT * FROM tl_calendar_events WHERE jetroId = ?', [$id]);
    }

    protected function assertError(Response $response, int $status, string $code): void
    {
        $this->assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame($code, $this->json($response)['code']);
        $this->assertStringNotContainsString('#0 ', (string) $response->getContent());
    }
}
