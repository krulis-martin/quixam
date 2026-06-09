<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260609154658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ip_address (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', ip_address VARCHAR(255) NOT NULL, hostname VARCHAR(255) NOT NULL, location VARCHAR(255) NOT NULL, seating_type VARCHAR(255) NOT NULL, row INT NOT NULL, `column` INT NOT NULL, UNIQUE INDEX UNIQ_22FFD58C22FFD58C (ip_address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ip_address');
    }
}
