<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230526172214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer ADD ip_address VARCHAR(255) NOT NULL, CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE question_id question_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE enrolled_user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE enrollment_registration CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE question CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE enrolled_user_id enrolled_user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE template_questions_group_id template_questions_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE template_question_id template_question_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE last_answer_id last_answer_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE template_question CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE questions_group_id questions_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE template_questions_group CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE template_test CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE test_term CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE template_id template_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE test_term_supervisor CHANGE test_term_id test_term_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer DROP ip_address, CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE question_id question_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE enrolled_user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE enrollment_registration CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE question CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE enrolled_user_id enrolled_user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE template_questions_group_id template_questions_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE template_question_id template_question_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE last_answer_id last_answer_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE template_question CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE questions_group_id questions_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE template_questions_group CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE test_id test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE template_test CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE test_term CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE template_id template_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE test_term_supervisor CHANGE test_term_id test_term_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
    }
}
