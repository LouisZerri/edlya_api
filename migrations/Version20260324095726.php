<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324095726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active field to user table and activate existing users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_active TINYINT(1) DEFAULT 0 NOT NULL');
        // Activer tous les comptes existants
        $this->addSql('UPDATE user SET is_active = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP is_active');
    }
}
