<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\QuestionService;

/**
 * Testes de perguntas e respostas ML — Fase 5.
 *
 * QuestionService aceita skipDbAutoConnect=true para instanciar sem DB.
 * Testes funcionais dependem de DB e conta ML ativa.
 *
 * @covers \App\Services\QuestionService
 */
class QuestionTest extends TestCase
{
    private bool $dbAvailable = false;
    private ?QuestionService $service = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->service     = new QuestionService(null, null, null, null, null, null, null, false);
            $this->dbAvailable = true;
        } catch (\Throwable) {
            try {
                $this->service     = new QuestionService(null, null, null, null, null, null, null, true);
                $this->dbAvailable = false;
            } catch (\Throwable) {
                $this->dbAvailable = false;
            }
        }
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testQuestionServiceClassExists(): void
    {
        $this->assertTrue(class_exists(QuestionService::class));
    }

    public function testQuestionServiceCanBeInstantiatedWithSkipDb(): void
    {
        $service = new QuestionService(null, null, null, null, null, null, null, true);
        $this->assertInstanceOf(QuestionService::class, $service);
    }

    /** @dataProvider questionMethodsProvider */
    public function testQuestionServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(QuestionService::class, $method),
            "QuestionService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function questionMethodsProvider(): array
    {
        return [
            'syncQuestions'           => ['syncQuestions'],
            'getQuestions'            => ['getQuestions'],
            'generateDraftAnswer'     => ['generateDraftAnswer'],
            'analyzeQuestion'         => ['analyzeQuestion'],
            'getQuestion'             => ['getQuestion'],
            'getQuestionFromDatabase' => ['getQuestionFromDatabase'],
            'answerQuestion'          => ['answerQuestion'],
            'deleteQuestion'          => ['deleteQuestion'],
            'getUnansweredCount'      => ['getUnansweredCount'],
            'saveQuestionToDatabase'  => ['saveQuestionToDatabase'],
        ];
    }

    // ------------------------------------------------------------------
    // Testes funcionais (requerem DB)
    // ------------------------------------------------------------------

    public function testGetQuestionsReturnsArrayWhenDbAvailable(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->service->getQuestions(['limit' => 1]);

        $this->assertIsArray($result);
    }

    public function testGetUnansweredCountReturnsIntWhenDbAvailable(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->service->getUnansweredCount();

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testGetQuestionForNonExistentIdReturnsErrorOrThrows(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        try {
            $result = $this->service->getQuestion('NONEXISTENT_999999');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Reflecao: assinaturas de metodos criticos
    // ------------------------------------------------------------------

    public function testAnswerQuestionAcceptsTwoStrings(): void
    {
        $ref    = new \ReflectionMethod(QuestionService::class, 'answerQuestion');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params));
        $this->assertSame('questionId', $params[0]->getName());
        $this->assertSame('text', $params[1]->getName());
    }
}
