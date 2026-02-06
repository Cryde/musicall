<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Dashboard\TimeSeriesMetrics;
use App\Repository\Comment\CommentRepository;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Message\MessageRepository;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\PublicationRepository;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<TimeSeriesMetrics>
 */
readonly class TimeSeriesMetricsProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageRepository $messageRepository,
        private PublicationRepository $publicationRepository,
        private CommentRepository $commentRepository,
        private ForumPostRepository $forumPostRepository,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TimeSeriesMetrics
    {
        /** @var string $metric */
        $metric = $context['filters']['metric'];
        $from = new \DateTimeImmutable($context['filters']['from']);
        // +1 day so the end date is inclusive
        $to = (new \DateTimeImmutable($context['filters']['to']))->modify('+1 day');

        $dataPoints = match ($metric) {
            'registrations' => $this->userRepository->countRegistrationsByDate($from, $to),
            'logins' => $this->userRepository->countLoginsByDate($from, $to),
            'messages' => $this->messageRepository->countMessagesByDate($from, $to),
            'publications' => $this->publicationRepository->countPublicationsByDate($from, $to),
            'comments' => $this->commentRepository->countCommentsByDate($from, $to),
            'forum_posts' => $this->forumPostRepository->countForumPostsByDate($from, $to),
            'musician_announces' => $this->musicianAnnounceRepository->countMusicianAnnouncesByDate($from, $to),
            default => [],
        };

        $result = new TimeSeriesMetrics();
        $result->metric = $metric;
        $result->from = $context['filters']['from'];
        $result->to = $context['filters']['to'];
        $result->dataPoints = $dataPoints;
        $result->total = array_sum(array_column($dataPoints, 'count'));

        return $result;
    }
}
