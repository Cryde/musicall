<?php

declare(strict_types=1);

namespace App\Tests\Double;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The Vite tag functions without a front end build, which the PHP suite never has in CI: each renders nothing,
 * so a test can render the page shell (base.html.twig). Same names as the bundle's EntryFilesTwigExtension.
 */
final class StubViteEntryFilesTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite_entry_script_tags', static fn (): string => '', ['is_safe' => ['html']]),
            new TwigFunction('vite_entry_link_tags', static fn (): string => '', ['is_safe' => ['html']]),
            new TwigFunction('vite_mode', static fn (): ?string => null),
        ];
    }
}
