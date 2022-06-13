<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\TemplateTests;
use App\Model\Repository\TemplateQuestions;
use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestion;
use App\Helpers\TestOrchestrator;
use Nette;
use Nette\Application\UI\Form;
use Nette\Bridges\ApplicationLatte\LatteFactory;

final class TemplatePresenter extends AuthenticatedPresenter
{
    /** @var TemplateTests @inject */
    public $templateTests;

    /** @var TemplateQuestions @inject */
    public $templateQuestions;

    /** @var TestOrchestrator @inject */
    public $testOrchestrator;

    /** @var LatteFactory @inject */
    public $latteFactory;

    private function getCurrentQuestion(TemplateQuestion $question): TemplateQuestion
    {
        $previous = $question;
        do {
            $question = $previous;
            $previous = $this->templateQuestions->findOneByEvenIfDeleted([ 'createdFrom' => $question->getId() ]);
        } while ($previous);
        return $question;
    }

    public function checkDefault(): bool
    {
        return false; // only admin is accepted
    }

    public function renderDefault(): void
    {
        $this->template->locale = $this->selectedLocale;
        $this->template->tests = $this->templateTests->findAll();
    }

    public function checkQuestion(string $id): bool
    {
        return false; // only admin is accepted
    }

    public function renderQuestion(string $id, int $seed = 0): void
    {
        $this->template->locale = $this->selectedLocale;

        $question = $this->templateQuestions->findEvenIfDeletedOrThrow($id);
        $this->template->question = $question;

        $this->template->seed = $seed;

        $type = $question->getType();
        $questionData = $this->testOrchestrator->instantiateQuestionData(
            $type,
            $question->getData(),
            $seed
        );
        $engine = $this->latteFactory->create();

        $this->template->text = $questionData->getText($this->selectedLocale);
        $this->template->result = $questionData->renderResultContent(
            $engine,
            $this->selectedLocale,
            $questionData->getCorrectAnswer(),
            true
        );

        if ($question->getDeletedAt()) {
            $this->template->currentQuestion = $this->getCurrentQuestion($question);
        }
    }
}
