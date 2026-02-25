<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117014536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cle (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, nombre INT NOT NULL, commentaire LONGTEXT DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, etat_des_lieux_id INT NOT NULL, INDEX IDX_41401D171EA7F144 (etat_des_lieux_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE compteur (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, numero VARCHAR(50) DEFAULT NULL, index_value VARCHAR(50) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, photos JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, etat_des_lieux_id INT NOT NULL, INDEX IDX_4D021BD51EA7F144 (etat_des_lieux_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE element (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, nom VARCHAR(255) NOT NULL, etat VARCHAR(20) NOT NULL, observations LONGTEXT DEFAULT NULL, degradations JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, piece_id INT NOT NULL, INDEX IDX_41405E39C40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE etat_des_lieux (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, date_realisation DATE NOT NULL, locataire_nom VARCHAR(255) NOT NULL, locataire_email VARCHAR(255) DEFAULT NULL, locataire_telephone VARCHAR(255) DEFAULT NULL, observations_generales LONGTEXT DEFAULT NULL, statut VARCHAR(20) DEFAULT NULL, signature_bailleur LONGTEXT DEFAULT NULL, signature_locataire LONGTEXT DEFAULT NULL, date_signature_bailleur DATETIME DEFAULT NULL, date_signature_locataire DATETIME DEFAULT NULL, code_validation VARCHAR(6) DEFAULT NULL, code_validation_expire_at DATETIME DEFAULT NULL, code_validation_verifie_at DATETIME DEFAULT NULL, signature_token VARCHAR(64) DEFAULT NULL, signature_token_expire_at DATETIME DEFAULT NULL, signature_ip VARCHAR(45) DEFAULT NULL, signature_user_agent LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, logement_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_F721031258ABF955 (logement_id), INDEX IDX_F7210312A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE logement (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, code_postal VARCHAR(255) NOT NULL, ville VARCHAR(255) NOT NULL, type VARCHAR(50) DEFAULT NULL, surface DOUBLE PRECISION DEFAULT NULL, nb_pieces INT NOT NULL, description LONGTEXT DEFAULT NULL, photo_principale VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_F0FD4457A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE photo (id INT AUTO_INCREMENT NOT NULL, chemin VARCHAR(255) NOT NULL, legende VARCHAR(255) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, ordre INT NOT NULL, created_at DATETIME NOT NULL, element_id INT NOT NULL, INDEX IDX_14B784181F1F2A24 (element_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE piece (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, ordre INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, etat_des_lieux_id INT NOT NULL, INDEX IDX_44CA0B231EA7F144 (etat_des_lieux_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, telephone VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE cle ADD CONSTRAINT FK_41401D171EA7F144 FOREIGN KEY (etat_des_lieux_id) REFERENCES etat_des_lieux (id)');
        $this->addSql('ALTER TABLE compteur ADD CONSTRAINT FK_4D021BD51EA7F144 FOREIGN KEY (etat_des_lieux_id) REFERENCES etat_des_lieux (id)');
        $this->addSql('ALTER TABLE element ADD CONSTRAINT FK_41405E39C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id)');
        $this->addSql('ALTER TABLE etat_des_lieux ADD CONSTRAINT FK_F721031258ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id)');
        $this->addSql('ALTER TABLE etat_des_lieux ADD CONSTRAINT FK_F7210312A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE logement ADD CONSTRAINT FK_F0FD4457A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181F1F2A24 FOREIGN KEY (element_id) REFERENCES element (id)');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B231EA7F144 FOREIGN KEY (etat_des_lieux_id) REFERENCES etat_des_lieux (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cle DROP FOREIGN KEY FK_41401D171EA7F144');
        $this->addSql('ALTER TABLE compteur DROP FOREIGN KEY FK_4D021BD51EA7F144');
        $this->addSql('ALTER TABLE element DROP FOREIGN KEY FK_41405E39C40FCFA8');
        $this->addSql('ALTER TABLE etat_des_lieux DROP FOREIGN KEY FK_F721031258ABF955');
        $this->addSql('ALTER TABLE etat_des_lieux DROP FOREIGN KEY FK_F7210312A76ED395');
        $this->addSql('ALTER TABLE logement DROP FOREIGN KEY FK_F0FD4457A76ED395');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784181F1F2A24');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B231EA7F144');
        $this->addSql('DROP TABLE cle');
        $this->addSql('DROP TABLE compteur');
        $this->addSql('DROP TABLE element');
        $this->addSql('DROP TABLE etat_des_lieux');
        $this->addSql('DROP TABLE logement');
        $this->addSql('DROP TABLE photo');
        $this->addSql('DROP TABLE piece');
        $this->addSql('DROP TABLE user');
    }
}
