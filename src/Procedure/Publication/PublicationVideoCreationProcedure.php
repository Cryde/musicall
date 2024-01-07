<?php

namespace App\Procedure\Publication;

use App\ApiResource\Publication\Video\AddVideo;
use App\Entity\Publication;
use App\Entity\User;
use App\Service\Builder\CommentThreadDirector;
use App\Service\Builder\Metric\ViewCacheDirector;
use App\Service\Builder\PublicationCoverDirector;
use App\Service\Builder\PublicationDirector;
use App\Service\File\RemoteFileDownloader;
use App\Service\Google\Youtube;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PublicationVideoCreationProcedure
{
    public function __construct(
        private readonly Youtube                  $youtube,
        private readonly PublicationCoverDirector $publicationCoverDirector,
        private readonly PublicationDirector      $publicationDirector,
        private readonly RemoteFileDownloader     $remoteFileDownloader,
        private readonly ParameterBagInterface    $containerBag,
        private readonly CommentThreadDirector    $commentThreadDirector,
        private readonly ViewCacheDirector        $viewCacheDirector,
        private readonly EntityManagerInterface   $entityManager
    ) {
    }

    public function process(AddVideo $addVideo, User $currentUser): Publication
    {
        $data = $this->youtube->getVideoInfo($addVideo->url);
        [$path, $size] = $this->remoteFileDownloader->download($data['image_url'], $this->containerBag->get('file_publication_cover_destination'));

        $thread = $this->commentThreadDirector->create();
        $viewCache = $this->viewCacheDirector->build();
        $this->entityManager->persist($thread);
        $cover = $this->publicationCoverDirector->build($path, $size);
        $publication = $this->publicationDirector->buildVideo($addVideo, $currentUser);
        $cover->setPublication($publication);
        $publication->setCover($cover);
        $publication->setThread($thread);
        $publication->setViewCache($viewCache);
        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        return $publication;
    }
}