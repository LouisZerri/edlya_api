<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223093957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression des colonnes de l\'ancien flux de signature distante (token, OTP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etat_des_lieux DROP code_validation, DROP code_validation_expire_at, DROP code_validation_verifie_at, DROP signature_token, DROP signature_token_expire_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE etat_des_lieux ADD code_validation VARCHAR(6) DEFAULT NULL, ADD code_validation_expire_at DATETIME DEFAULT NULL, ADD code_validation_verifie_at DATETIME DEFAULT NULL, ADD signature_token VARCHAR(64) DEFAULT NULL, ADD signature_token_expire_at DATETIME DEFAULT NULL');
    }
}
