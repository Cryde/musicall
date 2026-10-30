<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sign in with Apple keeps the refresh token it must revoke when the account is deleted (#1269).
 */
final class Version20261030160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_token and refresh_token_client_id to user_social_account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_social_account ADD refresh_token VARCHAR(512) DEFAULT NULL, ADD refresh_token_client_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_social_account DROP refresh_token, DROP refresh_token_client_id');
    }
}
