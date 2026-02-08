<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207164002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD created_at DATETIME NOT NULL DEFAULT "2026-01-01 00:00:00" COMMENT \'(DC2Type:datetime_immutable)\', ADD supervisor_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE game ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C19E9AC5F FOREIGN KEY (supervisor_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_232B318C19E9AC5F ON game (supervisor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318C19E9AC5F');
        $this->addSql('DROP INDEX IDX_232B318C19E9AC5F ON game');
        $this->addSql('ALTER TABLE game DROP supervisor_id, DROP created_at, DROP approved_at');
    }
}
