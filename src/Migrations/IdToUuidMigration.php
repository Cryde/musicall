<?php

namespace App\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Id\UuidGenerator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IdToUuidMigration extends AbstractMigration implements ContainerAwareInterface
{
    protected $em;
    protected $idToUuidMap = [];
    protected $generator;
    protected $fks;
    protected $table;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->em = $container->get('doctrine')->getManager();
        $this->generator = new UuidGenerator();
    }

    public function up(Schema $schema):void
    {
    }

    public function migrate(string $tableName, string $uuidColumnName = '__uuid__')
    {
        $this->write('Migrating ' . $tableName . '.id to UUIDs...');
        $this->prepare($tableName);
        $this->addUuidFields($uuidColumnName);
        $this->generateUuidsToReplaceIds($uuidColumnName);
        $this->addThoseUuidsToTablesWithFK();
        $this->deletePreviousFKs();
        $this->renameNewFKsToPreviousNames();
        $this->dropIdPrimaryKeyAndSetUuidToPrimaryKey($uuidColumnName);
        $this->restoreConstraintsAndIndexes();
        $this->write('Successfully migrated ' . $tableName . '.id to UUIDs!');
    }

    public function down(Schema $schema):void
    {
    }

    private function isForeignKeyNullable($table, $key)
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getName() === $key) {
                return !$column->getNotnull();
            }
        }
        throw new \Exception('Unable to find ' . $key . 'in ' . $table);
    }

    private function prepare(string $tableName)
    {
        $this->table = $tableName;
        $this->fks = [];
        $this->idToUuidMap = [];

        foreach ($this->sm->listTables() as $table) {
            $foreignKeys = $this->sm->listTableForeignKeys($table->getName());
            foreach ($foreignKeys as $foreignKey) {
                $key = $foreignKey->getColumns()[0];
                if ($foreignKey->getForeignTableName() === $this->table) {
                    $fk = [
                        'table' => $table->getName(),
                        'key' => $key,
                        'tmpKey' => $key . '_to_uuid',
                        'nullable' => $this->isForeignKeyNullable($table, $key),
                        'name' => $foreignKey->getName(),
                        'primaryKey' => $table->getPrimaryKeyColumns(),
                    ];
                    if ($foreignKey->onDelete()) {
                        $fk['onDelete'] = $foreignKey->onDelete();
                    }
                    $this->fks[] = $fk;
                }
            }
        }
        if (count($this->fks) > 0) {
            $this->write('-> Detected the following foreign keys :');
            foreach ($this->fks as $fk) {
                $this->write('  * ' . $fk['table'] . '.' . $fk['key']);
            }

            return;
        }
        $this->write('-> 0 foreign key detected.');
    }

    private function addUuidFields(string $uuidColumnName)
    {
        $this->connection->executeQuery('ALTER TABLE ' . $this->table . " ADD $uuidColumnName CHAR(36) COMMENT '(DC2Type:guid)' FIRST");
        foreach ($this->fks as $fk) {
            $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' ADD ' . $fk['tmpKey'] . ' CHAR(36) COMMENT \'(DC2Type:guid)\'');
        }
    }

    private function generateUuidsToReplaceIds(string $uuidColumnName)
    {
        $fetchs = $this->connection->fetchAll('SELECT id from ' . $this->table);
        if (count($fetchs) > 0) {
            $this->write('-> Generating ' . count($fetchs) . ' UUID(s)...');
            foreach ($fetchs as $fetch) {
                $id = $fetch['id'];
                $uuid = $this->generator->generate($this->em, null);
                $this->idToUuidMap[$id] = $uuid;
                $this->connection->update($this->table, [$uuidColumnName => $uuid], ['id' => $id]);
            }
        }
    }

    private function addThoseUuidsToTablesWithFK()
    {
        if (0 === count($this->fks)) {
            return;
        }
        $this->write('-> Adding UUIDs to tables with foreign keys...');
        foreach ($this->fks as $fk) {
            $selectPk = implode(',', $fk['primaryKey']);
            $fetchs = $this->connection->fetchAll('SELECT ' . $selectPk . ', ' . $fk['key'] . ' FROM ' . $fk['table']);
            if (count($fetchs) > 0) {
                $this->write('  * Adding ' . count($fetchs) . ' UUIDs to "' . $fk['table'] . '.' . $fk['key'] . '"...');
                foreach ($fetchs as $fetch) {
                    // do something when the value of foreign key is not null
                    if ($fetch[$fk['key']]) {
                        $queryPk = array_flip($fk['primaryKey']);
                        foreach ($queryPk as $key => $value) {
                            $queryPk[$key] = $fetch[$key];
                        }
                        $this->connection->update(
                            $fk['table'],
                            [$fk['tmpKey'] => $this->idToUuidMap[$fetch[$fk['key']]]],
                            $queryPk
                        );
                    }
                }
            }
        }
    }

    private function deletePreviousFKs()
    {
        $this->write('-> Deleting previous id foreign keys...');
        foreach ($this->fks as $fk) {
            if (isset($fk['primaryKey'])) {
                try {
                    // drop primary key if not already dropped
                    $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' DROP PRIMARY KEY');
                } catch (\Exception $e) {
                }
            }
            $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' DROP FOREIGN KEY ' . $fk['name']);
            $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' DROP COLUMN ' . $fk['key']);
        }
    }

    private function renameNewFKsToPreviousNames()
    {
        $this->write('-> Renaming temporary uuid foreign keys to previous foreign keys names...');
        foreach ($this->fks as $fk) {
            $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' CHANGE ' . $fk['tmpKey'] . ' ' . $fk['key'] . ' CHAR(36) ' . ($fk['nullable'] ? '' : 'NOT NULL ') . 'COMMENT \'(DC2Type:guid)\'');
        }
    }

    private function dropIdPrimaryKeyAndSetUuidToPrimaryKey(string $uuidColumnName)
    {
        $this->write('-> Creating the uuid primary key...');
        $this->connection->executeQuery('ALTER TABLE ' . $this->table . ' DROP PRIMARY KEY, DROP COLUMN id');
        $this->connection->executeQuery('ALTER TABLE ' . $this->table . " CHANGE $uuidColumnName id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)'");
        $this->connection->executeQuery('ALTER TABLE ' . $this->table . ' ADD PRIMARY KEY (id)');
    }

    private function restoreConstraintsAndIndexes()
    {
        foreach ($this->fks as $fk) {
            if (isset($fk['primaryKey'])) {
                try {
                    // restore primary key if not already restored
                    $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' ADD PRIMARY KEY (' . implode(',', $fk['primaryKey']) . ')');
                } catch (\Exception $e) {
                }
            }
            $this->connection->executeQuery('ALTER TABLE ' . $fk['table'] . ' ADD CONSTRAINT ' . $fk['name'] . ' FOREIGN KEY (' . $fk['key'] . ') REFERENCES ' . $this->table . ' (id)' .
                (isset($fk['onDelete']) ? ' ON DELETE ' . $fk['onDelete'] : '')
            );
            $this->connection->executeQuery('CREATE INDEX ' . str_replace('FK_', 'IDX_', $fk['name']) . ' ON ' . $fk['table'] . ' (' . $fk['key'] . ')');
        }
    }
}
