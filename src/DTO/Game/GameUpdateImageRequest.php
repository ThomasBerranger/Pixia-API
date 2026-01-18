<?php

declare(strict_types=1);

namespace App\DTO\Game;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class GameUpdateImageRequest
{
    #[Assert\NotNull(message: 'Une image est requise.')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxSizeMessage: 'L\'image est trop volumineuse ({{ size }} {{ suffix }}). Taille maximale : {{ limit }} {{ suffix }}.',
        mimeTypesMessage: 'Format d\'image invalide. Formats acceptés : JPEG, PNG, WEBP.'
    )]
    public ?UploadedFile $image = null;
}
