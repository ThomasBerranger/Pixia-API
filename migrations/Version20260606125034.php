<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606125034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_consent flag on user for email notifications';
    }

    public function up(Schema $schema): void
    {
        $userTable = $schema->getTable('user');

        if (!$userTable->hasColumn('email_consent')) {
            // Default to 1: existing users stay subscribed, opt-out happens through the signed unsubscribe link.
            $this->addSql('ALTER TABLE `user` ADD email_consent TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP email_consent');
    }
}
