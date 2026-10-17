<?php declare(strict_types=1);

namespace App\Validator\Message;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidContactOrigin extends Constraint
{
    public string $bothMessage = 'message.contact_origin.both';
    public string $announceMessage = 'message.contact_origin.announce_invalid';
    public string $teacherProfileMessage = 'message.contact_origin.no_teacher_profile';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
