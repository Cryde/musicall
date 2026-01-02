<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use App\Service\Bot\BotMetaDataProviderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicationMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private PublicationRepository            $publicationRepository,
        private PublicationSubCategoryRepository $publicationSubCategoryRepository,
        private UploaderHelper                   $uploaderHelper,
        private CacheManager                     $cacheManager,
    ) {
    }

    public function supports(string $uri): bool
    {
        return $uri === '/publications'
            || $uri === '/publications/'
            || str_starts_with($uri, '/publications/');
    }

    public function getMetaData(string $uri): array
    {
        if (preg_match('#^/publications/category/(.+)$#', $uri, $matches)) {
            return $this->getForCategory($matches[1]);
        }

        if (preg_match('#^/publications/(.+)$#', $uri, $matches)) {
            return $this->getForPublication($matches[1]);
        }

        return $this->getForBase();
    }

    private function getForBase(): array
    {
        return [
            'title' => 'Publications - MusicAll',
            'description' => 'Découvrez toutes les publications sur MusicAll : actualités musicales, tests de matériel, interviews et bien plus encore.',
        ];
    }

    private function getForCategory(string $slug): array
    {
        $category = $this->publicationSubCategoryRepository->findOneBy([
            'slug' => $slug,
            'type' => PublicationSubCategory::TYPE_PUBLICATION,
        ]);

        if (!$category) {
            return $this->getForBase();
        }

        return [
            'title' => $category->getTitle() . ' - Publications - MusicAll',
            'description' => 'Retrouvez toutes les publications de la catégorie ' . $category->getTitle() . ' sur MusicAll.',
        ];
    }

    private function getForPublication(string $slug): array
    {
        $publication = $this->publicationRepository->findOneBy([
            'slug' => $slug,
            'status' => Publication::STATUS_ONLINE,
        ]);

        if (!$publication) {
            return [];
        }

        $cover = null;
        if ($publication->getCover()) {
            $path = $this->uploaderHelper->asset($publication->getCover(), 'imageFile');
            $cover = $this->cacheManager->getBrowserPath($path, 'publication_image_filter');
        }

        return [
            'title' => $publication->getTitle(),
            'description' => $publication->getShortDescription(),
            'cover' => $cover,
        ];
    }
}
