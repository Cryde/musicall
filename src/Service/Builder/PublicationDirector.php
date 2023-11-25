<?php

namespace App\Service\Builder;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
use App\Model\Publication\Request\AddVideo;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Google\YoutubeUrlHelper;
use App\Service\Publication\PublicationSlug;

class PublicationDirector
{
    public function __construct(
        private readonly YoutubeUrlHelper                 $youtubeUrlHelper,
        private readonly PublicationSubCategoryRepository $publicationSubCategoryRepository,
        private readonly PublicationSlug                  $publicationSlug
    ) {
    }

    public function buildVideo(AddVideo $addVideo, User $user): Publication
    {
        $category = $addVideo->category;
        if ($category && $category->getType() !== PublicationSubCategory::TYPE_COURSE) {
            $category = null;
        }
        if (!$category) {
            $category = $this->publicationSubCategoryRepository->findOneBy(['slug' => 'decouvertes']);
        }

        return (new Publication())
            ->setTitle($addVideo->title)
            ->setSlug($this->publicationSlug->create('v-' . $addVideo->title))
            ->setType(Publication::TYPE_VIDEO)
            ->setStatus(Publication::STATUS_ONLINE)
            ->setShortDescription($addVideo->description)
            ->setContent($this->youtubeUrlHelper->getVideoId($addVideo->url))
            ->setSubCategory($category)
            ->setAuthor($user)
            ->setPublicationDatetime(new \DateTimeImmutable());
    }
}
