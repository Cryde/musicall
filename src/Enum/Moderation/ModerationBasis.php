<?php declare(strict_types=1);

namespace App\Enum\Moderation;

/**
 * The ground a moderation decision rests on, as its statement of reasons names it (DSA art. 17, #1126):
 * one case per item of the terms' §6 « Comportements interdits », plus illegal content under the law.
 * Mirrored by assets/js/constants/moderation.js, pinned by ModerationBasisClientMirrorTest.
 */
enum ModerationBasis: string
{
    case IllegalContent = 'illegal_content';
    case ObjectionableContent = 'objectionable_content';
    case Harassment = 'harassment';
    case Impersonation = 'impersonation';
    case Spam = 'spam';
    case Security = 'security';
    case PersonalData = 'personal_data';
    case IntellectualProperty = 'intellectual_property';
    case BandSpaceStorage = 'band_space_storage';
    case QuotaCircumvention = 'quota_circumvention';

    /** @return list<string> for Assert\Choice */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
