<?php declare(strict_types=1);

namespace App\Validator\Message;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidContactOrigin extends Constraint
{
    public string $bothMessage = 'Un message part d\'une annonce ou d\'un profil de professeur, pas des deux';
    public string $announceMessage = 'Cette annonce n\'existe plus ou n\'appartient pas au destinataire';
    public string $teacherProfileMessage = 'Le destinataire n\'a pas de profil de professeur';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
