<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Ce que tu vas apprendre » and « Morceaux travaillés » on the teacher profile (#1312): JSON, as student_levels.
 * Added nullable, filled, then required, so the existing profiles start with two empty lists.
 */
final class Version20261106150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add level_goals and songs to user_teacher_profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_teacher_profile ADD level_goals JSON DEFAULT NULL, ADD songs JSON DEFAULT NULL');
        $this->addSql("UPDATE user_teacher_profile SET level_goals = '[]', songs = '[]'");
        $this->addSql('ALTER TABLE user_teacher_profile CHANGE level_goals level_goals JSON NOT NULL, CHANGE songs songs JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_teacher_profile DROP level_goals, DROP songs');
    }
}
