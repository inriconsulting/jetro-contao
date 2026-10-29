<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

use Contao\Input;
use Contao\InputEncodingMode;

/**
 * Kodiert Text genau wie eine Backend-Eingabe ohne HTML (V7f): Contao-Modus encodeAll
 * samt Insert-Tag-Klammern, damit auch ##Simple-Tokens## und {{Insert-Tags}} Text bleiben.
 */
final class TextEncoder
{
    public static function encode(string $text): string
    {
        return Input::encodeInput($text, InputEncodingMode::encodeAll);
    }

    public static function paragraphs(string $text): string
    {
        $blocks = preg_split('/\R{2,}/', trim(str_replace("\r\n", "\n", $text))) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            if ('' !== trim($block)) {
                $html[] = '<p>'.implode('<br>', array_map(self::encode(...), preg_split('/\R/', trim($block)) ?: [])).'</p>';
            }
        }

        return implode("\n", $html);
    }
}
