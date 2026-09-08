<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908192030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace message_thread_meta.is_read with a last_read_datetime read position';
    }

    /**
     * The conversion is the interesting half, and it is deliberately not "read = now".
     *
     * A row already marked read becomes the thread's last message, because that is what they have
     * read, and a read position in the future would hide the next message to arrive.
     *
     * A row marked unread becomes one second *before* that last message, so exactly one message
     * counts as unread. Null would be the other defensible answer, but null means every message ever
     * written in the thread is unread, so a badge showing "4 threads" today would show hundreds after
     * the deploy. This preserves what people currently see and lets real counts build from here.
     *
     * A thread with no last message gets null either way: there is nothing there to have read.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_meta ADD last_read_datetime DATETIME DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE message_thread_meta meta
            INNER JOIN message_thread thread ON thread.id = meta.thread_id
            INNER JOIN message last_message ON last_message.id = thread.last_message_id
            SET meta.last_read_datetime = IF(
                meta.is_read = 1,
                last_message.creation_datetime,
                DATE_SUB(last_message.creation_datetime, INTERVAL 1 SECOND)
            )
            SQL);

        $this->addSql('ALTER TABLE message_thread_meta DROP is_read');
    }

    /**
     * Back to a boolean: read means the position has reached the thread's last message. A row with no
     * position, or one behind the last message, is unread. Threads with no last message come back as
     * read, arbitrarily: going forward gave them a null position, which the new model reads as unread,
     * so the distinction is already lost by then. No such row exists in practice, since a thread gets
     * its last_message_id inside the transaction that creates it.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_thread_meta ADD is_read TINYINT(1) DEFAULT 1 NOT NULL');

        $this->addSql(<<<'SQL'
            UPDATE message_thread_meta meta
            INNER JOIN message_thread thread ON thread.id = meta.thread_id
            INNER JOIN message last_message ON last_message.id = thread.last_message_id
            SET meta.is_read = IF(
                meta.last_read_datetime IS NOT NULL
                    AND meta.last_read_datetime >= last_message.creation_datetime,
                1,
                0
            )
            SQL);

        $this->addSql('ALTER TABLE message_thread_meta DROP last_read_datetime');
        $this->addSql('ALTER TABLE message_thread_meta ALTER is_read DROP DEFAULT');
    }
}
