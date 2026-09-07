<?php declare(strict_types=1);

namespace App\Tests\Double;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * Stands in for the Mercure hub and keeps every Update it was handed, so a test can assert on the
 * topics and the private flag rather than on a return value that says nothing.
 *
 * Hand written rather than a mock because the interesting assertion is about objects that were
 * built, which read better recorded than trapped inside an expectation callback.
 *
 * It is also registered for the whole suite in config/services.yaml under when@test, and that is not
 * a convenience: NotificationCreator publishes on every notification, fifteen source files call it,
 * and without this every one of them would try an HTTP POST to a host that does not resolve.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

    public function publish(Update $update): string
    {
        $this->updates[] = $update;

        return 'urn:uuid:00000000-0000-0000-0000-000000000000';
    }

    /**
     * Every topic published on, in order, flattened across updates. One entry per topic rather than
     * per publish, since one update carries the whole recipient list.
     *
     * @return list<string>
     */
    public function publishedTopics(): array
    {
        return array_merge(...array_map(static fn (Update $update): array => $update->getTopics(), $this->updates));
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
