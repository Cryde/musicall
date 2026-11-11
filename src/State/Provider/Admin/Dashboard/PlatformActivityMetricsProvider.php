<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Dashboard\PlatformActivityMetrics;
use App\Repository\User\UserDailyActivityRepository;
use Psr\Clock\ClockInterface;

/**
 * @implements ProviderInterface<PlatformActivityMetrics>
 */
readonly class PlatformActivityMetricsProvider implements ProviderInterface
{
    private const int APP_VERSION_DAYS = 30;

    public function __construct(
        private UserDailyActivityRepository $userDailyActivityRepository,
        private ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PlatformActivityMetrics
    {
        $from = new \DateTimeImmutable($context['filters']['from']);
        // +1 day so the end date is inclusive
        $to = (new \DateTimeImmutable($context['filters']['to']))->modify('+1 day');

        $metrics = new PlatformActivityMetrics();
        $metrics->activeMembers = $this->userDailyActivityRepository->countMembers($from, $to);
        $metrics->dailyActiveMembers = $this->userDailyActivityRepository->countMembersByDayAndPlatform($from, $to);
        $metrics->activeMembersByPlatform = $this->userDailyActivityRepository->countMembersByPlatform($from, $to);
        $metrics->multiPlatformMembers = $this->userDailyActivityRepository->countMultiPlatformMembers($from, $to);
        $metrics->activeMembersByRegistrationPlatform = $this->userDailyActivityRepository->countMembersByRegistrationPlatform($from, $to);
        // Today included.
        $metrics->appVersions = $this->userDailyActivityRepository->countMembersByAppVersion(
            $this->clock->now()->modify(sprintf('-%d days', self::APP_VERSION_DAYS - 1)),
        );

        return $metrics;
    }
}
