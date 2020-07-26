<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\IdToUuidMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200726084555 extends IdToUuidMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->migrate('fos_user');
    }
}
