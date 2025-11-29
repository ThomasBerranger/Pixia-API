<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129215838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achievement DROP FOREIGN KEY FK_96737FF199E6F5DF');
        $this->addSql('ALTER TABLE achievement ADD CONSTRAINT FK_96737FF199E6F5DF FOREIGN KEY (player_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achievement DROP FOREIGN KEY FK_96737FF199E6F5DF');
        $this->addSql('ALTER TABLE achievement ADD CONSTRAINT FK_96737FF199E6F5DF FOREIGN KEY (player_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
