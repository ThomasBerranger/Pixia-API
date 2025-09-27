<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class GameService
{
    public function __construct(private Security $security, private EntityManagerInterface $entityManager)
    {
    }
}
