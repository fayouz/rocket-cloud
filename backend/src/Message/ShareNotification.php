<?php

namespace App\Message;

/** Emails the recipients of a share link (worker). */
final readonly class ShareNotification implements AsyncMessageInterface
{
    public function __construct(public string $shareId)
    {
    }
}
