<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ShareLink;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Message\ShareNotification;
use Rocket\Core\Security\ActorContext;
use App\Share\MailerClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * New share link: owned by the user, password hashed, recipients notified by email (Rocket Mailer, in the background).
 *
 * @implements ProcessorInterface<ShareLink, ShareLink>
 */
final class ShareLinkCreateProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<ShareLink, ShareLink> $persist */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persist,
        private readonly ActorContext $actor,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly MessageBusInterface $bus,
        private readonly MailerClient $mailer,
        private readonly \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ShareLink) {
            return $this->persist->process($data, $operation, $uriVariables, $context);
        }
        $data->setOwner($this->actor->requireUser());
        $violations = $this->validator->validate($data);
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }
        if (null !== $data->getPassword()) {
            $data->setPasswordHash($this->hashers->getPasswordHasher('share')->hash($data->getPassword()));
            $data->setPassword(null);
        }
        if ([] !== $data->getRecipients()) {
            $data->setNotificationStatus($this->mailer->isConfigured() ? 'queued' : 'Rocket Mailer n’est pas configuré (ROCKET_MAILER_URL).');
        }

        $result = $this->persist->process($data, $operation, $uriVariables, $context);
        if ('queued' === $data->getNotificationStatus()) {
            $this->bus->dispatch(new ShareNotification((string) $data->getId()));
        }

        return $result;
    }
}
