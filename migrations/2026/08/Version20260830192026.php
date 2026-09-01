<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The absence table behind #777: a period a band member is not available, so the agenda can answer
 * "who can actually be there".
 *
 * It hangs off band_space_membership rather than fos_user, so a record belongs to one band and
 * survives the member being in several. ON DELETE CASCADE because an absence is meaningless once the
 * membership is gone, unlike the authored content Version20260829120000 moved to RESTRICT.
 *
 * The only index is IDX_9DE6D6877597D3FE, the one Doctrine emits for the join column. A composite
 * (member_id, start_date, end_date) was measured and dropped: MariaDB drives from the membership
 * side on band_space_id and then does a ref into this table on the foreign key index, so it never
 * picks the composite while that index exists, and DBAL's Index::isFulfilledBy needs an equal column
 * count so the composite can never absorb it either. At band scale - single digit members, tens of
 * absences a year - the plans are indistinguishable, so the composite was pure write cost.
 */
final class Version20260830192026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create band_space_member_absence, the member unavailability shown on the agenda';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE band_space_member_absence (id CHAR(36) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(120) DEFAULT NULL, creation_datetime DATETIME NOT NULL, member_id CHAR(36) NOT NULL, INDEX IDX_9DE6D6877597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE band_space_member_absence ADD CONSTRAINT FK_9DE6D6877597D3FE FOREIGN KEY (member_id) REFERENCES band_space_membership (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE band_space_member_absence DROP FOREIGN KEY FK_9DE6D6877597D3FE');
        $this->addSql('DROP TABLE band_space_member_absence');
    }
}
