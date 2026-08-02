<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The sections a new rider starts with, in order.
 *
 * A seed, not a schema: these are ordinary rows once created, free to be renamed, reordered
 * or deleted. They exist so a new rider is a prompt rather than a blank page.
 *
 * The patch list and the stage plot are deliberately absent, they are their own tabs.
 */
enum TechRiderDefaultSection: string
{
    case MembersAndContacts = 'members_and_contacts';
    case Backline = 'backline';
    case SoundSystem = 'sound_system';
    case Monitoring = 'monitoring';
    case Lighting = 'lighting';
    case Catering = 'catering';
    case Misc = 'misc';

    public function title(): string
    {
        return match ($this) {
            self::MembersAndContacts => 'Membres et contacts',
            self::Backline => 'Backline et instruments',
            self::SoundSystem => 'Sonorisation',
            self::Monitoring => 'Retours et in-ears',
            self::Lighting => 'Éclairage',
            self::Catering => 'Catering',
            self::Misc => 'Divers',
        };
    }
}
