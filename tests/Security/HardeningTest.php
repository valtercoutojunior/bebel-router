<?php

declare(strict_types=1);

namespace BebelRouter\Tests\Security;

use PHPUnit\Framework\TestCase;
use BebelRouter\ContainerInterface;
use BebelRouter\Exception\PayloadTooLargeException;
use BebelRouter\Exception\RouteNameConflictException;
use BebelRouter\Middleware\MiddlewareInterface;
use BebelRouter\Request;
use BebelRouter\Response;
use BebelRouter\Router;

final class HardeningTest extends TestCase
{
    public function testDoubleEncodedTraversalIsNeutralized(): void
    {
        $router = new Router();
        $router->get('/public/{file}', fn ($file) => 'leak');

        $res = $router->dispatch(new Request('GET', '/public/%252e%252e%252f%252e%252e%252fetc%252fpasswd'));

        $this->assertSame(404, $res->getStatusCode());
    }

    public function testControlCharactersAreNeutralized(): void
    {
        $router = new Router();
        $router->get('/foo', fn () => 'ok');

        $this->assertSame(404, $router->dispatch(new Request('GET', "/foo\n"))->getStatusCode());
        $this->assertSame(404, $router->dispatch(new Request('GET', '/foo%00'))->getStatusCode());
    }

    public function testPlusIsLiteralInPath(): void
    {
        $router = new Router();
        $router->get('/a+b', fn () => 'plus');

        $this->assertSame('plus', $router->dispatch(new Request('GET', '/a+b'))->getContent());
    }

    public function testStaticSegmentsAreEscaped(): void
    {
        $router = new Router();
        $router->get('/file.json', fn () => 'json');

        $this->assertSame('json', $router->dispatch(new Request('GET', '/file.json'))->getContent());
        $this->assertSame(404, $router->dispatch(new Request('GET', '/fileXjson'))->getStatusCode());
    }

