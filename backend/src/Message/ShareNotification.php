<?php

namespace App\Message;

use Rocket\Core\Message\AsyncMessageInterface;


/** Emails the recipients of a share link (worker). */
final readonly class ShareNotification implements AsyncMessageInterface
{
    public function __construct(public string $shareId)
    {
    }
}
