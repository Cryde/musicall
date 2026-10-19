<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Decoded in PHP rather than with JSON_TABLE: a real row copies the reference column byte for byte, so an
 * exact lookup finds it, while the columns' case and accent insensitive collation would also convert a
 * variant, which is deliberately left alone. A name matching nothing stays and is shown as stored.
 */
final class Version20261018095157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store instrument and style slugs in message contact origins and announce report snapshots (#1212)';
    }

    public function up(Schema $schema): void
    {
        // An announce names the player (« Batteur »), a teacher profile the instrument (« Piano »).
        $this->convert(
            $this->lookup('SELECT musician_name, slug FROM attribute_instrument'),
            $this->lookup('SELECT name, slug FROM attribute_instrument'),
            $this->lookup('SELECT name, slug FROM attribute_style'),
        );
    }

    public function down(Schema $schema): void
    {
        $this->convert(
            $this->lookup('SELECT slug, musician_name FROM attribute_instrument'),
            $this->lookup('SELECT slug, name FROM attribute_instrument'),
            $this->lookup('SELECT slug, name FROM attribute_style'),
        );
    }

    /**
     * @param array<string, string> $announceInstruments
     * @param array<string, string> $teacherInstruments
     * @param array<string, string> $styles
     */
    private function convert(array $announceInstruments, array $teacherInstruments, array $styles): void
    {
        $origins = $this->connection->fetchAllAssociative('SELECT id, type, instruments, styles FROM message_contact_origin');
        foreach ($origins as $origin) {
            $instrumentLookup = $origin['type'] === 'teacher_profile' ? $teacherInstruments : $announceInstruments;
            $instruments = self::encode(self::replace(self::decode($origin['instruments']), $instrumentLookup));
            $originStyles = self::encode(self::replace(self::decode($origin['styles']), $styles));
            if ($instruments === $origin['instruments'] && $originStyles === $origin['styles']) {
                continue;
            }

            $this->addSql(
                'UPDATE message_contact_origin SET instruments = ?, styles = ? WHERE id = ?',
                [$instruments, $originStyles, $origin['id']],
            );
        }

        $reports = $this->connection->fetchAllAssociative("SELECT id, snapshot_context FROM report WHERE target_type = 'announce'");
        foreach ($reports as $report) {
            $context = self::decode($report['snapshot_context']);
            $instrument = $context['instrument'] ?? null;
            if (!is_string($instrument) || !isset($announceInstruments[$instrument])) {
                continue;
            }

            $context['instrument'] = $announceInstruments[$instrument];
            $this->addSql('UPDATE report SET snapshot_context = ? WHERE id = ?', [self::encode($context), $report['id']]);
        }
    }

    /** @return array<string, string> */
    private function lookup(string $sql): array
    {
        /** @var array<string, string> */
        return $this->connection->fetchAllKeyValue($sql);
    }

    /**
     * @param array<mixed> $values
     * @param array<string, string> $lookup
     *
     * @return array<mixed>
     */
    private static function replace(array $values, array $lookup): array
    {
        return array_map(static fn (mixed $value): mixed => is_string($value) ? $lookup[$value] ?? $value : $value, $values);
    }

    /** @return array<mixed> */
    private static function decode(string $json): array
    {
        return (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Doctrine's own JSON flags, so an untouched row and a converted one read the same. */
    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
