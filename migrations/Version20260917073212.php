<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917073212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status to leads';
    }

    public function up(Schema $schema): void
    {
       $this->addSql("ALTER TABLE lead ADD COLUMN status VARCHAR(255) NOT NULL 	DEFAULT 'nouveau'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lead DROP COLUMN status');
    }
}