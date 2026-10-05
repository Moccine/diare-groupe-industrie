<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004140754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact_request (id INT AUTO_INCREMENT NOT NULL, last_name VARCHAR(80) NOT NULL, first_name VARCHAR(80) NOT NULL, company VARCHAR(160) DEFAULT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(40) DEFAULT NULL, subject VARCHAR(180) NOT NULL, message LONGTEXT NOT NULL, consent TINYINT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, alt VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, caption LONGTEXT DEFAULT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE news (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, excerpt LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, published_at DATETIME NOT NULL, is_published TINYINT NOT NULL, meta_title VARCHAR(180) DEFAULT NULL, meta_description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, image_id INT DEFAULT NULL, INDEX IDX_1DD399503DA5256D (image_id), UNIQUE INDEX uniq_news_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, menu_title VARCHAR(120) DEFAULT NULL, meta_title VARCHAR(180) DEFAULT NULL, meta_description LONGTEXT DEFAULT NULL, is_published TINYINT NOT NULL, show_in_menu TINYINT NOT NULL, menu_position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_page_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE partner (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, url VARCHAR(500) DEFAULT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL, logo_id INT DEFAULT NULL, INDEX IDX_312B3E16F98F144A (logo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, short_description LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, is_featured TINYINT NOT NULL, is_published TINYINT NOT NULL, position INT NOT NULL, meta_title VARCHAR(180) DEFAULT NULL, meta_description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT DEFAULT NULL, main_image_id INT DEFAULT NULL, INDEX IDX_D34A04AD12469DE2 (category_id), INDEX IDX_D34A04ADE4873418 (main_image_id), UNIQUE INDEX uniq_product_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_gallery (product_id INT NOT NULL, media_id INT NOT NULL, INDEX IDX_96F2638A4584665A (product_id), INDEX IDX_96F2638AEA9FDD75 (media_id), PRIMARY KEY (product_id, media_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, position INT NOT NULL, is_published TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_product_category_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE section (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(40) NOT NULL, title VARCHAR(180) DEFAULT NULL, subtitle VARCHAR(255) DEFAULT NULL, eyebrow VARCHAR(160) DEFAULT NULL, content LONGTEXT DEFAULT NULL, button_label VARCHAR(80) DEFAULT NULL, button_url VARCHAR(500) DEFAULT NULL, secondary_button_label VARCHAR(80) DEFAULT NULL, secondary_button_url VARCHAR(500) DEFAULT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL, theme VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, page_id INT NOT NULL, image_id INT DEFAULT NULL, background_image_id INT DEFAULT NULL, INDEX IDX_2D737AEFC4663E4 (page_id), INDEX IDX_2D737AEF3DA5256D (image_id), INDEX IDX_2D737AEFE6DA28AA (background_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE section_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, text LONGTEXT DEFAULT NULL, position INT NOT NULL, section_id INT NOT NULL, image_id INT DEFAULT NULL, INDEX IDX_9AA5C915D823E37A (section_id), INDEX IDX_9AA5C9153DA5256D (image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site_settings (id INT AUTO_INCREMENT NOT NULL, company_name VARCHAR(180) NOT NULL, primary_color VARCHAR(7) NOT NULL, secondary_color VARCHAR(7) NOT NULL, accent_color VARCHAR(7) NOT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, facebook VARCHAR(500) DEFAULT NULL, linkedin VARCHAR(500) DEFAULT NULL, instagram VARCHAR(500) DEFAULT NULL, youtube VARCHAR(500) DEFAULT NULL, footer_text LONGTEXT DEFAULT NULL, copyright VARCHAR(255) DEFAULT NULL, header_cta_label VARCHAR(80) DEFAULT NULL, header_cta_url VARCHAR(500) DEFAULT NULL, meta_title VARCHAR(180) DEFAULT NULL, meta_description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, logo_id INT DEFAULT NULL, favicon_id INT DEFAULT NULL, INDEX IDX_E9081F1FF98F144A (logo_id), INDEX IDX_E9081F1FD78119FD (favicon_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE statistic (id INT AUTO_INCREMENT NOT NULL, value VARCHAR(80) NOT NULL, label VARCHAR(160) NOT NULL, description VARCHAR(255) DEFAULT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE news ADD CONSTRAINT FK_1DD399503DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE partner ADD CONSTRAINT FK_312B3E16F98F144A FOREIGN KEY (logo_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES product_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADE4873418 FOREIGN KEY (main_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_gallery ADD CONSTRAINT FK_96F2638A4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_gallery ADD CONSTRAINT FK_96F2638AEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE section ADD CONSTRAINT FK_2D737AEFC4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE section ADD CONSTRAINT FK_2D737AEF3DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE section ADD CONSTRAINT FK_2D737AEFE6DA28AA FOREIGN KEY (background_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE section_item ADD CONSTRAINT FK_9AA5C915D823E37A FOREIGN KEY (section_id) REFERENCES section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE section_item ADD CONSTRAINT FK_9AA5C9153DA5256D FOREIGN KEY (image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE site_settings ADD CONSTRAINT FK_E9081F1FF98F144A FOREIGN KEY (logo_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE site_settings ADD CONSTRAINT FK_E9081F1FD78119FD FOREIGN KEY (favicon_id) REFERENCES media (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE news DROP FOREIGN KEY FK_1DD399503DA5256D');
        $this->addSql('ALTER TABLE partner DROP FOREIGN KEY FK_312B3E16F98F144A');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADE4873418');
        $this->addSql('ALTER TABLE product_gallery DROP FOREIGN KEY FK_96F2638A4584665A');
        $this->addSql('ALTER TABLE product_gallery DROP FOREIGN KEY FK_96F2638AEA9FDD75');
        $this->addSql('ALTER TABLE section DROP FOREIGN KEY FK_2D737AEFC4663E4');
        $this->addSql('ALTER TABLE section DROP FOREIGN KEY FK_2D737AEF3DA5256D');
        $this->addSql('ALTER TABLE section DROP FOREIGN KEY FK_2D737AEFE6DA28AA');
        $this->addSql('ALTER TABLE section_item DROP FOREIGN KEY FK_9AA5C915D823E37A');
        $this->addSql('ALTER TABLE section_item DROP FOREIGN KEY FK_9AA5C9153DA5256D');
        $this->addSql('ALTER TABLE site_settings DROP FOREIGN KEY FK_E9081F1FF98F144A');
        $this->addSql('ALTER TABLE site_settings DROP FOREIGN KEY FK_E9081F1FD78119FD');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE contact_request');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE news');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE partner');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_gallery');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE section');
        $this->addSql('DROP TABLE section_item');
        $this->addSql('DROP TABLE site_settings');
        $this->addSql('DROP TABLE statistic');
    }
}
