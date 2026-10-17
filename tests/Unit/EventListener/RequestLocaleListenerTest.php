<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\RequestLocaleListener;
use App\Locale\LocalizedPath;
use App\Locale\RequestLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Pins the listener's wiring: the resolved locale reaches both the request and the translator.
 */
class RequestLocaleListenerTest extends TestCase
{
    private string $phpDefaultLocale;
    private TokenStorage $tokenStorage;
    private LocaleSwitcher $localeSwitcher;
    private RequestLocaleListener $listener;

    protected function setUp(): void
    {
        // LocaleSwitcher::setLocale() also moves PHP's default locale, which must not leak into other tests.
        $this->phpDefaultLocale = \Locale::getDefault();
        $this->tokenStorage = new TokenStorage();
        $container = new Container();
        $container->set('security.token_storage', $this->tokenStorage);
        $this->localeSwitcher = new LocaleSwitcher('fr', []);
        $this->listener = new RequestLocaleListener(
            new RequestLocaleResolver(['fr', 'en'], 'fr', new LocalizedPath(['fr', 'en'], 'fr')),
            $this->localeSwitcher,
            new Security($container),
        );
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->phpDefaultLocale);
    }

    public function test_the_resolved_locale_reaches_the_request_and_the_locale_switcher(): void
    {
        $request = Request::create('/', cookies: [RequestLocaleResolver::COOKIE => 'en']);

        ($this->listener)($this->event($request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $this->localeSwitcher->getLocale());
    }

    public function test_the_authenticated_user_locale_is_used(): void
    {
        $user = new User();
        $user->locale = 'en';
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'api', ['ROLE_USER']));
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'fr');

        ($this->listener)($this->event($request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $this->localeSwitcher->getLocale());
    }

    public function test_a_sub_request_is_left_alone(): void
    {
        $request = Request::create('/', cookies: [RequestLocaleResolver::COOKIE => 'en']);

        ($this->listener)($this->event($request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame('fr', $this->localeSwitcher->getLocale());
    }

    private function event(Request $request, int $requestType): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
    }
}
