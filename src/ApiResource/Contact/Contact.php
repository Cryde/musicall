<?php

namespace App\ApiResource\Contact;

use ApiPlatform\Metadata\Post;
use App\State\Processor\Contact\ContactProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/contact',
    name: '_api_contact_us',
    processor: ContactProcessor::class
)]
class Contact
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, minMessage: 'Votre nom doit contenir minimum {{ limit }} caractères.')]
    public string $name;
    #[Assert\NotBlank]
    #[Assert\Email(message: "L'email est invalide")]
    public string $email;
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, minMessage: 'Votre message doit être de minimum {{ limit }} caractères.')]
    public string $message;
}
