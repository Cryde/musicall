<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The items a new rider starts with, in order (#1090).
 *
 * A seed, not a schema: these are ordinary rows once created, free to be renamed, reordered or
 * deleted. They exist so a new rider is a prompt rather than a blank page, and each one uses the type
 * built for it: the contacts come from the roster, the plot and the patch list have their editors.
 * Riders created before this change keep the seven text items they were given.
 */
enum TechRiderDefaultItem: string
{
    case MembersAndContacts = 'members_and_contacts';
    case StagePlot = 'stage_plot';
    case PatchList = 'patch_list';
    case Backline = 'backline';
    case Catering = 'catering';

    public function title(): string
    {
        return match ($this) {
            self::MembersAndContacts => 'Membres et contacts',
            self::StagePlot => 'Plan de scène',
            self::PatchList => 'Patch list',
            self::Backline => 'Backline et instruments',
            self::Catering => 'Catering',
        };
    }

    public function type(): TechRiderItemType
    {
        return match ($this) {
            self::MembersAndContacts => TechRiderItemType::Contacts,
            self::StagePlot => TechRiderItemType::StagePlot,
            self::PatchList => TechRiderItemType::PatchList,
            self::Backline, self::Catering => TechRiderItemType::Text,
        };
    }
}
