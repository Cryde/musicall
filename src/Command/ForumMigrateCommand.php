<?php

namespace App\Command;

use App\Repository\Forum\ForumPostRepository;
use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Service\Slugifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:forum:migrate',
    description: 'Command will migrate some data (add forum & topic slug)',
)]
class ForumMigrateCommand extends Command
{
    public function __construct(
        private ForumTopicRepository   $forumTopicRepository,
        private ForumRepository        $forumRepository,
        private ForumPostRepository    $forumPostRepository,
        private EntityManagerInterface $entityManager,
        private Slugifier              $slugifier
    ) {
        parent::__construct(null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $forums = $this->forumRepository->findAll();
        foreach ($forums as $forum) {
            $topicCount = $this->forumTopicRepository->count(['forum' => $forum]);
            if ($topicCount !== $forum->getTopicNumber()) {
                $forum->setTopicNumber($topicCount);
            }
            $postCount = $this->forumPostRepository->countMessagePerForum($forum);
            if ($postCount !== $forum->getPostNumber()) {
                $forum->setPostNumber($postCount);
            }
        }
        $this->entityManager->flush();


        // topic parts
        $topics = $this->forumTopicRepository->findAll();
        foreach ($topics as $topic) {
            $postCount = $this->forumPostRepository->count(['topic' => $topic]);
            $topic->setPostNumber($postCount);

            if ($topic->getSlug()) {
                //continue;
            }
            $slug = $this->slugifier->create($topic, 'title');
            $topic->setSlug($slug);
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }
}
