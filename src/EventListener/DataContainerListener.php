<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Date;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DataContainerListener
{
    public function __construct(
        private readonly Connection $db,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    #[AsCallback(table: 'tl_jetro_connection', target: 'list.label.label')]
    public function connectionLabel(array $row): string
    {
        $calendar = $this->db->fetchOne('SELECT title FROM tl_calendar WHERE id = ?', [(int) $row['defaultCalendar']]);
        $status = '' === (string) $row['tokenHash']
            ? $this->t('status_none')
            : \sprintf($this->t('status_active'), Date::parse(Date::getNumericDatimFormat(), (int) $row['tokenCreated']));

        return \sprintf(
            '%s <span class="label-info">[%s]</span> <span class="tl_gray">%s: %s</span>',
            htmlspecialchars((string) $row['title']),
            htmlspecialchars($status),
            htmlspecialchars($this->t('default_calendar')),
            htmlspecialchars(false === $calendar ? '–' : (string) $calendar),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    #[AsCallback(table: 'tl_jetro_calendar', target: 'list.sorting.child_record')]
    public function mappingLabel(array $row): string
    {
        $calendar = $this->db->fetchOne('SELECT title FROM tl_calendar WHERE id = ?', [(int) $row['calendar']]);

        return \sprintf('<code>%s</code> → %s', htmlspecialchars((string) $row['jetroKey']), htmlspecialchars(false === $calendar ? '–' : (string) $calendar));
    }

    /**
     * @param array<string, mixed> $row
     */
    #[AsCallback(table: 'tl_jetro_log', target: 'list.label.label')]
    public function logLabel(array $row): string
    {
        return \sprintf(
            '%s · %s %s → %d%s',
            Date::parse(Date::getNumericDatimFormat(), (int) $row['tstamp']),
            htmlspecialchars((string) $row['method']),
            htmlspecialchars((string) $row['path']),
            (int) $row['status'],
            '' === (string) $row['code'] ? '' : ' <code>'.htmlspecialchars((string) $row['code']).'</code>',
        );
    }

    private function t(string $key): string
    {
        return $this->translator->trans('tl_jetro_connection.'.$key, [], 'contao_tl_jetro_connection');
    }
}
