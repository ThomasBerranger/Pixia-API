<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Activity;

use App\DTO\Activity\ActivityIndexRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ActivityIndexRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // ========== Valeurs par défaut ==========

    public function testDefaultValuesAreValid(): void
    {
        $dto = new ActivityIndexRequest();

        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors);
        $this->assertSame(1, $dto->page);
        $this->assertNull($dto->search);
    }

    // ========== Page ==========

    public function testValidPage(): void
    {
        $dto = new ActivityIndexRequest(5, null);

        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors);
    }

    public function testZeroPageIsInvalid(): void
    {
        $dto = new ActivityIndexRequest(0, null);

        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function testNegativePageIsInvalid(): void
    {
        $dto = new ActivityIndexRequest(-1, null);

        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    // ========== Search ==========

    public function testNullSearchIsValid(): void
    {
        $dto = new ActivityIndexRequest(1, null);

        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors);
    }

    public function testSearchAtMaxLengthIsValid(): void
    {
        $dto = new ActivityIndexRequest(1, str_repeat('a', 255));

        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors);
    }

    public function testSearchTooLongIsInvalid(): void
    {
        $dto = new ActivityIndexRequest(1, str_repeat('a', 256));

        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }
}
