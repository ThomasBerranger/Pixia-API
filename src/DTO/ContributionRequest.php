<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Contribution;
use App\Enum\ContributionType;
use Symfony\Component\Validator\Constraints as Assert;

class ContributionRequest
{
    #[Assert\NotBlank(message: 'Un titre est requis.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Ton titre doit faire au moins {{ limit }} caractères.', maxMessage: 'Ton titre doit faire moins de {{ limit }} caractères.')]
    public string $title;

    #[Assert\NotBlank(message: 'Une description est requis.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Ta description doit faire au moins {{ limit }} caractères.', maxMessage: 'Ta description doit faire moins de {{ limit }} caractères.')]
    public string $description;

    #[Assert\NotBlank(message: 'Un type est requis.')]
    public ContributionType $type;

    public function toContribution(): Contribution
    {
        $contribution = new Contribution();

        $contribution
            ->setTitle($this->title)
            ->setDescription($this->description)
            ->setType($this->type);

        return $contribution;
    }
}
