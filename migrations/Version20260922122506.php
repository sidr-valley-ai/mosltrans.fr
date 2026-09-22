<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922122506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create status history table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE status_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, old_status VARCHAR(255) NOT NULL, new_status VARCHAR(255) NOT NULL, changed_at DATETIME NOT NULL, lead_id INTEGER NOT NULL, CONSTRAINT FK_2F6A07CE55458D FOREIGN KEY (lead_id) REFERENCES lead (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2F6A07CE55458D ON status_history (lead_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE status_history');
    }
}