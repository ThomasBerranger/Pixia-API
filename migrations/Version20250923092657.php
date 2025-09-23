<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250923092657 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', author_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', type VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, hint VARCHAR(255) NOT NULL, explanation VARCHAR(255) NOT NULL, date DATE NOT NULL, difficulty VARCHAR(255) NOT NULL, INDEX IDX_232B318CF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE history (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', game_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', date DATETIME NOT NULL, difficulty VARCHAR(255) NOT NULL, INDEX IDX_27BA704BA76ED395 (user_id), INDEX IDX_27BA704BE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, score INT NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318CF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE history ADD CONSTRAINT FK_27BA704BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE history ADD CONSTRAINT FK_27BA704BE48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP FOREIGN KEY FK_232B318CF675F31B');
        $this->addSql('ALTER TABLE history DROP FOREIGN KEY FK_27BA704BA76ED395');
        $this->addSql('ALTER TABLE history DROP FOREIGN KEY FK_27BA704BE48FD905');

        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE history');
        $this->addSql('DROP TABLE user');
    }
}
