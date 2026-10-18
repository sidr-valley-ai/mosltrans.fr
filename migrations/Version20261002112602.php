<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002112602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table envoi (suivi des livraisons par numéro de suivi)';
    }

    public function up(Schema $schema): void
    {
        // décrite avec l'API Schema plutôt qu'en SQL brut : Doctrine génère le SQL propre à la base utilisée
        $table = $schema->createTable('envoi');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('reference', Types::STRING, ['length' => 20]);
        $table->addColumn('ville_depart', Types::STRING, ['length' => 255]);
        $table->addColumn('ville_arrivee', Types::STRING, ['length' => 255]);
        $table->addColumn('latitude_depart', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('longitude_depart', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('latitude_arrivee', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('longitude_arrivee', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('etape', Types::STRING, ['length' => 20]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('note_interne', Types::TEXT, ['notnull' => false]);
        $table->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $table->addUniqueIndex(['reference'], 'UNIQ_CA7E3566AEA34913');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('envoi');
    }
}
