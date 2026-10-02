<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002112602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table envoi (suivi des livraisons par numéro de suivi)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE envoi (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reference VARCHAR(20) NOT NULL, ville_depart VARCHAR(255) NOT NULL, ville_arrivee VARCHAR(255) NOT NULL, latitude_depart DOUBLE PRECISION DEFAULT NULL, longitude_depart DOUBLE PRECISION DEFAULT NULL, latitude_arrivee DOUBLE PRECISION DEFAULT NULL, longitude_arrivee DOUBLE PRECISION DEFAULT NULL, etape VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, note_interne CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CA7E3566AEA34913 ON envoi (reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE envoi');
    }
}
