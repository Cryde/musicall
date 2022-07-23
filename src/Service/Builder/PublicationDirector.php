<?php

namespace App\Service\Builder;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Entity\User;
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

    public function buildVideo(array $data, User $user): Publication
    {
        $category = null;
        if (isset($data['categoryId'])) {
            $category = $this->publicationSubCategoryRepository->find($data['categoryId']);
            // we can only define a category for course for now
            if ($category && $category->getType() !== PublicationSubCategory::TYPE_COURSE) {
                $category = null;
            }
        }

        if (!$category) {
            $category = $this->publicationSubCategoryRepository->findOneBy(['slug' => 'decouvertes']);
        }

        return (new Publication())
            ->setTitle($data['title'])
            ->setSlug($this->publicationSlug->create('v-'.$data['title']))
            ->setType(Publication::TYPE_VIDEO)
            ->setStatus(Publication::STATUS_ONLINE)
            ->setShortDescription($data['description'])
            ->setContent($this->youtubeUrlHelper->getVideoId($data['videoUrl']))
            ->setSubCategory($category)
            ->setAuthor($user)
            ->setPublicationDatetime(new \DateTimeImmutable());
    }
}
