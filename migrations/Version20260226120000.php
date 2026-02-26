<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression des colonnes role et entreprise de la table user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP role, DROP entreprise');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD role VARCHAR(20) NOT NULL DEFAULT \'bailleur\', ADD entreprise VARCHAR(255) DEFAULT NULL');
    }
}
