<?php

declare(strict_types=1);

// Vertrag gegen ein echtes Contao: Route, Session, Dateiverwaltung, Slug, Sprache, Spaltenlängen.

[, $app, $base] = $argv;
$url = parse_url((string) getenv('DATABASE_URL'));
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $url['host'], $url['port'] ?? 3306, ltrim($url['path'], '/')), $url['user'], $url['pass'] ?? '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$token = 'jt_'.bin2hex(random_bytes(32));
$pdo->exec("INSERT INTO tl_calendar (tstamp, title) VALUES (UNIX_TIMESTAMP(), 'Integration')");
$calendar = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO tl_jetro_connection (tstamp, title, tokenHash, defaultCalendar) VALUES (UNIX_TIMESTAMP(), ?, ?, ?)')->execute(['Integration', hash('sha256', $token), $calendar]);

$failures = 0;
$check = static function (bool $ok, string $what) use (&$failures): void {
    echo ($ok ? 'ok   ' : 'FEHL ').$what.PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$request = static function (string $method, string $path, array|null $body = null) use ($base, $token): array {
    $ch = curl_init($base.'/api/jetro/v1'.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ['X-Jetro-Token: '.$token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
    ]);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    return [$status, substr($raw, 0, $size), json_decode(substr($raw, $size), true)];
};

$fixture = static fn (string $name): array => json_decode((string) file_get_contents(__DIR__.'/../Fixtures/jetro/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
$eventId = '0192f3a4-5b6c-7d8e-9f01-23456789abcd';
$event = $fixture('v2-event');

[$status, $headers, $body] = $request('GET', '/');
$v1 = $fixture('v1-index');
// Paketversion wächst mit jedem Release, Name und api bleiben wie in der Fixture
$check(200 === $status && $v1['name'] === $body['name'] && $v1['api'] === $body['api'] && preg_match('/^\d+\.\d+\.\d+$/', (string) $body['version']), 'V1 über die echte Route');
$check(false === stripos($headers, 'set-cookie'), 'keine Session, kein Cookie');

[$status] = $request('PUT', '/media/'.$event['imageId'], $fixture('v4-media'));
$check(201 === $status, 'V4 legt Bild an');
$file = $pdo->query("SELECT path FROM tl_files WHERE path LIKE 'files/jetro/%'")->fetchColumn();
$check(false !== $file && is_file($app.'/'.$file), 'Bild in der Dateiverwaltung und auf der Platte');
$check(is_file($app.'/files/jetro/.public'), 'Ordner files/jetro öffentlich');

[$status] = $request('PUT', '/events/'.$eventId, $event);
$check(201 === $status, 'V2 mit Bild legt Termin an');
$row = $pdo->query("SELECT * FROM tl_calendar_events WHERE jetroId = '$eventId'")->fetch(PDO::FETCH_ASSOC);
$check(1 === (int) $row['addImage'] && 16 === strlen((string) $row['singleSRC']), 'Termin verweist auf das Bild');
$check('gottesdienst-0192f3a4' === $row['alias'], 'Alias aus dem Contao-Slug: '.$row['alias']);
$check(str_contains((string) $row['teaser'], 'jetro-signup'), 'Anmeldelink mit Beschriftung aus der Sprachdatei');

[$status] = $request('PUT', '/events/0192f3a4-5b6c-7d8e-9f01-000000000001', ['title' => str_repeat('Wort ', 51)] + $event);
$check(201 === $status, 'langer Titel: Alias passt in die Spalte');

[$status, , $body] = $request('PUT', '/events/0192f3a4-5b6c-7d8e-9f01-000000000002', ['title' => str_repeat('a', 254).'#'] + $event);
$check(422 === $status && 'title' === ($body['field'] ?? null), 'kodiert zu langer Titel: 422 statt 500');

foreach (['title', 'location', 'address', 'alias'] as $column) {
    $length = (int) $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tl_calendar_events' AND COLUMN_NAME = '$column'")->fetchColumn();
    $check($length >= 255, "Spalte $column fasst 255 (ist $length)");
}

[$status] = $request('PATCH', '/events/'.$eventId, $fixture('v3-patch'));
$check(200 === $status, 'V3 über die echte Route');

echo $failures ? "$failures Fehler\n" : "alles ok\n";
exit($failures ? 1 : 0);
