<?php

namespace App\Service\Builder;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Google\YoutubeUrlHelper;
use App\Service\Publication\PublicationSlug;

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
    /**
     * @var PublicationSlug
     */
    private $publicationSlug;

    public function __construct(
        YoutubeUrlHelper $youtubeUrlHelper,
        PublicationSubCategoryRepository $publicationSubCategoryRepository,
        PublicationSlug $publicationSlug
    ) {
        $this->youtubeUrlHelper = $youtubeUrlHelper;
        $this->publicationSubCategoryRepository = $publicationSubCategoryRepository;
        $this->publicationSlug = $publicationSlug;
    }

    public function buildVideo(array $data, User $user)
    {
        $publicationSubCategory = $this->publicationSubCategoryRepository->findOneBy(['slug' => 'decouvertes']);

        return (new Publication())
            ->setTitle($data['title'])
            ->setSlug($this->publicationSlug->create('v-'.$data['title']))
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
