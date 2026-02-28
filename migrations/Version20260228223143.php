<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228223143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cout_reparation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cout_reparation (
            id INT AUTO_INCREMENT NOT NULL,
            type_element VARCHAR(50) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            unite VARCHAR(50) NOT NULL DEFAULT \'forfait\',
            prix_unitaire DOUBLE PRECISION NOT NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cout_reparation');
    }
}
