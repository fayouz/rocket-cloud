<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Folder;
use ApiPlatform\Validator\Exception\ValidationException;
use Rocket\Core\Security\ActorContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * New resources belong to the user acting (or the user an application acts for).
 *
 * @implements ProcessorInterface<Folder, Folder>
 */
final class OwnedProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<object, object> $persist */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persist,
        private readonly ActorContext $actor,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (method_exists($data, 'setOwner') && method_exists($data, 'getOwner') && null === $data->getOwner()) {
            $data->setOwner($this->actor->requireUser());
            // Validated again now that the owner is known (parent folder of another user…).
            $violations = $this->validator->validate($data);
            if (\count($violations) > 0) {
                throw new ValidationException($violations);
            }
        }

        return $this->persist->process($data, $operation, $uriVariables, $context);
    }
}
