<?php

declare(strict_types=1);

// Darstellungen im echten Contao: Seite mit Terminliste anlegen, abrufen und das Markup prüfen (V7 j, k).
// Erwartet die Daten aus smoke.php (Kalender mit einem Jetro-Termin samt Bild).

[, $app, $base] = $argv;
$url = parse_url((string) getenv('DATABASE_URL'));
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $url['host'], $url['port'] ?? 3306, ltrim($url['path'], '/')), $url['user'], $url['pass'] ?? '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$failures = 0;
$check = static function (bool $ok, string $what) use (&$failures): void {
    echo ($ok ? 'ok   ' : 'FEHL ').$what.PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$insert = static function (string $table, array $row) use ($pdo): int {
    $columns = implode(', ', array_map(static fn ($c): string => "`$c`", array_keys($row)));
    $pdo->prepare("INSERT INTO $table ($columns) VALUES (".implode(', ', array_fill(0, \count($row), '?')).')')->execute(array_values($row));

    return (int) $pdo->lastInsertId();
};

$calendar = (int) $pdo->query('SELECT defaultCalendar FROM tl_jetro_connection ORDER BY id LIMIT 1')->fetchColumn();
$now = time();

$theme = $insert('tl_theme', ['tstamp' => $now, 'name' => 'Jetro-Test']);
$layout = $insert('tl_layout', [
    'pid' => $theme,
    'tstamp' => $now,
    'name' => 'Jetro-Test',
    'rows' => '2rw',
    'cols' => '1cl',
    'template' => 'fe_page',
    'modules' => serialize([['mod' => 0, 'col' => 'main', 'enable' => '1']]),
]);

$root = $insert('tl_page', [
    'tstamp' => $now,
    'title' => 'Jetro-Test',
    'alias' => 'jetro-test',
    'type' => 'root',
    'language' => 'de',
    'fallback' => 1,
    'includeLayout' => 1,
    'layout' => $layout,
    'useSSL' => 0,
    'published' => 1,
]);
$page = $insert('tl_page', [
    'pid' => $root,
    'tstamp' => $now,
    'title' => 'Termine',
    'alias' => 'termine',
    'type' => 'regular',
    'published' => 1,
]);
$pdo->prepare('UPDATE tl_calendar SET jumpTo = ? WHERE id = ?')->execute([$page, $calendar]);

$article = $insert('tl_article', ['pid' => $page, 'tstamp' => $now, 'title' => 'Termine', 'inColumn' => 'main', 'published' => 1]);

/**
 * Legt ein Terminlisten-Modul samt Inhaltselement an und gibt die Seite zurück.
 */
$render = static function (string $layoutKey, array $fields) use ($insert, $pdo, $theme, $article, $calendar, $now, $base): string {
    $module = $insert('tl_module', [
        'pid' => $theme,
        'tstamp' => $now,
        'name' => 'Termine '.$layoutKey,
        'type' => 'eventlist',
        'cal_calendar' => serialize([$calendar]),
        'cal_format' => 'cal_all',
        'cal_order' => 'ascending',
        'numberOfItems' => 6,
        'jetroLayout' => $layoutKey,
        ...$fields,
    ]);
    $content = $insert('tl_content', ['pid' => $article, 'ptable' => 'tl_article', 'tstamp' => $now, 'type' => 'module', 'module' => $module, 'invisible' => 0]);

    $html = (string) file_get_contents($base.'/termine');

    $pdo->prepare('DELETE FROM tl_content WHERE id = ?')->execute([$content]);
    $pdo->prepare('DELETE FROM tl_module WHERE id = ?')->execute([$module]);

    return $html;
};

$layouts = [
    'cards' => ['event_jetro_card', 'mod_eventlist_jetro_cards', serialize(['440', '250', 'crop'])],
    'large' => ['event_jetro_card_large', 'mod_eventlist_jetro_cards', serialize(['680', '383', 'crop'])],
    'compact' => ['event_jetro_card_compact', 'mod_eventlist_jetro_cards', ''],
    'list' => ['event_jetro_list', 'mod_eventlist_jetro_list', serialize(['176', '176', 'crop'])],
];

foreach ($layouts as $key => [$event, $module, $size]) {
    $html = $render($key, ['cal_template' => $event, 'customTpl' => $module, 'cal_noSpan' => '1', 'imgSize' => $size]);
    $wrapper = 'list' === $key ? '<ul class="jetro-list">' : 'class="jetro-cards"';

    $check(str_contains($html, $wrapper), "$key: Vorlage des Moduls gerendert");
    $check(str_contains($html, 'Gottesdienst'), "$key: Titel des Termins");
    // Kompakte Karten zeigen bewusst nur Titel und Datum
    $check('compact' === $key || str_contains($html, 'Gemeindehaus'), "$key: Ort des Termins");
    // Contao fasst Stilvorlagen und Skripte in /assets zusammen, der Dateiname bleibt im Pfad
    $check(str_contains($html, 'jetro-cards.css'), "$key: Stilvorlage eingebunden");
    $check(str_contains($html, 'class="jetro-credit"'), "$key: Zeile „Termine aus Jetro“");
    // Weder Twig-Platzhalter noch doppelt kodierter Text dürfen durchschlagen
    $check(!str_contains($html, 'jetroSheet') && !str_contains($html, '{{') && !str_contains($html, '&amp;#'), "$key: keine Reste von Vorlage oder Kodierung");

    if ('compact' === $key) {
        $check(str_contains($html, 'jetro-sheet__day'), "$key: Kalenderblatt statt Bild");
    } else {
        $check(1 === preg_match('/jetro-(card|list)__media">\s*<img\s/', $html), "$key: Bild aus Jetro");
    }

    if ('cards' === $key) {
        $check(str_contains($html, 'jetro-cards.js'), "$key: Skript eingebunden");
        $check(str_contains($html, 'jetro-cards__track'), "$key: Slider-Spur");
    }
}

// Abgesagter Termin: Hinweis in der Liste (V7e)
$pdo->exec('UPDATE tl_calendar_events SET jetroCancelled = 1');
$html = $render('list', ['cal_template' => 'event_jetro_list', 'customTpl' => 'mod_eventlist_jetro_list', 'cal_noSpan' => '1']);
$check(str_contains($html, 'jetro-cancelled') && str_contains($html, 'Abgesagt'), 'Abgesagt in der Terminliste');
$pdo->exec('UPDATE tl_calendar_events SET jetroCancelled = 0');

echo $failures ? "$failures Fehler\n" : "alles ok\n";
exit($failures ? 1 : 0);
