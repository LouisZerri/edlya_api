<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260124084248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE partage (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, email VARCHAR(255) DEFAULT NULL, type VARCHAR(10) NOT NULL, expire_at DATETIME NOT NULL, consulte_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, etat_des_lieux_id INT NOT NULL, UNIQUE INDEX UNIQ_8B929E6E5F37A13B (token), INDEX IDX_8B929E6E1EA7F144 (etat_des_lieux_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE partage ADD CONSTRAINT FK_8B929E6E1EA7F144 FOREIGN KEY (etat_des_lieux_id) REFERENCES etat_des_lieux (id)');
        $this->addSql('ALTER TABLE user ADD role VARCHAR(20) NOT NULL, ADD entreprise VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE partage DROP FOREIGN KEY FK_8B929E6E1EA7F144');
        $this->addSql('DROP TABLE partage');
        $this->addSql('ALTER TABLE user DROP role, DROP entreprise');
    }
}
