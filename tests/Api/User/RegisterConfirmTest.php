<?php

namespace App\Tests\Api\User;

use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RegisterConfirmTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_register_confirm()
    {
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user1 = UserFactory::new()->asBaseUser()->create(['token' => 'abc'])->_real();

        // pre-test double check that we have user with that token
        $this->assertCount(1, $userRepository->findBy(['token' => 'abc']));

        $this->client->request('GET', '/register/confirm/abc');
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/registration/success');

        $this->assertNull($user1->getToken());
    }

    public function test_register_confirm_with_not_existing_token()
    {
        $this->client->request('GET', '/register/confirm/not_existing');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'Ce token n\'existe pas/plus, il est possible que vous ayez déjà confirmé votre compte',
            $this->client->getResponse()->getContent()
        );
    }
}