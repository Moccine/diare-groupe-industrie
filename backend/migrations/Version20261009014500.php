<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009014500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les candidatures aux offres d’emploi, sans supprimer les offres existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_application (id INT AUTO_INCREMENT NOT NULL, job_title_snapshot VARCHAR(180) NOT NULL, contract_type_snapshot VARCHAR(40) DEFAULT NULL, location_snapshot VARCHAR(120) DEFAULT NULL, is_spontaneous TINYINT NOT NULL, desired_role VARCHAR(120) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(40) NOT NULL, motivation LONGTEXT NOT NULL, linkedin_url VARCHAR(500) DEFAULT NULL, availability VARCHAR(120) DEFAULT NULL, cv_stored_filename VARCHAR(80) NOT NULL, cv_original_filename VARCHAR(180) NOT NULL, cv_mime_type VARCHAR(80) NOT NULL, cv_size INT NOT NULL, status VARCHAR(20) NOT NULL, internal_notes LONGTEXT DEFAULT NULL, consented_at DATETIME NOT NULL, consent_version VARCHAR(40) NOT NULL, processed_at DATETIME DEFAULT NULL, retention_until DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, job_offer_id INT DEFAULT NULL, INDEX idx_job_application_created_at (created_at), INDEX idx_job_application_status (status), INDEX idx_job_application_job_offer (job_offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_C737C6883481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY FK_C737C6883481D195');
        $this->addSql('DROP TABLE job_application');
    }
}
