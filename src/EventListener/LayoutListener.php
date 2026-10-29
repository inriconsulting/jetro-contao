<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Feld „Darstellung“ an der Terminliste: setzt Event-Template, Modul-Template und,
 * wenn noch keine gewählt ist, die empfohlene Bildgröße (V7 j).
 */
final class LayoutListener
{
    public const LAYOUTS = [
        'cards' => ['event_jetro_card', 'mod_eventlist_jetro_cards', ['440', '250', 'crop']],
        'large' => ['event_jetro_card_large', 'mod_eventlist_jetro_cards', ['680', '383', 'crop']],
        'compact' => ['event_jetro_card_compact', 'mod_eventlist_jetro_cards', null],
        'list' => ['event_jetro_list', 'mod_eventlist_jetro_list', ['176', '176', 'crop']],
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    #[AsCallback(table: 'tl_module', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        $row = $this->db->fetchAssociative('SELECT type, jetroLayout, cal_template, customTpl, imgSize FROM tl_module WHERE id = ?', [$dc->id]);

        if (false === $row || 'eventlist' !== $row['type']) {
            return;
        }

        $fields = self::fieldsFor((string) $row['jetroLayout'], $row);

        if ([] !== $fields) {
            $this->db->update('tl_module', $fields, ['id' => $dc->id]);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    public static function fieldsFor(string $layout, array $row): array
    {
        if (!isset(self::LAYOUTS[$layout])) {
            // Contao-Standard: nur eigene Vorlagen zurücksetzen, nie die einer anderen Erweiterung
            return str_starts_with((string) $row['cal_template'], 'event_jetro_')
                ? ['cal_template' => 'event_list', 'customTpl' => '']
                : [];
        }

        [$event, $module, $size] = self::LAYOUTS[$layout];

        // Mehrtägige Termine nur einmal: sonst erscheint eine Freizeit als Karte je Tag
        $fields = ['cal_template' => $event, 'customTpl' => $module, 'cal_noSpan' => '1'];
        $current = @unserialize((string) $row['imgSize']) ?: [];

        if (null !== $size && '' === implode('', array_map('strval', $current))) {
            $fields['imgSize'] = serialize($size);
        }

        return $fields;
    }
}
