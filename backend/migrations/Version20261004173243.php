<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004173243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les diapositives de hero et reprend le contenu déjà publié.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hero_slide (id INT AUTO_INCREMENT NOT NULL, eyebrow VARCHAR(160) DEFAULT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, button_label VARCHAR(80) DEFAULT NULL, button_url VARCHAR(500) DEFAULT NULL, content_position VARCHAR(20) NOT NULL, overlay VARCHAR(20) NOT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, section_id INT NOT NULL, image_id INT DEFAULT NULL, mobile_image_id INT DEFAULT NULL, INDEX IDX_EDD0E1A5D823E37A (section_id), INDEX IDX_EDD0E1A53DA5256D (image_id), INDEX IDX_EDD0E1A5F0928933 (mobile_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hero_slide ADD CONSTRAINT FK_EDD0E1A5D823E37A FOREIGN KEY (section_id) REFERENCES section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_slide ADD CONSTRAINT FK_EDD0E1A53DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero_slide ADD CONSTRAINT FK_EDD0E1A5F0928933 FOREIGN KEY (mobile_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql("INSERT INTO hero_slide (section_id, image_id, mobile_image_id, eyebrow, title, description, button_label, button_url, content_position, overlay, position, is_active, created_at, updated_at) SELECT s.id, s.image_id, NULL, s.eyebrow, COALESCE(NULLIF(s.title, ''), 'Diaré Groupe Industrie'), s.content, s.button_label, s.button_url, 'start', 'medium', 1, 1, NOW(), NOW() FROM section s WHERE s.type = 'hero' AND s.image_id IS NOT NULL");
        $this->addSql("INSERT INTO hero_slide (section_id, image_id, mobile_image_id, eyebrow, title, description, button_label, button_url, content_position, overlay, position, is_active, created_at, updated_at) SELECT s.id, p.main_image_id, NULL, c.name, p.name, p.short_description, 'Voir le produit', CONCAT('/nos-produits/', p.slug), CASE WHEN p.position = (SELECT MIN(fp.position) FROM product fp WHERE fp.main_image_id IS NOT NULL AND fp.is_published = 1 AND fp.is_featured = 1 AND fp.main_image_id <> s.image_id) THEN 'end' ELSE 'center' END, CASE WHEN p.position = (SELECT MIN(fp.position) FROM product fp WHERE fp.main_image_id IS NOT NULL AND fp.is_published = 1 AND fp.is_featured = 1 AND fp.main_image_id <> s.image_id) THEN 'soft' ELSE 'strong' END, p.position, 1, NOW(), NOW() FROM product p INNER JOIN section s ON s.type = 'hero' LEFT JOIN product_category c ON c.id = p.category_id WHERE p.main_image_id IS NOT NULL AND p.is_published = 1 AND p.is_featured = 1 AND p.main_image_id <> s.image_id");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hero_slide DROP FOREIGN KEY FK_EDD0E1A5D823E37A');
        $this->addSql('ALTER TABLE hero_slide DROP FOREIGN KEY FK_EDD0E1A53DA5256D');
        $this->addSql('ALTER TABLE hero_slide DROP FOREIGN KEY FK_EDD0E1A5F0928933');
        $this->addSql('DROP TABLE hero_slide');
    }
}
