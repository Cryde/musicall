<?php declare(strict_types=1);

namespace App\Enum\Feedback;

/**
 * Where in the app a piece of feedback came from.
 *
 * Distinct from {@see \App\Enum\BandSpace\BandSpaceModule}, which is band scoped and whose values
 * are a persisted contract for BandSpaceActivity rows: adding a site wide case there would widen a
 * column that a different feature depends on. The Band Space cases here carry the same string values
 * as their BandSpaceModule counterparts on purpose, so the two can be compared without a mapping
 * table, but the two enums are free to diverge.
 *
 * The client prefills this from the current route, so a case only earns its place if a user can
 * actually be standing on it. `Other` is the fallback for a route the map does not know, which is
 * also what an anonymous caller posting by hand gets.
 */
enum FeedbackModule: string
{
    // Band Space, mirroring BandSpaceModule's values.
    case Agenda = 'agenda';
    case Notes = 'notes';
    case File = 'file';
    case Task = 'task';
    case Finance = 'finance';
    case Setlist = 'setlist';
    case Rider = 'rider';
    case Settings = 'settings';
    case Dashboard = 'dashboard';

    // The rest of the site.
    case Forum = 'forum';
    case Publication = 'publication';
    case Gallery = 'gallery';
    case Directory = 'directory';
    case Course = 'course';
    case Message = 'message';
    case Notification = 'notification';
    case Account = 'account';
    case Other = 'other';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Agenda => 'Agenda',
            self::Notes => 'Notes',
            self::File => 'Fichiers',
            self::Task => 'Tâches',
            self::Finance => 'Finances',
            self::Setlist => 'Setlists',
            self::Rider => 'Tech riders',
            self::Settings => 'Paramètres du Band Space',
            self::Dashboard => 'Dashboard du Band Space',
            self::Forum => 'Forum',
            self::Publication => 'Publications',
            self::Gallery => 'Galeries photo',
            self::Directory => 'Annuaire et recherche',
            self::Course => 'Cours',
            self::Message => 'Messagerie',
            self::Notification => 'Notifications',
            self::Account => 'Mon compte',
            self::Other => 'Autre',
        };
    }
}
