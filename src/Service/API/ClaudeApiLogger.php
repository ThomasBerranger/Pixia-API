<?php

declare(strict_types=1);

namespace App\Service\API;

use App\Entity\User;
use Psr\Log\LoggerInterface;

readonly class ClaudeApiLogger
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function logRequest(?User $user, string $input): void
    {
        $this->logger->info('=== CLAUDE API REQUEST ===', [
            'user_id' => $user?->getId()?->toRfc4122(),
            'username' => $user?->getUsername(),
            'input' => $input,
        ]);
    }

    public function logRawResponse(string $raw): void
    {
        $this->logger->info('Claude API raw response', [
            'raw' => $raw,
        ]);
    }

    public function logExtractedJson(string $extracted): void
    {
        $this->logger->info('Claude API extracted JSON', [
            'extracted' => $extracted,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function logParsedResult(array $result): void
    {
        $this->logger->info('Claude API parsed result', [
            'result' => $result,
        ]);
    }

    public function logError(\Throwable $e, string $content): void
    {
        $this->logger->error('Claude API error', [
            'error' => $e->getMessage(),
            'content' => $content,
        ]);
    }

    public function logEnd(): void
    {
        $this->logger->info('=== END CLAUDE API ===');
    }
}
