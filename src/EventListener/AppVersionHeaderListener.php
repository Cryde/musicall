<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Names the deployed front end build on every response, so a tab still running an older one learns
 * of the deploy from its ordinary API calls (#1105). The id is the one Vite baked into that build.
 *
 * Read once per process: a deploy restarts the workers, and a build in dev touches a watched file.
 * No file, as in the PHP CI job, which builds no front end, means no header.
 */
#[AsEventListener(event: 'kernel.response')]
final class AppVersionHeaderListener
{
    public const string HEADER = 'X-App-Version';

    private const string VERSION_PATTERN = '/^[A-Za-z0-9._-]{1,64}\z/';

    private ?string $version = null;

    private bool $isVersionRead = false;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/build/version.json')]
        private readonly string $versionFile,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $version = $this->deployedVersion();
        if ($version !== null) {
            $event->getResponse()->headers->set(self::HEADER, $version);
        }
    }

    private function deployedVersion(): ?string
    {
        if (!$this->isVersionRead) {
            $this->version = $this->readVersionFile();
            $this->isVersionRead = true;
        }

        return $this->version;
    }

    private function readVersionFile(): ?string
    {
        if (!is_file($this->versionFile)) {
            return null;
        }
        $content = file_get_contents($this->versionFile);
        if ($content === false) {
            return null;
        }
        $decoded = json_decode($content, true);
        $version = is_array($decoded) ? ($decoded['version'] ?? null) : null;

        return is_string($version) && preg_match(self::VERSION_PATTERN, $version) === 1 ? $version : null;
    }
}
