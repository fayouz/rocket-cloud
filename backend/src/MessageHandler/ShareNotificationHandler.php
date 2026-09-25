<?php

namespace App\MessageHandler;

use App\Message\ShareNotification;
use App\Repository\ShareLinkRepository;
use App\Share\MailerClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ShareNotificationHandler
{
    public function __construct(
        private readonly ShareLinkRepository $shares,
        private readonly MailerClient $mailer,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'FRONTEND_URL')] private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(ShareNotification $message): void
    {
        $share = $this->shares->find($message->shareId);
        if (null === $share || [] === $share->getRecipients() || null === $share->getOwner()) {
            return;
        }
        $owner = $share->getOwner();
        $url = rtrim($this->frontendUrl, '/').'/s/'.$share->getToken();
        $e = static fn (?string $v) => htmlspecialchars((string) $v, \ENT_QUOTES);

        $html = '<p>Bonjour,</p>'
            .\sprintf('<p><strong>%s</strong> vous a partagé %s <strong>%s</strong>.</p>', $e($owner->getDisplayName()), null !== $share->getFile() ? 'le fichier' : 'le dossier', $e($share->getTargetName()))
            .(null !== $share->getMessage() ? '<blockquote style="border-left:3px solid #0ea5e9;margin:16px 0;padding:4px 12px;color:#334155;">'.nl2br($e($share->getMessage())).'</blockquote>' : '')
            .\sprintf('<p><a href="%s" style="background:#0ea5e9;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block;">Ouvrir le partage</a></p>', $e($url))
            .(null !== $share->getExpiresAt() ? '<p style="color:#64748b;font-size:13px;">Lien valable jusqu’au '.$share->getExpiresAt()->format('d/m/Y à H:i').'.</p>' : '')
            .($share->isPasswordProtected() ? '<p style="color:#64748b;font-size:13px;">Le lien est protégé par un mot de passe, communiqué séparément.</p>' : '');

        try {
            $this->mailer->send($owner->getEmail(), $share->getRecipients(), \sprintf('%s vous a partagé « %s »', $owner->getDisplayName(), $share->getTargetName()), $html);
            $share->setNotificationStatus('sent');
        } catch (\Throwable $e) {
            $share->setNotificationStatus($e->getMessage());
        }
        $this->em->flush();
    }
}
