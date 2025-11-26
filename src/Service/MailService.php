<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contribution;
use App\Enum\ContributionType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class MailService
{
    public const string PIXIA_MAIL = 'mirage.pixia@gmail.com';
    public const string NO_REPLY_MAIL = 'noreply@pixia.fun';

    public function __construct(private MailerInterface $mailer)
    {
    }

    public function sendContributionMail(string $username, Contribution $contribution): void
    {
        $email = new Email()
            ->from(new Address(self::NO_REPLY_MAIL, 'Pixia'))
            ->to(self::PIXIA_MAIL)
            ->subject($contribution->getType() === ContributionType::GAME_SUGGESTION ? 'Proposition de fusion' : 'Bug remonté')
            ->html(
                '<h2>' . $username . ($contribution->getType() === ContributionType::GAME_SUGGESTION ? ' te propose une fusion' : ' te remonte un bug') . '</h2>
                       <p>' . $contribution->getTitle() . '</p>
                       <p>' . $contribution->getDescription() . '</p>
                       '
            );

        $this->mailer->send($email);
    }
}
