<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\Input;
use Contao\InputEncodingMode;

/**
 * Kodiert Text genau wie eine Backend-Eingabe ohne HTML (V7f).
 *
 * Bis Contao 5.7 kodiert Contao schon beim Speichern (Modus encodeAll samt Insert-Tag-Klammern),
 * damit auch ##Simple-Tokens## und {{Insert-Tags}} Text bleiben. Contao 6 speichert Eingaben roh
 * und kodiert erst bei der Ausgabe über Twig; eine beim Speichern kodierte Zeichenkette erschiene
 * dort doppelt kodiert. Darum entscheidet die Contao-Version über die Textspalten.
 *
 * Selbst erzeugtes HTML (teaser) wird in beiden Versionen kodiert: Contao reicht solche Felder
 * ungeprüft an den Browser weiter, kodierte Klammern bleiben dort sichtbarer Text.
 *
 * Damit {{Insert-Tags}} auch im rohen Text nie ausgeführt werden, stehen dort [{] und [}];
 * genau diese Schreibweise setzt Contao selbst beim Umstieg auf 6.0 ein (OutputEncodingMigration).
 */
final class TextEncoder
{
    private static bool|null $raw = null;

    /**
     * true = die Textspalten nehmen den rohen Text auf (Contao ab 6).
     */
    public static function storesRawText(): bool
    {
        return self::$raw ??= (int) ContaoCoreBundle::getVersion() >= 6;
    }

    /**
     * Wert für eine Textspalte (title, location, address).
     */
    public static function encode(string $text): string
    {
        if (self::storesRawText()) {
            // encodeNone prüft nur Zeichensatz, Zeilenumbrüche und Null-Bytes
            return str_replace(
                ['{{', '}}'],
                ['[{]', '[}]'],
                Input::encodeInput($text, InputEncodingMode::encodeNone, false),
            );
        }

        return self::html($text);
    }

    /**
     * Wert, der in selbst erzeugtes HTML eingesetzt wird.
     */
    public static function html(string $text): string
    {
        return Input::encodeInput($text, InputEncodingMode::encodeAll);
    }

    public static function paragraphs(string $text): string
    {
        $blocks = preg_split('/\R{2,}/', trim(str_replace("\r\n", "\n", $text))) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            if ('' !== trim($block)) {
                $html[] = '<p>'.implode('<br>', array_map(self::html(...), preg_split('/\R/', trim($block)) ?: [])).'</p>';
            }
        }

        return implode("\n", $html);
    }
}
