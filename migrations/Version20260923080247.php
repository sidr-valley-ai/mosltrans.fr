<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923080247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une date de relance aux leads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lead ADD COLUMN follow_up_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lead DROP COLUMN follow_up_at');
    }
}