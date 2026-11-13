<?php

declare(strict_types=1);

// Backend „Jetro“ im echten Contao: Listen, Beschriftungen und Token erzeugen (V7a, V7h).
// Erwartet den Benutzer aus run.sh und die Daten aus smoke.php.

[, $app, $base] = $argv;
$jar = sys_get_temp_dir().'/jetro-backend-cookies.txt';
@unlink($jar);

$url = parse_url((string) getenv('DATABASE_URL'));
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $url['host'], $url['port'] ?? 3306, ltrim($url['path'], '/')), $url['user'], $url['pass'] ?? '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Eine Kalender-Zuordnung, damit die Unterliste eine Zeile zum Beschriften hat
$pdo->exec("INSERT INTO tl_jetro_calendar (pid, tstamp, jetroKey, calendar) SELECT id, UNIX_TIMESTAMP(), 'gottesdienst', defaultCalendar FROM tl_jetro_connection ORDER BY id LIMIT 1");

$failures = 0;
$check = static function (bool $ok, string $what) use (&$failures): void {
    echo ($ok ? 'ok   ' : 'FEHL ').$what.PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$call = static function (string $path, array|null $fields = null) use ($base, $jar): string {
    $ch = curl_init($base.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if (null !== $fields) {
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            // Symfony prüft den Anfrage-Token auch über die Herkunft der Anfrage
            CURLOPT_HTTPHEADER => ['Origin: '.$base, 'Referer: '.$base.$path],
        ]);
    }

    return (string) curl_exec($ch);
};

/** Versteckte Felder eines Formulars (Anfrage-Token, FORM_SUBMIT, _target_path). */
$hidden = static function (string $html): array {
    preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER);
    $fields = [];

    foreach ($matches as $match) {
        $fields[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $fields;
};

$html = $call('/contao/login');
$html = $call('/contao/login', $hidden($html) + ['username' => 'jetro-test', 'password' => 'jetro-integration-1']);
$check(str_contains($html, 'do=jetro'), 'Anmeldung im Backend');

$html = $call('/contao?do=jetro');
$check(str_contains($html, 'Standard-Kalender: Integration'), 'Verbindungen: Beschriftung mit Standard-Kalender');
$check(str_contains($html, '<span class="label-info">'), 'Verbindungen: HTML der Beschriftung bleibt HTML');

$html = $call('/contao?do=jetro&table=tl_jetro_calendar&id=1');
$check(1 === preg_match('#<code>gottesdienst</code> → Integration#', $html), 'Zuordnungen: Beschriftung je Zeile');

$html = $call('/contao?do=jetro&table=tl_jetro_log');
$check(str_contains($html, '/api/jetro/v1/events/'), 'Protokoll: Anfragen von Jetro');
$check(!preg_match('/jt_[0-9a-f]{32}/', $html), 'Protokoll: kein Token sichtbar');

// Token erzeugen: GET fragt zurück, POST leitet weiter, danach ist das Token genau einmal zu sehen
$html = $call('/contao?do=jetro&key=token&id=1');
$check(str_contains($html, 'name="FORM_SUBMIT" value="jetro_token"'), 'Token: Rückfrage mit Formular');
$html = $call('/contao?do=jetro&key=token&id=1', $hidden($html));
$check(1 === preg_match('/value="(jt_[0-9a-f]{64})"/', $html, $token), 'Token: nach der Weiterleitung sichtbar');
$check(isset($token[1]) && !str_contains($call('/contao?do=jetro&key=token&id=1'), $token[1]), 'Token: nur einmal sichtbar');

echo $failures ? "$failures Fehler\n" : "alles ok\n";
exit($failures ? 1 : 0);
