<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: user, admin, events, reservations tables';
    }

    public function up(Schema $schema): void
    {
        // ── user ──────────────────────────────────────────────────
        $this->addSql('CREATE TABLE "user" (
            id       SERIAL NOT NULL,
            username VARCHAR(180) NOT NULL,
            roles    JSON NOT NULL DEFAULT \'[]\',
            password VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "user" (username)');

        // ── admin ─────────────────────────────────────────────────
        $this->addSql('CREATE TABLE admin (
            id       SERIAL NOT NULL,
            username VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ADMIN_USERNAME ON admin (username)');

        // ── events ────────────────────────────────────────────────
        $this->addSql('CREATE TABLE events (
            id          SERIAL NOT NULL,
            title       VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            date        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            location    VARCHAR(255) NOT NULL,
            seats       INT NOT NULL,
            image       VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        // ── reservations ──────────────────────────────────────────
        $this->addSql('CREATE TABLE reservations (
            id         SERIAL NOT NULL,
            event_id   INT NOT NULL,
            user_id    INT DEFAULT NULL,
            name       VARCHAR(180) NOT NULL,
            email      VARCHAR(180) NOT NULL,
            phone      VARCHAR(30) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN reservations.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reservations
            ADD CONSTRAINT FK_RESERVATIONS_EVENT  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_RESERVATIONS_USER   FOREIGN KEY (user_id)  REFERENCES "user"(id) ON DELETE SET NULL
        ');
        $this->addSql('CREATE INDEX IDX_RESERVATIONS_EVENT ON reservations (event_id)');
        $this->addSql('CREATE INDEX IDX_RESERVATIONS_USER  ON reservations (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT FK_RESERVATIONS_EVENT');
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT FK_RESERVATIONS_USER');
        $this->addSql('DROP TABLE IF EXISTS reservations');
        $this->addSql('DROP TABLE IF EXISTS events');
        $this->addSql('DROP TABLE IF EXISTS admin');
        $this->addSql('DROP TABLE IF EXISTS "user"');
    }
}