<?php

declare(strict_types=1);

namespace App\EventSubscriber\Observability;

use App\Enum\User\ClientPlatform;
use App\Http\ClientHeaders;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * One line per 4xx response (#1391): they are kept out of Sentry errors, so their rate lives here. The 401s
 * the `auth` channel also logs are counted too: that channel diagnoses credentials, this one counts responses.
 */
final readonly class HttpClientErrorObservabilityListener
{
    private const string EVENT = 'http.client_error';

    // On the Request rather than on this service, so a FrankenPHP worker never carries them into the next request.
    private const string EXCEPTION_CLASS_ATTRIBUTE = '_http_client_error_exception_class';
    private const string EXCEPTION_MESSAGE_ATTRIBUTE = '_http_client_error_exception_message';
    // A token or an id: 16 characters with a digit, or 40 of anything. No segment naming a route is either.
    private const string TOKEN_SEGMENT_PATTERN = '#(?:(?=[^/]*\d)[^/]{16,}|[^/]{40,})#';

    public function __construct(
        #[Target('http')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Ahead of the listener that renders the error, since setting a response stops propagation.
     */
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 2048)]
    public function keepException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $event->getRequest()->attributes->add([
            self::EXCEPTION_CLASS_ATTRIBUTE => $exception::class,
            self::EXCEPTION_MESSAGE_ATTRIBUTE => $exception->getMessage(),
        ]);
    }

    /**
     * On the final response, because API Platform decides the status of a mapped exception.
     */
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -2048)]
    public function logClientError(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$response->isClientError()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $routeMatched = is_string($route) && $route !== '';
        $clientHeaders = ClientHeaders::of($request);
        $platform = $clientHeaders->signedInPlatform();
        $referer = $request->headers->get('Referer');

        $context = [
            'event' => self::EVENT,
            'status' => $response->getStatusCode(),
            'route' => $routeMatched ? $route : '',
            'method' => $request->getMethod(),
            // The router's own message quotes the full referer, which may carry a token.
            'message' => $routeMatched ? $request->attributes->get(self::EXCEPTION_MESSAGE_ATTRIBUTE) : null,
            // A class name, never the Throwable: LogsHandler drops a record whose `exception` is one.
            'exception_class' => $request->attributes->get(self::EXCEPTION_CLASS_ATTRIBUTE),
            'client_platform' => $platform === ClientPlatform::Web ? null : $platform->value,
            'client_version' => $clientHeaders->versionFor($platform),
            'referer_host' => $referer === null ? null : (parse_url($referer, PHP_URL_HOST) ?: null),
        ];

        // A matched route is named by `route`, so the tokens of its path (password reset, invitation) stay out.
        // A token route refusing the method has no `_route` either, hence the masking.
        if (!$routeMatched) {
            $context['path'] = preg_replace(self::TOKEN_SEGMENT_PATTERN, '{redacted}', $request->getPathInfo());
        }

        $this->logger->info(self::EVENT, $context);
    }
}
