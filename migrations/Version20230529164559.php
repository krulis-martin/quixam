<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230529164559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template_test ADD grading VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE test_term ADD grading VARCHAR(255) NOT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $grading = '{"1":17,"2":14,"3":11,"4":0}';
        $this->connection->executeStatement("UPDATE template_test SET grading = :grading", [ 'grading' => $grading ]);
        $this->connection->executeStatement("UPDATE test_term SET grading = :grading", [ 'grading' => $grading ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template_test DROP grading');
        $this->addSql('ALTER TABLE test_term DROP grading');
    }
}
