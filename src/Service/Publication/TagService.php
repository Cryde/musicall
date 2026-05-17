<?php

declare(strict_types=1);

namespace App\Service\Publication;

use App\Entity\Publication\Tag;
use App\Repository\Publication\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class TagService
{
    public function __construct(
        private TagRepository          $tagRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface       $slugger,
    ) {
    }

    /**
     * Upsert tags by slug from a list of labels. Caller is responsible for flushing.
     *
     * Callers MUST pass labels that have already been validated (non-blank, length-capped);
     * input cleanliness is not this service's concern. The only thing the service does for
     * input safety is skip labels that the slugger reduces to an empty string (punctuation-only).
     *
     * @param string[] $labels
     *
     * @return Tag[]
     */
    public function upsertByLabels(array $labels): array
    {
        $bySlug = [];
        foreach ($labels as $label) {
            $slug = $this->slugger->slug($label)->lower()->toString();
            if ($slug === '' || isset($bySlug[$slug])) {
                continue;
            }
            $bySlug[$slug] = $label;
        }

        if ($bySlug === []) {
            return [];
        }

        $existing = $this->tagRepository->findBySlugs(array_keys($bySlug));
        $existingBySlug = [];
        foreach ($existing as $tag) {
            $existingBySlug[$tag->slug] = $tag;
        }

        $tags = [];
        foreach ($bySlug as $slug => $label) {
            if (isset($existingBySlug[$slug])) {
                $tags[] = $existingBySlug[$slug];
                continue;
            }
            $tag = new Tag();
            $tag->label = $label;
            $tag->slug = $slug;
            $this->entityManager->persist($tag);
            $tags[] = $tag;
        }

        return $tags;
    }
}
