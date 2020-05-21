<?php

namespace App\Command;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Builder\CommentThreadDirector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommentThreadCreateEmptyCommand extends Command
{
    protected static $defaultName = 'comment:thread:create-empty';
    /**
     * @var PublicationRepository
     */
    private PublicationRepository $publicationRepository;
    /**
     * @var CommentThreadDirector
     */
    private CommentThreadDirector $commentThreadDirector;
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    public function __construct(PublicationRepository $publicationRepository, CommentThreadDirector $commentThreadDirector, EntityManagerInterface $entityManager)
    {
        parent::__construct(self::$defaultName);
        $this->publicationRepository = $publicationRepository;
        $this->commentThreadDirector = $commentThreadDirector;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Create all the thread for publication that are online but don\'t have thread');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $publications = $this->publicationRepository->findBy(['status' => Publication::STATUS_ONLINE, 'thread' => null]);

        foreach ($publications as $publication) {
            $thread = $this->commentThreadDirector->create();
            $thread->setCreationDatetime($publication->getPublicationDatetime());
            $publication->setThread($thread);
            $this->entityManager->persist($thread);
        }

        $this->entityManager->flush();

        return 0;
    }
}