    public function testInvalidWhereRegexIsRejectedAtRegistration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $router = new Router();
        $router->get('/x/{id}', fn ($id) => $id)->where('id', '[a-z');
    }

    public function testDuplicateRouteIsRejected(): void
    {
        $this->expectException(\BebelRouter\Exception\DuplicateRouteException::class);

        $router = new Router();
        $router->get('/same', fn () => 'first');
        $router->get('/same', fn () => 'second');
    }

    public function testGroupNameDoesNotRenamePreviousRoutes(): void
    {
        $router = new Router();
        $router->get('/before', fn () => 'before')->name('before');
        $router->group(['as' => 'admin.'], function (Router $group): void {
            $group->get('/after', fn () => 'after')->name('after');
        });

        $this->assertSame('/before', $router->route('before'));
        $this->assertSame('/after', $router->route('admin.after'));
    }

    public function testGroupNameConflictIsRejected(): void
    {
        $this->expectException(RouteNameConflictException::class);

        $router = new Router();
        $router->get('/one', fn () => 'one')->name('admin.after');
        $router->group(['as' => 'admin.'], function (Router $group): void {
            $group->get('/two', fn () => 'two')->name('after');
        });
    }

    public function testDebugDoesNotLeakMessageOrStackTrace(): void
    {
        $router = new Router(true);
        $router->get('/boom', function (): string {
            throw new \RuntimeException('segredo-interno');
        });

        $res = $router->dispatch(new Request('GET', '/boom'));

        $this->assertSame(500, $res->getStatusCode());
        $this->assertStringNotContainsString('segredo-interno', $res->getContent());
        $this->assertStringNotContainsString('Stack trace', $res->getContent());
    }

    public function testUnknownMiddlewareAliasIsHandledSafely(): void
    {
        $router = new Router();
        $router->middleware(['does-not-exist']);
        $router->get('/x', fn () => 'ok');

        $res = $router->dispatch(new Request('GET', '/x'));

        $this->assertSame(500, $res->getStatusCode());
        $this->assertStringNotContainsString('does-not-exist', $res->getContent());
    }

    public function testMiddlewareIsResolvedFromContainer(): void
    {
        $router = new Router();
        $router->setContainer(new SimpleContainer([
            DepMiddleware::class => new DepMiddleware('di-ok'),
        ]));
        $router->aliasMiddleware('dep', DepMiddleware::class);
        $router->middleware(['dep']);
        $router->get('/di', fn () => 'no');

        $this->assertSame('di-ok', $router->dispatch(new Request('GET', '/di'))->getContent());
    }

    public function testResponsesIncludeSecurityHeaders(): void
    {
        $router = new Router();
        $router->get('/h', fn () => 'ok');
        $headers = $router->dispatch(new Request('GET', '/h'))->getHeaders();

        $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);
        $this->assertSame('DENY', $headers['X-Frame-Options'] ?? null);
        $this->assertSame('no-referrer', $headers['Referrer-Policy'] ?? null);
    }

    public function testRedirectRejectsDangerousScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::redirect('javascript:alert(1)');
    }

    public function testRedirectRejectsHeaderInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::redirect("/ok\r\nSet-Cookie: x=1");
    }

    public function testAbsoluteRedirectRequiresAllowedHosts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Response::redirect('https://evil.example/phishing');
    }

    public function testAbsoluteRedirectAllowsConfiguredHost(): void
    {
        $res = Response::redirect('https://trusted.example/ok', 302, [], ['trusted.example']);

        $this->assertSame('https://trusted.example/ok', $res->getHeaders()['Location'] ?? '');
    }

    public function testJsonEscapesHtmlContext(): void
    {
        $res = Response::json(['x' => '</script><b>']);

        $this->assertStringNotContainsString('</script>', $res->getContent());
        $this->assertStringContainsString('\\u003C', $res->getContent());
    }

    public function testFallbackIsNotReachableAsRealRoute(): void
    {
        $router = new Router();
        $router->fallback(fn () => Response::text('fb', 404));

        $this->assertCount(0, $router->getRoutes());
        $this->assertSame('fb', $router->dispatch(new Request('GET', '/nope'))->getContent());
        $this->assertSame(404, $router->dispatch(new Request('GET', '/__fallback__'))->getStatusCode());
    }

    public function testOverlongUriIsRejected(): void
    {
        $router = new Router();
        $router->get('/x', fn () => 'ok');

        $res = $router->dispatch(new Request('GET', '/' . str_repeat('a', 9000)));

        $this->assertSame(414, $res->getStatusCode());
    }

    public function testBodyLimitIsEnforced(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        new Request('GET', '/', [], [], str_repeat('a', Request::MAX_BODY_BYTES + 1));
    }

    public function testHeadFallsBackToGetWithoutBody(): void
    {
        $router = new Router();
        $router->get('/h', fn () => 'body');

        $res = $router->dispatch(new Request('HEAD', '/h'));

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('', $res->getContent());
    }

    public function testOptionsReturnsAllowHeader(): void
    {
        $router = new Router();
        $router->get('/o', fn () => 'ok');
        $router->post('/o', fn () => 'ok');

        $res = $router->dispatch(new Request('OPTIONS', '/o'));

        $this->assertSame(204, $res->getStatusCode());
        $this->assertSame('GET, HEAD, POST', $res->getHeaders()['Allow'] ?? '');
    }

    public function testUrlGeneratorEncodesParameters(): void
    {
        $router = new Router();
        $router->get('/u/{id}', fn ($id) => $id)->name('u');

        $this->assertSame('/u/a%20b', $router->route('u', ['id' => 'a b']));
        $this->assertSame('/u/a%2Fb', $router->route('u', ['id' => 'a/b']));
    }
}

final class DepMiddleware implements MiddlewareInterface
{
    public function __construct(private string $marker)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        return Response::text($this->marker);
    }
}

final class SimpleContainer implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(private array $services)
    {
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? null;
    }
}
