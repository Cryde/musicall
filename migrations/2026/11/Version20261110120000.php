<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Where and how each account was created (#1355). Every existing account is backfilled as web; its method
 * is the provider of a social account created with it, the same minute, otherwise password.
 */
final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fos_user.registration_platform, registration_method and registration_app_version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user ADD registration_platform VARCHAR(20) DEFAULT NULL, ADD registration_method VARCHAR(20) DEFAULT NULL, ADD registration_app_version VARCHAR(32) DEFAULT NULL');
        $this->addSql("UPDATE fos_user SET registration_platform = 'web'");
        // A Google or Apple sign-up writes its social account right after the user; a link made later from the settings does not.
        $this->addSql("UPDATE fos_user u SET u.registration_method = COALESCE((
            SELECT sa.provider FROM user_social_account sa
            WHERE sa.user_id = u.id
              AND sa.provider IN ('google', 'apple')
              AND sa.creation_datetime BETWEEN u.creation_datetime AND u.creation_datetime + INTERVAL 60 SECOND
            ORDER BY sa.creation_datetime, sa.id
            LIMIT 1
        ), 'password')");
        $this->addSql('ALTER TABLE fos_user MODIFY registration_platform VARCHAR(20) NOT NULL, MODIFY registration_method VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fos_user DROP registration_platform, DROP registration_method, DROP registration_app_version');
    }
}
