<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\Game\GameIdeaRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MoviePopularityCheckRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testIdeaCannotBeEmpty(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = '';

        $violations = $this->validator->validate($dto);

        $this->assertGreaterThanOrEqual(1, $violations->count());
        // Vérifier qu'au moins une violation contient le message approprié
        $messages = array_map(fn ($v) => $v->getMessage(), iterator_to_array($violations));
        $this->assertContains('L\'idée ne peut pas être vide', $messages);
    }

    public function testIdeaCannotBeShorterThan10Characters(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = 'Court'; // 5 caractères

        $violations = $this->validator->validate($dto);

        $this->assertGreaterThanOrEqual(1, $violations->count());
        $this->assertStringContainsString('10', $violations[0]->getMessage());
    }

    public function testIdeaCannotBeLongerThan500Characters(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = str_repeat('a', 501); // 501 caractères

        $violations = $this->validator->validate($dto);

        $this->assertGreaterThanOrEqual(1, $violations->count());
        $this->assertStringContainsString('500', $violations[0]->getMessage());
    }

    public function testIdeaAcceptsValidInput(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = 'Une fusion épique entre Matrix et Le Seigneur des Anneaux';

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testIdeaAcceptsExactly10Characters(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = '1234567890'; // Exactement 10

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testIdeaAcceptsExactly500Characters(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = str_repeat('a', 500); // Exactement 500

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testIdeaAcceptsSpecialCharacters(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = 'Matrix + LOTR = Évasion épique !? 🎬';

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testIdeaAcceptsMultilineText(): void
    {
        $dto = new GameIdeaRequest();
        $dto->idea = "Une fusion entre Matrix et LOTR\nAvec des rebondissements incroyables";

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }
}
