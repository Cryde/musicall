<?php

declare(strict_types=1);

namespace App\State\Provider\App;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\App\AppConfig;
use App\ApiResource\App\PlatformBuilds;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<AppConfig>
 */
readonly class AppConfigProvider implements ProviderInterface
{
    /** Nobody is ever blocked by default. */
    private const int FIRST_BUILD = 1;

    public function __construct(
        #[Autowire('%app.android.min_supported_build%')]
        private string $androidMinSupportedBuild,
        #[Autowire('%app.android.latest_build%')]
        private string $androidLatestBuild,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AppConfig
    {
        $minimum = self::build($this->androidMinSupportedBuild);

        // Latest is never below the minimum, or the app would be told it is up to date and blocked at once.
        return new AppConfig(new PlatformBuilds($minimum, max($minimum, self::build($this->androidLatestBuild))));
    }

    /**
     * Read leniently, since it is typed by hand into the server's `.env.local`: anything that is not a
     * positive whole number falls back to the first build rather than failing, so a typo can disable
     * the check but never lock anyone out.
     */
    private static function build(string $value): int
    {
        $value = trim($value);

        // Nine digits at most, so a slip of the keyboard cannot become PHP_INT_MAX and block everyone.
        return ctype_digit($value) && strlen($value) <= 9 && (int) $value >= self::FIRST_BUILD ? (int) $value : self::FIRST_BUILD;
    }
}
