<?php

declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Attribute\Instrument;
use App\Model\Search\TeacherSearchLanding;
use App\Repository\Attribute\InstrumentRepository;
use App\Service\Bot\PageMetaDataProviderInterface;
use App\Translation\ReferenceNameTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

/** « Prof de basse à Bruxelles »: the teacher search names the instrument and the city its address holds (#1314). */
readonly class TeacherSearchMetaDataProvider implements PageMetaDataProviderInterface
{
    private const string PATH = '/rechercher-un-professeur';
    // French writes « d'orgue » but « de basse »; an h is left alone, most instruments starting with one aspirate it.
    private const string ELIDED = '/^[aeiouyàâäéèêëîïôöùûü]/iu';
    // By whether an instrument, then a city, is searched.
    private const array TITLES = [
        'none_none' => 'meta.teacher_search.title',
        'instrument_none' => 'meta.teacher_search.title_instrument',
        'none_city' => 'meta.teacher_search.title_city',
        'instrument_city' => 'meta.teacher_search.title_instrument_city',
    ];
    private const array DESCRIPTIONS = [
        'none_none' => 'meta.teacher_search.description',
        'instrument_none' => 'meta.teacher_search.description_instrument',
        'none_city' => 'meta.teacher_search.description_city',
        'instrument_city' => 'meta.teacher_search.description_instrument_city',
    ];

    public function __construct(
        private InstrumentRepository $instrumentRepository,
        private ReferenceNameTranslator $referenceNameTranslator,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(string $uri): bool
    {
        return rtrim(explode('?', $uri, 2)[0], '/') === self::PATH;
    }

    public function getMetaData(string $uri): array
    {
        parse_str(explode('?', $uri, 2)[1] ?? '', $query);
        $landing = TeacherSearchLanding::fromQuery($query);
        $instrument = $landing->instrumentSlug !== null ? $this->instrumentRepository->findOneBy(['slug' => $landing->instrumentSlug]) : null;
        $name = $instrument instanceof Instrument ? $this->referenceNameTranslator->instrument($instrument) : null;

        $variant = ($name !== null ? 'instrument' : 'none') . '_' . ($landing->city !== null ? 'city' : 'none');
        $parameters = ['instrument' => $name !== null ? $this->instrumentPhrase($name) : null, 'city' => $landing->city];

        return [
            // English names the instrument first: « Bass teacher ».
            'title' => mb_ucfirst($this->translator->trans(self::TITLES[$variant], $parameters)),
            'description' => $this->translator->trans(self::DESCRIPTIONS[$variant], $parameters),
        ];
    }

    /** How a sentence names the instrument: « de basse », « d'orgue » in French, « bass » in English (teacherSearch.js). */
    private function instrumentPhrase(string $name): string
    {
        $lower = mb_strtolower($name);
        if (!str_starts_with($this->translator->getLocale(), 'fr')) {
            return $lower;
        }

        return preg_match(self::ELIDED, $lower) === 1 ? "d'" . $lower : 'de ' . $lower;
    }
}
