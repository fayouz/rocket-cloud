<?php

namespace App\Share;

use App\Health\ServiceProbeInterface;

/** Rocket Mailer on the dashboard (share notifications). */
final class MailerProbe implements ServiceProbeInterface
{
    public function __construct(private readonly MailerClient $mailer)
    {
    }

    public function id(): string
    {
        return 'mailer';
    }

    public function label(): string
    {
        return 'Rocket Mailer (notifications)';
    }

    public function targets(): iterable
    {
        if ($this->mailer->isConfigured()) {
            yield 'api' => ['name' => 'Rocket Mailer', 'check' => $this->mailer->ping(...)];
        }
    }
}
