<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout d\'index de performance sur game, activity, achievement et user';
    }

    public function up(Schema $schema): void
    {
        $gameTable = $schema->getTable('game');
        if (!$gameTable->hasIndex('IDX_game_status_date')) {
            $this->addSql('CREATE INDEX IDX_game_status_date ON game (status, date)');
        }
        if (!$gameTable->hasIndex('IDX_game_type_status_date')) {
            $this->addSql('CREATE INDEX IDX_game_type_status_date ON game (type, status, date)');
        }

        $activityTable = $schema->getTable('activity');
        if (!$activityTable->hasIndex('IDX_activity_player_type_created')) {
            $this->addSql('CREATE INDEX IDX_activity_player_type_created ON activity (player_id, type, created_at)');
        }

        $achievementTable = $schema->getTable('achievement');
        if (!$achievementTable->hasIndex('IDX_achievement_player')) {
            $this->addSql('CREATE INDEX IDX_achievement_player ON achievement (player_id)');
        }
        if ($achievementTable->hasIndex('IDX_96737FF199E6F5DF')) {
            $this->addSql('DROP INDEX IDX_96737FF199E6F5DF ON achievement');
        }

        $userTable = $schema->getTable('user');
        if (!$userTable->hasIndex('IDX_user_score_created')) {
            $this->addSql('CREATE INDEX IDX_user_score_created ON user (score, created_at)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_game_status_date ON game');
        $this->addSql('DROP INDEX IDX_game_type_status_date ON game');
        $this->addSql('DROP INDEX IDX_activity_player_type_created ON activity');
        $this->addSql('DROP INDEX IDX_achievement_player ON achievement');
        $this->addSql('CREATE INDEX IDX_96737FF199E6F5DF ON achievement (player_id)');
        $this->addSql('DROP INDEX IDX_user_score_created ON user');
    }
}
