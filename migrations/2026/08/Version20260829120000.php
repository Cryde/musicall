<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The eight Band Space columns that still allowed an authorless row, converted to the shape #908 landed
 * on for notes: NOT NULL with no onDelete, so the foreign key defaults to RESTRICT.
 *
 * The nullability was never the point. task.created_by_id started NOT NULL with ON DELETE CASCADE, and
 * Version20260501100000 relaxed both so that deleting a user could not wipe a band's tasks. Dropping
 * the CASCADE alone would have done it: RESTRICT refuses to delete a member who still owns content, so
 * the band keeps its tasks and keeps knowing who wrote them. SET NULL bought the same protection by
 * throwing the authorship away. The file module copied the pattern to five more tables the week after.
 *
 * Nothing hard-deletes a fos_user row today (DeleteAccountProcedure anonymises in place and keeps the
 * primary key), so RESTRICT changes no behaviour; it only stops a future hard delete from silently
 * orphaning content.
 */
final class Version20260829120000 extends AbstractMigration
{
    /** @var array<string, string> table => author column */
    private const array AUTHOR_COLUMNS = [
        'task' => 'created_by_id',
        'band_space_file' => 'created_by_id',
        'band_space_file_version' => 'created_by_id',
        'band_space_file_attachment' => 'attached_by_id',
        'band_space_folder' => 'created_by_id',
        'band_space_file_share' => 'created_by_id',
        'band_space_tech_rider' => 'created_by_id',
        // Named creator rather than createdBy, which is why #909's own sweep for the pattern missed it.
        'agenda_entry' => 'creator_id',
    ];

    /** @var array<string, string> table => foreign key constraint name */
    private const array FOREIGN_KEYS = [
        'task' => 'FK_527EDB25B03A8386',
        'band_space_file' => 'FK_1CDD155DB03A8386',
        'band_space_file_version' => 'FK_3B2112B2B03A8386',
        'band_space_file_attachment' => 'FK_A6E57146A7B6C524',
        'band_space_folder' => 'FK_B12D9B5B03A8386',
        'band_space_file_share' => 'FK_BC2C494DB03A8386',
        'band_space_tech_rider' => 'FK_C4F3158FB03A8386',
        'agenda_entry' => 'FK_7B19C9EE61220EA6',
    ];

    public function getDescription(): string
    {
        return 'Make the author mandatory on the eight remaining nullable Band Space author columns';
    }

    /**
     * A leftover NULL would fail the CHANGE below with a bare SQL error naming neither the table nor
     * what to do about it. Checked for every table before any of them is altered, because MariaDB does
     * not roll DDL back: aborting here leaves the schema untouched, aborting halfway would not.
     */
    public function preUp(Schema $schema): void
    {
        foreach (self::AUTHOR_COLUMNS as $table => $column) {
            $withoutAuthor = (int) $this->connection->fetchOne(
                sprintf('SELECT COUNT(*) FROM %s WHERE %s IS NULL', $table, $column)
            );

            $this->abortIf(
                $withoutAuthor > 0,
                sprintf(
                    '%d row(s) in %s still have no author. Fill %s from the actor of the matching '
                    . 'band_space_activity row, and assign one by hand where no such row exists.',
                    $withoutAuthor,
                    $table,
                    $column,
                ),
            );
        }
    }

    public function up(Schema $schema): void
    {
        foreach (self::AUTHOR_COLUMNS as $table => $column) {
            $foreignKey = self::FOREIGN_KEYS[$table];
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $foreignKey));
            $this->addSql(sprintf('ALTER TABLE %s CHANGE %s %s CHAR(36) NOT NULL', $table, $column, $column));
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES fos_user (id)',
                $table,
                $foreignKey,
                $column,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::AUTHOR_COLUMNS as $table => $column) {
            $foreignKey = self::FOREIGN_KEYS[$table];
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $foreignKey));
            $this->addSql(sprintf('ALTER TABLE %s CHANGE %s %s CHAR(36) DEFAULT NULL', $table, $column, $column));
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES fos_user (id) ON DELETE SET NULL',
                $table,
                $foreignKey,
                $column,
            ));
        }
    }
}
