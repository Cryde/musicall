<?php declare(strict_types=1);

namespace App\Exception;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The trans() of an exception whose message is a translation key with parameters, for a class that
 * also declares `implements TranslatableInterface`.
 */
trait TranslatableExceptionTrait
{
    /**
     * @return array<string, string>
     */
    abstract public function getParameters(): array;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getMessage(), $this->getParameters(), null, $locale);
    }
}
