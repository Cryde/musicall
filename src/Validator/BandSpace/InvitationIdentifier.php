<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class InvitationIdentifier extends Constraint
{
    public const string INVALID_EMAIL_ERROR = 'music_all_b1c2d3e4-5f6a-7b8c-9d0e-1f2a3b4c5d6e';
    public const string USERNAME_NOT_FOUND_ERROR = 'music_all_c2d3e4f5-6a7b-8c9d-0e1f-2a3b4c5d6e7f';

    public string $invalidEmailMessage = 'L\'adresse email n\'est pas valide';
    public string $usernameNotFoundMessage = 'Aucun utilisateur trouvé avec ce nom d\'utilisateur';
}
