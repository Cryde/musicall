<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Bot\BotMetaDataProviderInterface;

readonly class ForumMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private ForumRepository      $forumRepository,
        private ForumTopicRepository $forumTopicRepository,
    ) {
    }

    public function supports(string $uri): bool
    {
        return $uri === '/forums'
            || $uri === '/forums/'
            || str_starts_with($uri, '/forums/');
    }

    public function getMetaData(string $uri): array
    {
        if ($uri === '/forums' || $uri === '/forums/') {
            return $this->getForBase();
        }

        if (preg_match('#^/forums/topic/([^/]+)#', $uri, $matches)) {
            return $this->getForTopic($matches[1]);
        }

        if (preg_match('#^/forums/([^/]+)$#', $uri, $matches)) {
            return $this->getForForum($matches[1]);
        }

        return $this->getForBase();
    }

    /**
     * @return array{title: string, description: string}
     */
    private function getForBase(): array
    {
        return [
            'title' => 'Forum - MusicAll',
            'description' => 'Rejoignez la communauté MusicAll et échangez avec des passionnés de musique sur notre forum.',
        ];
    }

    /**
     * @return array{title: string, description: string}
     */
    private function getForForum(string $slug): array
    {
        $forum = $this->forumRepository->findOneBy(['slug' => $slug]);

        if (!$forum) {
            return $this->getForBase();
        }

        return [
            'title' => sprintf('%s - Forum - MusicAll', $forum->getTitle()),
            'description' => $forum->getDescription(),
        ];
    }

    /**
     * @return array{title: string, description: string}
     */
    private function getForTopic(string $slug): array
    {
        $topic = $this->forumTopicRepository->findOneBy(['slug' => $slug]);

        if (!$topic) {
            return $this->getForBase();
        }

        return [
            'title' => sprintf('%s - Forum - MusicAll', $topic->getTitle()),
            'description' => sprintf(
                'Discussion "%s" dans le forum %s sur MusicAll.',
                $topic->getTitle(),
                $topic->getForum()->getTitle()
            ),
        ];
    }
}
