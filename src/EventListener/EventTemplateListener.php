<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Date;
use Contao\Template;

/**
 * Versorgt die Jetro-Vorlagen mit dem, was Twig nicht selbst ermitteln kann:
 * Tag und Monat für das Kalenderblatt und die Stilvorlagen der Darstellungen.
 */
#[AsHook('parseTemplate')]
final class EventTemplateListener
{
    private const ASSETS = [
        'mod_eventlist_jetro_cards' => ['jetro-cards.css', 'jetro-cards.js'],
        'mod_eventlist_jetro_list' => ['jetro-cards.css', null],
    ];

    public function __invoke(Template $template): void
    {
        $name = $template->getName();

        if (str_starts_with($name, 'event_jetro_')) {
            $this->addSheet($template);

            return;
        }

        if (null !== $assets = self::ASSETS[$name] ?? null) {
            [$css, $js] = $assets;
            $GLOBALS['TL_CSS']['jetro_cards'] = 'bundles/jetrotoolscontao/'.$css.'|static';

            if (null !== $js) {
                $GLOBALS['TL_JAVASCRIPT']['jetro_cards'] = 'bundles/jetrotoolscontao/'.$js.'|static';
            }
        }
    }

    /**
     * Kalenderblatt mit Tag und Monat des Termins, auch bei Serienterminen (begin).
     */
    private function addSheet(Template $template): void
    {
        $data = $template->getData();
        // begin gilt je Wiederholung eines Serientermins, startDate für den Termin selbst
        $timestamp = (int) ($data['begin'] ?? 0);

        if ($timestamp < 1) {
            $timestamp = (int) ($data['startDate'] ?? 0);
        }

        if ($timestamp < 1) {
            return;
        }

        $template->setData([
            'jetroSheetDay' => Date::parse('d', $timestamp),
            'jetroSheetMonth' => Date::parse('M', $timestamp),
        ] + $data);
    }
}
