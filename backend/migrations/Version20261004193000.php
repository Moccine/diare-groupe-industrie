<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la carte, les offres d’emploi, les pictogrammes de catégories et la page Nous rejoindre.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD map_latitude DECIMAL(9, 6) DEFAULT NULL, ADD map_longitude DECIMAL(9, 6) DEFAULT NULL, ADD map_zoom INT DEFAULT NULL, ADD map_label VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_category ADD icon VARCHAR(20) DEFAULT \'generic\' NOT NULL, ADD accent_color VARCHAR(20) DEFAULT \'forest\' NOT NULL');
        $this->addSql('UPDATE product_category SET icon = \'milk\', accent_color = \'forest\' WHERE slug = \'lait\'');
        $this->addSql('UPDATE product_category SET icon = \'biscuit\', accent_color = \'lime\' WHERE slug = \'biscuits\'');
        $this->addSql('UPDATE product_category SET icon = \'sugar\', accent_color = \'deep\' WHERE slug = \'sucre\'');
        $this->addSql('ALTER TABLE product_category ALTER icon DROP DEFAULT, ALTER accent_color DROP DEFAULT');
        $this->addSql('CREATE TABLE job_offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, department VARCHAR(120) DEFAULT NULL, location VARCHAR(120) DEFAULT NULL, contract_type VARCHAR(20) NOT NULL, short_description LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, requirements LONGTEXT DEFAULT NULL, application_email VARCHAR(180) DEFAULT NULL, application_url VARCHAR(500) DEFAULT NULL, experience_level VARCHAR(80) DEFAULT NULL, salary_label VARCHAR(120) DEFAULT NULL, published_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, is_published TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_job_offer_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO page (title, slug, menu_title, meta_title, meta_description, is_published, show_in_menu, menu_position, created_at, updated_at) SELECT \'Rejoignez DGI\', \'nous-rejoindre\', \'Nous rejoindre\', \'Rejoignez DGI — Diaré Groupe Industrie\', \'Les offres d’emploi de Diaré Groupe Industrie.\', 1, 1, 8, NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM page WHERE slug = \'nous-rejoindre\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job_offer');
        $this->addSql('ALTER TABLE product_category DROP icon, DROP accent_color');
        $this->addSql('ALTER TABLE site_settings DROP map_latitude, DROP map_longitude, DROP map_zoom, DROP map_label');
        $this->addSql('DELETE FROM page WHERE slug = \'nous-rejoindre\'');
    }
}
