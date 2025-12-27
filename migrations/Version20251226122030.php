<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226122030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE monthly_leaderboard (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', player_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', score INT NOT NULL, month DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8535389C99E6F5DF (player_id), UNIQUE INDEX unique_player_month (player_id, month), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE monthly_leaderboard ADD CONSTRAINT FK_8535389C99E6F5DF FOREIGN KEY (player_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE achievement CHANGE date date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE game CHANGE date date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monthly_leaderboard DROP FOREIGN KEY FK_8535389C99E6F5DF');
        $this->addSql('DROP TABLE monthly_leaderboard');
        $this->addSql('ALTER TABLE game CHANGE date date DATE NOT NULL');
        $this->addSql('ALTER TABLE achievement CHANGE date date DATETIME NOT NULL');
    }
}
