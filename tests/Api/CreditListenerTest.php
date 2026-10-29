<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use JetroTools\ContaoBundle\EventListener\CreditListener;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class CreditListenerTest extends ApiTestCase
{
    private const LIST = '<div class="mod_eventlist block"><div class="event">x</div></div>';

    public function testZeileMitLinkNurAufJetroUndNofollow(): void
    {
        $html = $this->render([['jetroConnection' => 1]]);

        $this->assertStringContainsString('<p class="jetro-credit">Termine aus <a href="https://jetro.tools" target="_blank" rel="nofollow noopener">Jetro</a></p></div>', $html);
    }

    public function testVerbindungMitAbgeschalteterZeileZeigtNichts(): void
    {
        $this->db->update('tl_jetro_connection', ['showCredit' => 0], ['id' => 1]);

        $this->assertSame(self::LIST, $this->render([['jetroConnection' => 1]]));
    }

    public function testOhneJetroTermineKeineZeile(): void
    {
        $this->assertSame(self::LIST, $this->render([['jetroConnection' => 0]]));
    }

    public function testAndereModuleBleibenUnberuehrt(): void
    {
        $listener = $this->listener();
        $listener->onGetAllEvents($this->events([['jetroConnection' => 1]]), [], 0, 0, $this->module(7));

        $this->assertSame('<nav>x</nav>', $listener->appendCredit('<nav>x</nav>', 'mod_navigation', 7));
    }

    public function testResetVergisstZustandZwischenAnfragen(): void
    {
        $listener = $this->listener();
        $listener->onGetAllEvents($this->events([['jetroConnection' => 1]]), [], 0, 0, $this->module(5));
        $listener->reset();

        $this->assertSame(self::LIST, $listener->appendCredit(self::LIST, 'mod_eventlist', 5));
    }

    /**
     * @param list<array<string, int>> $events
     */
    private function render(array $events): string
    {
        $listener = $this->listener();
        $listener->onGetAllEvents($this->events($events), [], 0, 0, $this->module(5));

        return $listener->appendCredit(self::LIST, 'mod_eventlist', 5);
    }

    private function listener(): CreditListener
    {
        return new CreditListener($this->db, new class() implements TranslatorInterface {
            use TranslatorTrait;

            public function trans(string|null $id, array $parameters = [], string|null $domain = null, string|null $locale = null): string
            {
                return 'MSC.jetro_credit' === $id ? 'Termine aus %s' : (string) $id;
            }
        });
    }

    /**
     * @param list<array<string, int>> $events
     *
     * @return array<int, array<int, list<array<string, int>>>>
     */
    private function events(array $events): array
    {
        return [20261101 => [1793520000 => $events]];
    }

    private function module(int $id): object
    {
        return (object) ['id' => $id];
    }
}
