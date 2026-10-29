<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Zeile „Termine aus Jetro“ unter Terminlisten mit Jetro-Terminen (V7k); je Verbindung abschaltbar.
 * Link nur auf „Jetro“ mit nofollow: verteilte Widget-Links wertet Google sonst als Link-Spam.
 */
final class CreditListener implements ResetInterface
{
    /** @var array<int, true> Module, die Jetro-Termine mit Zeile zeigen */
    private array $modules = [];

    /** @var array<int, bool>|null */
    private array|null $enabled = null;

    public function __construct(
        private readonly Connection $db,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<int, array<int, list<array<string, mixed>>>> $events
     * @param array<mixed>                                     $calendars
     *
     * @return array<int, array<int, list<array<string, mixed>>>>
     */
    #[AsHook('getAllEvents')]
    public function onGetAllEvents(array $events, array $calendars, int $start, int $end, object $module): array
    {
        foreach ($events as $times) {
            foreach ($times as $list) {
                foreach ($list as $event) {
                    if ($this->isEnabled((int) ($event['jetroConnection'] ?? 0))) {
                        $this->modules[(int) $module->id] = true;

                        return $events;
                    }
                }
            }
        }

        return $events;
    }

    #[AsHook('parseFrontendTemplate')]
    public function onParseFrontendTemplate(string $buffer, string $templateName, object $template): string
    {
        return $this->appendCredit($buffer, $templateName, (int) ($template->id ?? 0));
    }

    public function appendCredit(string $buffer, string $templateName, int $moduleId): string
    {
        if (!str_starts_with($templateName, 'mod_eventlist') || !isset($this->modules[$moduleId])) {
            return $buffer;
        }

        $GLOBALS['TL_CSS']['jetro_credit'] = 'bundles/jetrotoolscontao/jetro-credit.css|static';

        $line = '<p class="jetro-credit">'.\sprintf(
            htmlspecialchars($this->translator->trans('MSC.jetro_credit', [], 'contao_default')),
            '<a href="https://jetro.tools" target="_blank" rel="nofollow noopener">Jetro</a>',
        ).'</p>';

        // In den Wrapper des Moduls, damit die Zeile zur Liste gehört
        $pos = strrpos($buffer, '</div>');

        return false === $pos ? $buffer.$line : substr_replace($buffer, $line.'</div>', $pos, 6);
    }

    /**
     * Zustand gilt nur für eine Anfrage; Symfony ruft reset() zwischen Anfragen (Worker-Betrieb).
     */
    public function reset(): void
    {
        $this->modules = [];
        $this->enabled = null;
    }

    private function isEnabled(int $connectionId): bool
    {
        if ($connectionId < 1) {
            return false;
        }

        $this->enabled ??= array_map(
            static fn ($value): bool => (bool) $value,
            $this->db->fetchAllKeyValue('SELECT id, showCredit FROM tl_jetro_connection'),
        );

        return $this->enabled[$connectionId] ?? false;
    }
}
