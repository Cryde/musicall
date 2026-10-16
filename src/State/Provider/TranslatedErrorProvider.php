<?php declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates the `detail` of every API error in the request locale, so a throw site only carries a key.
 * A message with no catalogue entry comes back unchanged, which keeps untranslated messages working.
 *
 * @implements ProviderInterface<object>
 */
#[AsDecorator('api_platform.state.error_provider')]
final readonly class TranslatedErrorProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $inner
     */
    public function __construct(
        #[AutowireDecorated]
        private ProviderInterface $inner,
        private TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $error = $this->inner->provide($operation, $uriVariables, $context);
        if (!$error instanceof Error) {
            return $error;
        }

        $detail = $error->getDetail();
        if ($detail === null) {
            return $error;
        }

        $request = $context['request'] ?? null;
        $exception = $request instanceof Request ? $request->attributes->get('exception') : null;

        // The inner provider replaces the message on a 5xx and on an expression-based access denial.
        $error->setDetail(
            $exception instanceof TranslatableInterface && $exception instanceof \Throwable && $exception->getMessage() === $detail
                ? $exception->trans($this->translator)
                : $this->translator->trans($detail)
        );

        return $error;
    }
}
