<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le jeton de réinitialisation du mot de passe administrateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD reset_token VARCHAR(64) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_reset_token ON app_user (reset_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_reset_token ON app_user');
        $this->addSql('ALTER TABLE app_user DROP reset_token, DROP reset_token_expires_at');
    }
}
