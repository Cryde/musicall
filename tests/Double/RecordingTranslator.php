<?php declare(strict_types=1);

namespace App\Tests\Double;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates through the real translator and keeps the locale each message was asked in, so a test
 * can prove whose locale a text was built for while only the French catalogue exists.
 */
final class RecordingTranslator implements TranslatorInterface
{
    /** @var list<array{id: string, locale: ?string}> */
    public array $calls = [];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->calls[] = ['id' => $id, 'locale' => $locale];

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
