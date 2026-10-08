<?php declare(strict_types=1);

namespace App\Enum\Message;

/** What a direct message was sent from (#998). */
enum ContactOriginType: string
{
    case MusicianAnnounce = 'musician_announce';
    case TeacherProfile = 'teacher_profile';
}
