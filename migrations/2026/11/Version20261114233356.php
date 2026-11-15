<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The ground a suspension rests on, named in its statement of reasons (#1126). Nullable: a dismissal or a
 * lift has none, and the suspensions taken before this column never recorded one.
 */
final class Version20261114233356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moderation_action.basis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_action ADD basis VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_action DROP basis');
    }
}
