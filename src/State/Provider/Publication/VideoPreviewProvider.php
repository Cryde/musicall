<?php

declare(strict_types=1);

namespace App\State\Provider\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Publication\Video\VideoPreview;
use App\Service\Builder\Publication\VideoPreviewBuilder;
use App\Service\Google\Exception\YoutubeVideoNotFoundException;
use App\Service\Google\YoutubeVideo;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<VideoPreview>
 */
readonly class VideoPreviewProvider implements ProviderInterface
{
    public function __construct(
        private Security            $security,
        private YoutubeVideo        $youtube,
        private VideoPreviewBuilder $videoPreviewBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): VideoPreview
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException();
        }

        $url = $context['filters']['url'];

        try {
            $videoInfo = $this->youtube->getVideoInfo($url);
        } catch (YoutubeVideoNotFoundException) {
            throw new NotFoundHttpException('Video not found');
        }

        return $this->videoPreviewBuilder->buildFromYoutubeVideoInfo($videoInfo);
    }
}
