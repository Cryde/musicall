<?php declare(strict_types=1);

namespace App\Tests\Double;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * A translator over the project's real `messages` catalogues, for a unit test that must prove the
 * wording a reader gets without booting the kernel.
 */
final class CatalogueTranslator
{
    private const string DOMAIN = 'messages+intl-icu';

    public static function in(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['fr', 'en'] as $catalogueLocale) {
            $translator->addResource('yaml', sprintf('%s/../../translations/%s.%s.yaml', __DIR__, self::DOMAIN, $catalogueLocale), $catalogueLocale, self::DOMAIN);
        }

        return $translator;
    }
}
