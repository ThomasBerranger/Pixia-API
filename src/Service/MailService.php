<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contribution;
use App\Entity\User;
use App\Enum\ContributionType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

readonly class MailService
{
    public const string NAME = 'Pixia';
    public const string PIXIA_MAIL = 'mirage.pixia@gmail.com';
    public const string NO_REPLY_MAIL = 'noreply@pixia.fun';

    public function __construct(
        private MailerInterface     $mailer,
        private ResetPasswordHelperInterface $resetPasswordHelper,
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendContributionMail(string $username, Contribution $contribution): void
    {
        $email = new Email()
            ->from(new Address(self::NO_REPLY_MAIL, self::NAME))
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

    /**
     * @throws ResetPasswordExceptionInterface
     * @throws TransportExceptionInterface
     * @throws TooManyPasswordRequestsException
     */
    public function sendResetPasswordMail(User $user): void
    {
        $resetToken = $this->resetPasswordHelper->generateResetToken($user);

        $resetUrl = 'https://pixia.fun/reset-password/' . $resetToken->getToken();

        $email = new TemplatedEmail()
            ->from(new Address(self::NO_REPLY_MAIL, self::NAME))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe Pixia')
            ->htmlTemplate('reset_password.html.twig')
            ->context([
                'resetUrl' => $resetUrl,
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }
}
