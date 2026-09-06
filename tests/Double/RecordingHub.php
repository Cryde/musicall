<?php declare(strict_types=1);

namespace App\Tests\Double;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * Stands in for the Mercure hub and keeps the Update it was handed, so a test can assert on the
 * topic and the private flag rather than on a return value that says nothing.
 *
 * Hand written rather than a mock because the interesting assertion is about an object that was
 * built, which reads better recorded than trapped inside an expectation callback. It also means no
 * test needs any Mercure wiring, so the suite can never reach a real hub by accident.
 */
final class RecordingHub implements HubInterface
{
    public ?Update $published = null;

    public function publish(Update $update): string
    {
        $this->published = $update;

        return 'urn:uuid:00000000-0000-0000-0000-000000000000';
    }

    public function getPublicUrl(): string
    {
        return '/.well-known/mercure';
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return ProtocolVersion::Legacy;
    }

    public function getCookieName(): string
    {
        return 'mercureAuthorization';
    }
}
