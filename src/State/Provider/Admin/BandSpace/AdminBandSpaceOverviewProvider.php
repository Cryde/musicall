<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\BandSpace\AdminBandSpaceModuleUsage;
use App\ApiResource\Admin\BandSpace\AdminBandSpaceOverview;
use App\ApiResource\Admin\BandSpace\AdminBandSpaceStorage;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\Message\MessageRepository;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;

/**
 * @implements ProviderInterface<AdminBandSpaceOverview>
 */
readonly class AdminBandSpaceOverviewProvider implements ProviderInterface
{
    private const int ACTIVE_WINDOW_DAYS = 30;
    private const int TOP_STORAGE = 10;

    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceActivityRepository $bandSpaceActivityRepository,
        private BandSpaceFileVersionRepository $bandSpaceFileVersionRepository,
        private MessageRepository $messageRepository,
        private BandSpaceFileQuotaService $quotaService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminBandSpaceOverview
    {
        /** @var array{from: string, to: string} $filters */
        $filters = $context['filters'];
        $from = new \DateTimeImmutable($filters['from']);
        // +1 day so the end date is inclusive, as the dashboard reads it.
        $to = (new \DateTimeImmutable($filters['to']))->modify('+1 day');

        $overview = new AdminBandSpaceOverview();
        $overview->from = $filters['from'];
        $overview->to = $filters['to'];
        $overview->spaces = $this->bandSpaceRepository->countLive();
        $overview->pendingDeletionSpaces = $this->bandSpaceRepository->countPendingDeletion();
        $overview->activeWindowDays = self::ACTIVE_WINDOW_DAYS;
        $overview->activeSpaces = $this->bandSpaceRepository->countLiveActiveSince(
            new \DateTimeImmutable(\sprintf('-%d days', self::ACTIVE_WINDOW_DAYS)),
        );
        $overview->dormantSpaces = $overview->spaces - $overview->activeSpaces;

        $memberCounts = $this->bandSpaceRepository->findLiveActiveMemberCounts();
        $overview->averageMembers = $this->average($memberCounts);
        $overview->medianMembers = $this->median($memberCounts);
        $overview->soloSpaces = \count(array_filter($memberCounts, static fn (int $members): bool => $members === 1));

        $chat = $this->messageRepository->summarizeBandChatBetween($from, $to);
        $overview->chatMessages = $chat['messages'];
        $overview->chatSpaces = $chat['spaces'];

        $overview->modules = $this->buildModuleUsage($this->bandSpaceActivityRepository->summarizeModulesBetween($from, $to));
        $overview->storageBytes = $this->bandSpaceFileVersionRepository->sumActiveBytesOfLiveBandSpaces();
        $overview->topStorage = $this->buildTopStorage();

        return $overview;
    }

    /**
     * Every module gets a row, so one nobody used shows as a zero rather than going missing.
     *
     * @param array<string, array{spaces: int, activities: int}> $summary
     *
     * @return list<AdminBandSpaceModuleUsage>
     */
    private function buildModuleUsage(array $summary): array
    {
        return array_map(static function (BandSpaceModule $module) use ($summary): AdminBandSpaceModuleUsage {
            $usage = new AdminBandSpaceModuleUsage();
            $usage->module = $module->value;
            $usage->spaces = $summary[$module->value]['spaces'] ?? 0;
            $usage->activities = $summary[$module->value]['activities'] ?? 0;

            return $usage;
        }, BandSpaceModule::cases());
    }

    /** @return list<AdminBandSpaceStorage> */
    private function buildTopStorage(): array
    {
        return array_map(function (array $row): AdminBandSpaceStorage {
            $storage = new AdminBandSpaceStorage();
            $storage->name = $row['bandSpace']->name;
            $storage->bytes = $row['bytes'];
            $storage->quotaBytes = $this->quotaService->getQuotaBytes($row['bandSpace']);

            return $storage;
        }, $this->bandSpaceFileVersionRepository->findLiveBandSpacesByActiveBytes(self::TOP_STORAGE));
    }

    /** @param list<int> $values */
    private function average(array $values): float
    {
        return $values === [] ? 0.0 : round(array_sum($values) / \count($values), 1);
    }

    /** @param list<int> $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $middle = intdiv(\count($values), 2);

        return \count($values) % 2 === 1 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
