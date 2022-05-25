<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220525000724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE answer (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', question_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', answer VARCHAR(255) NOT NULL, evaluated_at DATETIME DEFAULT NULL, points INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_DADD4A251E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE enrolled_user (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', seed INT NOT NULL, score INT DEFAULT NULL, max_score INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_3A059C131E5D0459 (test_id), INDEX IDX_3A059C13A76ED395 (user_id), UNIQUE INDEX UNIQ_3A059C131E5D0459A76ED395 (test_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE enrollment_registration (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', email VARCHAR(255) DEFAULT NULL, external_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_C16C31631E5D0459 (test_id), INDEX IDX_C16C3163A76ED395 (user_id), UNIQUE INDEX UNIQ_C16C31631E5D0459A76ED395 (test_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', enrolled_user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', template_questions_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', template_question_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', last_answer_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', ordering INT NOT NULL, points INT NOT NULL, type VARCHAR(255) NOT NULL, caption VARCHAR(255) NOT NULL, data TEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_B6F7494ED5BFE9BA (enrolled_user_id), INDEX IDX_B6F7494EF9BBA6B4 (template_questions_group_id), INDEX IDX_B6F7494E15DEE2DB (template_question_id), UNIQUE INDEX UNIQ_B6F7494EBDFC9701 (last_answer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE template_question (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', questions_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', external_id VARCHAR(255) DEFAULT NULL, type VARCHAR(255) NOT NULL, caption VARCHAR(255) NOT NULL, data TEXT NOT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_E9A1793D3EA4CB4D (created_from_id), INDEX IDX_E9A1793D1E5D0459 (test_id), INDEX IDX_E9A1793D8C203BE2 (questions_group_id), INDEX external_id (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE template_questions_group (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', test_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', ordering INT NOT NULL, select_count INT NOT NULL, points INT NOT NULL, external_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_157F3C3D3EA4CB4D (created_from_id), INDEX IDX_157F3C3D1E5D0459 (test_id), INDEX external_id (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE template_test (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', caption VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, course_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX external_id (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_term (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', template_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', scheduled_at DATETIME DEFAULT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, location VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, note VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_A076CD425DA0FB8 (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_term_supervisor (test_term_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_44B1A6CD26DE134 (test_term_id), INDEX IDX_44B1A6CA76ED395 (user_id), PRIMARY KEY(test_term_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL, role VARCHAR(255) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, external_id VARCHAR(255) DEFAULT NULL, last_authentication_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D6499F75D7B0 (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A251E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enrolled_user ADD CONSTRAINT FK_3A059C131E5D0459 FOREIGN KEY (test_id) REFERENCES test_term (id)');
        $this->addSql('ALTER TABLE enrolled_user ADD CONSTRAINT FK_3A059C13A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE enrollment_registration ADD CONSTRAINT FK_C16C31631E5D0459 FOREIGN KEY (test_id) REFERENCES test_term (id)');
        $this->addSql('ALTER TABLE enrollment_registration ADD CONSTRAINT FK_C16C3163A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494ED5BFE9BA FOREIGN KEY (enrolled_user_id) REFERENCES enrolled_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EF9BBA6B4 FOREIGN KEY (template_questions_group_id) REFERENCES template_questions_group (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E15DEE2DB FOREIGN KEY (template_question_id) REFERENCES template_question (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EBDFC9701 FOREIGN KEY (last_answer_id) REFERENCES answer (id)');
        $this->addSql('ALTER TABLE template_question ADD CONSTRAINT FK_E9A1793D3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES template_question (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE template_question ADD CONSTRAINT FK_E9A1793D1E5D0459 FOREIGN KEY (test_id) REFERENCES template_test (id)');
        $this->addSql('ALTER TABLE template_question ADD CONSTRAINT FK_E9A1793D8C203BE2 FOREIGN KEY (questions_group_id) REFERENCES template_questions_group (id)');
        $this->addSql('ALTER TABLE template_questions_group ADD CONSTRAINT FK_157F3C3D3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES template_questions_group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE template_questions_group ADD CONSTRAINT FK_157F3C3D1E5D0459 FOREIGN KEY (test_id) REFERENCES template_test (id)');
        $this->addSql('ALTER TABLE test_term ADD CONSTRAINT FK_A076CD425DA0FB8 FOREIGN KEY (template_id) REFERENCES template_test (id)');
        $this->addSql('ALTER TABLE test_term_supervisor ADD CONSTRAINT FK_44B1A6CD26DE134 FOREIGN KEY (test_term_id) REFERENCES test_term (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test_term_supervisor ADD CONSTRAINT FK_44B1A6CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EBDFC9701');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494ED5BFE9BA');
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A251E27F6BF');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E15DEE2DB');
        $this->addSql('ALTER TABLE template_question DROP FOREIGN KEY FK_E9A1793D3EA4CB4D');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EF9BBA6B4');
        $this->addSql('ALTER TABLE template_question DROP FOREIGN KEY FK_E9A1793D8C203BE2');
        $this->addSql('ALTER TABLE template_questions_group DROP FOREIGN KEY FK_157F3C3D3EA4CB4D');
        $this->addSql('ALTER TABLE template_question DROP FOREIGN KEY FK_E9A1793D1E5D0459');
        $this->addSql('ALTER TABLE template_questions_group DROP FOREIGN KEY FK_157F3C3D1E5D0459');
        $this->addSql('ALTER TABLE test_term DROP FOREIGN KEY FK_A076CD425DA0FB8');
        $this->addSql('ALTER TABLE enrolled_user DROP FOREIGN KEY FK_3A059C131E5D0459');
        $this->addSql('ALTER TABLE enrollment_registration DROP FOREIGN KEY FK_C16C31631E5D0459');
        $this->addSql('ALTER TABLE test_term_supervisor DROP FOREIGN KEY FK_44B1A6CD26DE134');
        $this->addSql('ALTER TABLE enrolled_user DROP FOREIGN KEY FK_3A059C13A76ED395');
        $this->addSql('ALTER TABLE enrollment_registration DROP FOREIGN KEY FK_C16C3163A76ED395');
        $this->addSql('ALTER TABLE test_term_supervisor DROP FOREIGN KEY FK_44B1A6CA76ED395');
        $this->addSql('DROP TABLE answer');
        $this->addSql('DROP TABLE enrolled_user');
        $this->addSql('DROP TABLE enrollment_registration');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE template_question');
        $this->addSql('DROP TABLE template_questions_group');
        $this->addSql('DROP TABLE template_test');
        $this->addSql('DROP TABLE test_term');
        $this->addSql('DROP TABLE test_term_supervisor');
        $this->addSql('DROP TABLE user');
    }
}
