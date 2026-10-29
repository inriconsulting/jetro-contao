<?php

declare(strict_types=1);

namespace JetroTools\ContaoBundle\Tests\Api;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use JetroTools\ContaoBundle\Backend\TokenAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class TokenActionTest extends TestCase
{
    private Connection $db;
    private RequestStack $stack;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement("CREATE TABLE tl_jetro_connection (id INTEGER PRIMARY KEY, tstamp INTEGER DEFAULT 0, title TEXT DEFAULT '', tokenHash TEXT DEFAULT '', tokenCreated INTEGER DEFAULT 0)");
        $this->db->insert('tl_jetro_connection', ['id' => 7, 'title' => 'Jetro', 'tokenHash' => hash('sha256', 'alt')]);
        $this->stack = new RequestStack();
    }

    public function testGetZeigtNurDieRueckfrageUndAendertNichts(): void
    {
        $html = $this->action('GET');

        $this->assertStringContainsString('<form method="post">', $html);
        $this->assertStringContainsString('name="REQUEST_TOKEN" value="rt"', $html);
        $this->assertSame(hash('sha256', 'alt'), $this->hash());
    }

    public function testPostErzeugtTokenZeigtEsEinmalUndSpeichertNurDenHash(): void
    {
        $html = $this->action('POST', ['FORM_SUBMIT' => 'jetro_token']);

        $this->assertMatchesRegularExpression('/value="(jt_[0-9a-f]{64})"/', $html);
        preg_match('/value="(jt_[0-9a-f]{64})"/', $html, $m);
        $this->assertSame(hash('sha256', $m[1]), $this->hash());
        $this->assertStringNotContainsString($m[1], json_encode($this->db->fetchAllAssociative('SELECT * FROM tl_jetro_connection'), JSON_THROW_ON_ERROR));

        // Beim nächsten Aufruf ist das Token nicht mehr zu sehen
        $this->assertStringNotContainsString($m[1], $this->action('GET'));
    }

    public function testPostOhneFormularkennungAendertNichts(): void
    {
        $this->action('POST', ['FORM_SUBMIT' => 'anderes']);

        $this->assertSame(hash('sha256', 'alt'), $this->hash());
    }

    public function testWiderrufLeertDenHash(): void
    {
        $this->action('POST', ['FORM_SUBMIT' => 'jetro_revoke'], 'revoke');

        $this->assertSame('', $this->hash());
    }

    /**
     * @param array<string, string> $post
     */
    private function action(string $method, array $post = [], string $action = 'generate'): string
    {
        while ($this->stack->pop()) {
        }

        $this->stack->push(Request::create('/contao?do=jetro&key=token&id=7', $method, $post));

        $csrf = $this->createMock(ContaoCsrfTokenManager::class);
        $csrf->method('getDefaultTokenValue')->willReturn('rt');
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/contao?do=jetro');

        $translator = new class() implements TranslatorInterface {
            use TranslatorTrait;
        };

        return (new TokenAction($this->db, $this->stack, $router, $translator, $csrf))->{$action}();
    }

    private function hash(): string
    {
        return (string) $this->db->fetchOne('SELECT tokenHash FROM tl_jetro_connection WHERE id = 7');
    }
}
