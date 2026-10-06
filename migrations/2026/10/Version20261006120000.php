<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Records whether an invitation was made by typing a username, whose email the inviter never saw
 * (#1119). How older invitations were made is unknown, so every one pointing at an existing account
 * is treated as made by username, which hides its address. Their activities stop carrying the
 * address and name the username instead; that rewrite has nothing to undo, so down() only drops the
 * column.
 */
final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag band space invitations made by username and remove their email from activities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_invitation ADD invited_by_username TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE band_space_invitation SET invited_by_username = 1 WHERE existing_user_id IS NOT NULL');
        $this->addSql(<<<'SQL'
            UPDATE band_space_activity activity
            INNER JOIN band_space_invitation invitation ON invitation.id = activity.resource_id
            INNER JOIN fos_user invitee ON invitee.id = invitation.existing_user_id
            SET activity.payload = JSON_SET(
                JSON_REMOVE(activity.payload, '$.email', '$.invited_user_id'),
                '$.invited_username',
                invitee.username
            )
            WHERE invitation.invited_by_username = 1
              AND activity.module = 'settings'
              AND JSON_EXISTS(activity.payload, '$.email')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_invitation DROP invited_by_username');
    }
}
