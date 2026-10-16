<?php declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * An HTTP error whose message is a translation key that needs parameters. A key without parameters needs
 * no special class: any exception carrying it is translated by TranslatedErrorProvider.
 */
final class TranslatableHttpException extends HttpException implements TranslatableInterface
{
    use TranslatableExceptionTrait;

    /**
     * @param array<string, string> $parameters strings, the simple rule: a `{n, number}` or a plural `#`
     *                                          would group the digits of an int (1 234), sprintf never did
     * @param array<string, string> $headers
     */
    public function __construct(
        int $statusCode,
        string $messageKey,
        private readonly array $parameters = [],
        ?\Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $messageKey, $previous, $headers);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
