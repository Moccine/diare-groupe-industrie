<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un bouton secondaire optionnel aux diapositives du hero.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_slide ADD secondary_button_label VARCHAR(80) DEFAULT NULL, ADD secondary_button_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_slide DROP secondary_button_label, DROP secondary_button_url');
    }
}
