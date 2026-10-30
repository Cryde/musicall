<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\Lyrics;
use App\Entity\User;
use App\Http\ContentDisposition;
use App\Repository\BandSpace\LyricsRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\Song\SongPdfRenderer;
use App\Service\BandSpace\Song\SongSheetBuilder;
use App\Service\BandSpace\Song\SongSheetOptions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Archived lyrics download too: this is a read.
 *
 * @implements ProviderInterface<Response>
 */
readonly class LyricsPdfExportProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private LyricsRepository $lyricsRepository,
        private SongSheetBuilder $sheetBuilder,
        private SongPdfRenderer $pdfRenderer,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $lyrics = $this->lyricsRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$lyrics instanceof Lyrics) {
            throw new NotFoundHttpException('band_space.lyrics.not_found');
        }

        $sheet = $this->sheetBuilder->buildFromLyrics($lyrics, SongSheetOptions::fromParameters($operation->getParameters()));
        $response = new Response($this->pdfRenderer->renderSheet($sheet));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', ContentDisposition::attachment($sheet['title'] . '.pdf'));

        return $response;
    }
}
