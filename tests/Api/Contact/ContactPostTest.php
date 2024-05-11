<?php

namespace App\Tests\Api\Contact;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ContactPostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged()
    {
        $this->client->jsonRequest('POST', '/api/contact', [
            'name'    => 'Name test',
            'email'   => 'test@email.com',
            'message' => 'this is the message',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            'name'    => 'Name test',
            'email'   => 'test@email.com',
            'message' => 'this is the message',
        ]);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, '[ADMIN] Contact reçu depuis MusicAll');
    }

    public function test_with_errors()
    {
        $this->client->jsonRequest('POST', '/api/contact', [
            'name'    => 'na',
            'email'   => 'test_not_email_address',
            'message' => 'small',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            'status'            => 422,
            'violations'        => [
                [
                    'propertyPath' => 'email',
                    'message'      => 'L\'email est invalide',
                    'code'         => 'bd79c0ab-ddba-46cc-a703-a7a4b08de310',
                ],
                [
                    'propertyPath' => 'message',
                    'message'      => 'Votre message doit être de minimum 10 caractères.',
                    'code'         => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail'            => 'email: L\'email est invalide
message: Votre message doit être de minimum 10 caractères.',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: L\'email est invalide
message: Votre message doit être de minimum 10 caractères.',
            'type'              => '/validation_errors/0=bd79c0ab-ddba-46cc-a703-a7a4b08de310;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title'             => 'An error occurred',
        ]);

        $this->assertEmailCount(0);
    }
}