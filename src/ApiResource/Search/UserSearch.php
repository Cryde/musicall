<?php

namespace App\ApiResource\Search;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Provider\Search\UserSearchProvider;
use Symfony\Component\Validator\Constraints\Length;

#[GetCollection(
    uriTemplate: '/users/search',
    paginationEnabled: false,
    name: 'api_users_search',
    provider: UserSearchProvider::class,
    parameters: [
        'search' => new QueryParameter(key: 'search', description: 'The username you search', constraints: [new Length(min: 3)]),
    ]
)]
class UserSearch
{
    public string $id;
    public string $username;
}