<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226162244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nettoyage BDD: photo cascade+updatedAt, telephone varchar, element.ordre, drop photo_principale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logement DROP photo_principale');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784181F1F2A24');
        $this->addSql('ALTER TABLE photo ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE photo SET updated_at = created_at');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181F1F2A24 FOREIGN KEY (element_id) REFERENCES element (id) ON DELETE CASCADE');
        $this->addSql('UPDATE element SET ordre = 0 WHERE ordre IS NULL');
        $this->addSql('ALTER TABLE element CHANGE ordre ordre INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE user CHANGE telephone telephone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logement ADD photo_principale VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784181F1F2A24');
        $this->addSql('ALTER TABLE photo DROP updated_at');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181F1F2A24 FOREIGN KEY (element_id) REFERENCES element (id)');
        $this->addSql('ALTER TABLE element CHANGE ordre ordre INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE telephone telephone VARCHAR(255) DEFAULT NULL');
    }
}
