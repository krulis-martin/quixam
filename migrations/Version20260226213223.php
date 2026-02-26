<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226213223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE template_test_owner (template_test_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_E67505B84DE57F60 (template_test_id), INDEX IDX_E67505B8A76ED395 (user_id), PRIMARY KEY(template_test_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE template_test_owner ADD CONSTRAINT FK_E67505B84DE57F60 FOREIGN KEY (template_test_id) REFERENCES template_test (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE template_test_owner ADD CONSTRAINT FK_E67505B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answer ADD evaluation_updated_at DATETIME DEFAULT NULL, ADD auto_points INT DEFAULT NULL, ADD correctness DOUBLE PRECISION DEFAULT NULL, ADD public_comment TEXT NOT NULL, ADD private_comment TEXT NOT NULL, CHANGE answer answer TEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template_test_owner DROP FOREIGN KEY FK_E67505B84DE57F60');
        $this->addSql('ALTER TABLE template_test_owner DROP FOREIGN KEY FK_E67505B8A76ED395');
        $this->addSql('DROP TABLE template_test_owner');
        $this->addSql('ALTER TABLE answer DROP evaluation_updated_at, DROP auto_points, DROP correctness, DROP public_comment, DROP private_comment, CHANGE answer answer VARCHAR(255) NOT NULL');
    }
}
