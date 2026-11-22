<?php

declare(strict_types=1);

namespace App\Validator\Service;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Service\Service;
use App\Service\Musician\MediaUrlParser;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use App\Validator\Musician\SupportedMediaUrl;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A link a service embeds, against what its kind takes (#1411). Checked once the service is known, which a body
 * alone does not say: only a photography or video service takes Vimeo.
 */
readonly class ServiceMediaUrlChecker
{
    public function __construct(
        private MediaUrlParser $mediaUrlParser,
        private ValidatorInterface $validator,
    ) {
    }

    /** What the link embeds, or a 422 on `$propertyPath`, as the validator answers any other field. */
    public function check(Service $service, string $url, string $propertyPath): ParsedMediaUrl
    {
        $platforms = $service->kind->mediaPlatforms();
        $parsed = $this->mediaUrlParser->parse($url, $platforms);
        if ($parsed instanceof ParsedMediaUrl) {
            return $parsed;
        }

        // Asked of the validator only to word the refusal as SupportedMediaUrl does everywhere else.
        throw new ValidationException($this->validator->startContext()
            ->atPath($propertyPath)
            ->validate($url, new SupportedMediaUrl(platforms: $platforms))
            ->getViolations());
    }
}
