<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Enum\BandSpace\BandSpaceModule;
use App\Mercure\MercureTopic;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Tells every active member that a module of their space changed (#1102), so a dashboard widget or a
 * mobile home tile refetches it.
 *
 * Writes are collected during the request and published once it has terminated, so a request that
 * writes ten activities still sends one signal per space and module, and nothing is sent for a write
 * that failed. Same shape as the chat signals: private, on each member's own topic (#963), and a tag
 * the client answers by refetching through the access-checked API.
 */
class BandSpaceChangeSignal implements ResetInterface
{
    /** @var array<string, array{band_space_id: string, module: BandSpaceModule}> */
    private array $pending = [];

    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
        private readonly BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    public function changed(BandSpace $bandSpace, BandSpaceModule $module): void
    {
        // The id now: a space hard deleted later in the request no longer has one.
        $bandSpaceId = (string) $bandSpace->id;
        $this->pending[$bandSpaceId . '|' . $module->value] = ['band_space_id' => $bandSpaceId, 'module' => $module];
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $event->getResponse()->isSuccessful() ? $this->publish() : $this->reset();
    }

    /**
     * Whatever the exit code: a command flushes as it goes, so one that failed on its tenth item has
     * still written the first nine.
     */
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    public function onConsoleTerminate(): void
    {
        $this->publish();
    }

    /**
     * Never throws: the write is committed by now, so an unreachable hub costs the live update only.
     */
    public function publish(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $change) {
            try {
                $topics = array_map(
                    MercureTopic::userNotifications(...),
                    $this->membershipRepository->findActiveUserIdsByBandSpaceId($change['band_space_id']),
                );
                if ($topics === []) {
                    continue;
                }

                $this->hub->publish(new Update($topics, json_encode([
                    'type' => 'band_space_changed',
                    'band_space_id' => $change['band_space_id'],
                    'module' => $change['module']->value,
                ], JSON_THROW_ON_ERROR), private: true));
            } catch (\Throwable $throwable) {
                $this->logger->error('Could not publish a band space change signal, its screens will update on their next load', [
                    'exception' => $throwable,
                    'band_space_id' => $change['band_space_id'],
                    'module' => $change['module']->value,
                ]);
            }
        }
    }

    public function reset(): void
    {
        $this->pending = [];
    }
}
