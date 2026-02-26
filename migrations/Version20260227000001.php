<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonnes signature (pour Laravel web) et remember_token (pour auth Laravel)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etat_des_lieux ADD code_validation VARCHAR(6) DEFAULT NULL, ADD code_validation_expire_at DATETIME DEFAULT NULL, ADD code_validation_verifie_at DATETIME DEFAULT NULL, ADD signature_token VARCHAR(64) DEFAULT NULL, ADD signature_token_expire_at DATETIME DEFAULT NULL, ADD date_signature DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD remember_token VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etat_des_lieux DROP code_validation, DROP code_validation_expire_at, DROP code_validation_verifie_at, DROP signature_token, DROP signature_token_expire_at, DROP date_signature');
        $this->addSql('ALTER TABLE user DROP remember_token');
    }
}
