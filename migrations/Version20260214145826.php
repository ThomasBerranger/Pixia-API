<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260214145826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la contrainte unique (type, date) pour permettre les DRAFT/PENDING avec mêmes dates. Validation unicité APPROVED gérée en PHP.';
    }

    public function up(Schema $schema): void
    {
        // Supprimer la contrainte unique
        // L'unicité pour les APPROVED sera validée en PHP
        $this->addSql('DROP INDEX unique_type_date ON game');
    }

    public function down(Schema $schema): void
    {
        // Restaurer l'ancienne contrainte unique (bloque tous les statuts)
        $this->addSql('CREATE UNIQUE INDEX unique_type_date ON game (type, date)');
    }
}
