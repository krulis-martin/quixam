<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415152609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer CHANGE answer answer TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE question ADD points_per_item INT NOT NULL, ADD items_count INT NOT NULL');
        $this->addSql('ALTER TABLE template_questions_group ADD points_per_item INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer CHANGE answer answer TEXT NOT NULL');
        $this->addSql('ALTER TABLE question DROP points_per_item, DROP items_count');
        $this->addSql('ALTER TABLE template_questions_group DROP points_per_item');
    }
}
