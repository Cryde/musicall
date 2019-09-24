<?php

namespace App\Service\Builder;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Google\YoutubeUrlHelper;

class PublicationDirector
{
    /**
     * @var YoutubeUrlHelper
     */
    private $youtubeUrlHelper;
    /**
     * @var PublicationSubCategoryRepository
     */
    private $publicationSubCategoryRepository;

    public function __construct(
        YoutubeUrlHelper $youtubeUrlHelper,
        PublicationSubCategoryRepository $publicationSubCategoryRepository
    ) {
        $this->youtubeUrlHelper = $youtubeUrlHelper;
        $this->publicationSubCategoryRepository = $publicationSubCategoryRepository;
    }

    public function buildVideo(array $data, User $user)
    {
        $publicationSubCategory = $this->publicationSubCategoryRepository->findOneBy(['slug' => 'decouvertes']);

        return (new Publication())
            ->setTitle($data['title'])
            ->setType(Publication::TYPE_VIDEO)
            ->setStatus(Publication::STATUS_ONLINE)
            ->setShortDescription($data['description'])
            ->setContent($this->youtubeUrlHelper->getVideoId($data['videoUrl']))
            ->setSubCategory($publicationSubCategory)
            ->setAuthor($user)
            ->setPublicationDatetime(new \DateTimeImmutable())
            ->setCategory(Publication::CATEGORY_PUBLICATION);
    }
}
