<?php

namespace App\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\MusicianText;
use App\Entity\User;
use App\Exception\Musician\InvalidSearchException;
use App\Service\Builder\Search\MusicianSearchResultBuilder;
use App\Service\Finder\Musician\MusicianAIFinder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MusicianSearchProvider implements ProviderInterface
{
    public function __construct(
        private readonly ValidatorInterface          $validator,
        private readonly Security                    $security,
        private readonly MusicianAIFinder            $musicianAIFinder,
        private readonly MusicianSearchResultBuilder $musicianSearchResultBuilder
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$search = $context['filters']['search'] ?? null) {
            throw new InvalidSearchException('No search provided');
        }
        $musicianText = new MusicianText();
        $musicianText->search = $search;
        $errors = $this->validator->validate($musicianText);
        if (count($errors) > 0) {
            throw new InvalidSearchException();
        }
        /** @var User|null $user */
        $user = $this->security->getUser();
        $results = $this->musicianAIFinder->find($musicianText, $user);

        return $this->musicianSearchResultBuilder->buildFromList($results);
    }
}