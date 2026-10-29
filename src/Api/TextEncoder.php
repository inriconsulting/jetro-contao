<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Api;

/**
 * Kodiert Text wie eine Backend-Eingabe (V7f): HTML-Sonderzeichen und Insert-Tag-Klammern.
 */
final class TextEncoder
{
    public static function encode(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return str_replace(['{', '}'], ['&#123;', '&#125;'], $text);
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
