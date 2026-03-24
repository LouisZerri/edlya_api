<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324132235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activation_code table and is_verified field on user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activation_code (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, code VARCHAR(8) NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FA574C9A77153098 (code), INDEX idx_activation_code_email (email), INDEX idx_activation_code_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL');
        // Les comptes existants sont vérifiés
        $this->addSql('UPDATE user SET is_verified = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activation_code');
        $this->addSql('ALTER TABLE user DROP is_verified');
    }
}
