<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929081347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table email_history (historique des e-mails envoyés aux leads)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL, success BOOLEAN NOT NULL, error CLOB DEFAULT NULL, lead_id INTEGER NOT NULL, CONSTRAINT FK_9A7A188455458D FOREIGN KEY (lead_id) REFERENCES lead (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9A7A188455458D ON email_history (lead_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_history');
    }
}
