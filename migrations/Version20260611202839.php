<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611202839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add monthly_recap_sent_at on user to make the monthly recap mailing idempotent';
    }

    public function up(Schema $schema): void
    {
        $userTable = $schema->getTable('user');

        if (!$userTable->hasColumn('monthly_recap_sent_at')) {
            $this->addSql('ALTER TABLE `user` ADD monthly_recap_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP monthly_recap_sent_at');
    }
}
