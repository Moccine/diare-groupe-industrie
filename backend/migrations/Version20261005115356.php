<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005115356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’image et le texte de bannière sur les pages.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE page ADD banner_lead LONGTEXT DEFAULT NULL, ADD banner_image_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              page
            ADD
              CONSTRAINT FK_140AB6203F9CEB4E FOREIGN KEY (banner_image_id) REFERENCES media (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_140AB6203F9CEB4E ON page (banner_image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB6203F9CEB4E');
        $this->addSql('DROP INDEX IDX_140AB6203F9CEB4E ON page');
        $this->addSql('ALTER TABLE page DROP banner_lead, DROP banner_image_id');
    }
}
