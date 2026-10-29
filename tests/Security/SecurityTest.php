<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Security;

use PHPUnit\Framework\TestCase;
use BebelRouter\Request;
use BebelRouter\Router;

final class SecurityTest extends TestCase
{
    public function testPathTraversalBasic(): void
    {
        $router = new Router();
        $router->get('/public/{file}', function ($file) {
            return $file;
        });
        $req = new Request('GET', '/public/..%2F..%2Fetc%2Fpasswd');
        $res = $router->dispatch($req);
        $this->assertNotEquals(200, $res->getStatusCode());
    }

    public function testMethodSpoofingOnlyPost(): void
    {
        $router = new Router();
        $router->enableMethodSpoofing(true);
        $router->delete('/x', function () {
            return 'ok';
        });
        $req = new Request('GET', '/x', [], [], '', ['_method' => 'DELETE']);
        $res = $router->dispatch($req);
        $this->assertEquals(405, $res->getStatusCode());
    }

    public function testNonPublicMethodBlocked(): void
    {
        $router = new Router();
        $router->get('/test', [SecureController::class, 'secret']);
        $res = $router->dispatch(new Request('GET', '/test'));
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testIpIgnoresForwardedHeaderFromUntrustedPeer(): void
    {
        $request = new Request('GET', '/', [], ['X-Forwarded-For' => '1.2.3.4'], '', [], ['REMOTE_ADDR' => '203.0.113.9']);
        $this->assertSame('203.0.113.9', $request->getIp(['10.0.0.1']));
    }

    public function testIpIgnoresForwardedHeaderWithoutTrustedProxies(): void
    {
        $request = new Request('GET', '/', [], ['X-Forwarded-For' => '1.2.3.4'], '', [], ['REMOTE_ADDR' => '203.0.113.9']);
        $this->assertSame('203.0.113.9', $request->getIp());
    }

    public function testIpUsesForwardedHeaderFromTrustedProxy(): void
    {
        $request = new Request('GET', '/', [], ['X-Forwarded-For' => '198.51.100.7, 10.0.0.2'], '', [], ['REMOTE_ADDR' => '10.0.0.1']);
        $this->assertSame('198.51.100.7', $request->getIp(['10.0.0.1', '10.0.0.2']));
    }

    public function testIpIgnoresSpoofedEntriesPrependedToForwardedHeader(): void
    {
        $request = new Request('GET', '/', [], ['X-Forwarded-For' => '1.2.3.4, 9.9.9.9, 10.0.0.2'], '', [], ['REMOTE_ADDR' => '10.0.0.1']);
        $this->assertSame('9.9.9.9', $request->getIp(['10.0.0.1', '10.0.0.2']));
    }

    public function testIpAcceptsTrustedProxyCidrRanges(): void
    {
        $request = new Request('GET', '/', [], ['X-Forwarded-For' => '198.51.100.7, 10.0.0.2'], '', [], ['REMOTE_ADDR' => '10.0.0.1']);
        $this->assertSame('198.51.100.7', $request->getIp(['10.0.0.0/24']));
    }
}

final class SecureController
{
    /** @phpstan-ignore-next-line Intencionalmente privado: chamado via string pelo Router. */
    private function secret(): string
    {
        return 'no';
    }
}
