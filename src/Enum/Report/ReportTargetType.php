<?php declare(strict_types=1);

namespace App\Enum\Report;

/** What a report can point at (#1116). */
enum ReportTargetType: string
{
    case User = 'user';
    case Announce = 'announce';
    case Message = 'message';
    case BandChatMessage = 'band_chat_message';
    case ProfileMedia = 'profile_media';
    case ForumPost = 'forum_post';
    case Comment = 'comment';
    case Publication = 'publication';

    /**
     * One spelling per target, so a repeat and a moderator's decision find every report on it:
     * integers without leading zeros, uuids in lower case.
     */
    public function canonicalId(string $id): string
    {
        $isInteger = $this === self::Comment || $this === self::Publication;
        if (!$isInteger) {
            return mb_strtolower($id);
        }

        // Past 18 digits it is no id of ours, and an int cast would saturate onto a real one.
        return ctype_digit($id) && strlen(ltrim($id, '0')) <= 18 ? (string) (int) $id : $id;
    }

    /** @return list<string> for Assert\Choice */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
