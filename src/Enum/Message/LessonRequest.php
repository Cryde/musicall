<?php declare(strict_types=1);

namespace App\Enum\Message;

/** What a message sent from « Demander un cours d'essai » asks for (#1322): a trial when the teacher offers one. */
enum LessonRequest: string
{
    case Trial = 'trial';
    case First = 'first';
}
