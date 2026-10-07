<?php

declare(strict_types=1);

namespace App\State\ParameterProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ParameterProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ReadLinkParameterProvider for a parameter that holds uuids, refusing anything else first (#1141).
 *
 * The lookup hands the raw value to Doctrine, which throws on a non-uuid and answers a 500. A query
 * constraint cannot stop it: parameter validation runs after this provider, on the entity it loaded.
 * So the shape is checked here, and a bad value is a 422 naming the parameter, as other query
 * parameters answer. A well-formed but unknown id is left to the lookup.
 *
 * The messages come from the parameter's `invalid_message` and `not_found_message` extra properties,
 * and `list` says whether several ids may be given.
 */
final readonly class UuidLinkParameterProvider implements ParameterProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.state_provider.read_link')]
        private ParameterProviderInterface $readLinkParameterProvider,
    ) {
    }

    public function provide(Parameter $parameter, array $parameters = [], array $context = []): ?Operation
    {
        $value = $parameter->getValue();
        if (!$value instanceof ParameterNotFound && $value !== null && !$this->isWellFormed($parameter, $value)) {
            $message = $parameter->getExtraProperties()['invalid_message'] ?? 'Identifiant invalide';
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation($message, $message, [], null, $parameter->getKey(), $value, code: Assert\Uuid::INVALID_CHARACTERS_ERROR),
            ]));
        }

        // A list parameter given once is still a list: the filter it feeds reads one.
        if (is_string($value) && ($parameter->getExtraProperties()['list'] ?? false)) {
            $parameter->setValue([$value]);
        }

        try {
            $operation = $this->readLinkParameterProvider->provide($parameter, $parameters, $context);
        } catch (NotFoundHttpException $exception) {
            // The lookup's own message is about link security, which says nothing to a client.
            throw new NotFoundHttpException($parameter->getExtraProperties()['not_found_message'] ?? 'Introuvable', $exception);
        }

        // The lookup leaves a null for each id of a list it could not find; the filter wants only what exists.
        $found = $parameter->getValue();
        if (is_array($found)) {
            $parameter->setValue(array_values(array_filter($found, static fn (mixed $entity): bool => $entity !== null)));
        }

        return $operation;
    }

    /**
     * One uuid, or a plain list of them where the parameter says `list` (the lookup turns a list into a
     * list of entities, which a single-valued filter cannot take). A keyed array is never well formed.
     */
    private function isWellFormed(Parameter $parameter, mixed $value): bool
    {
        if (is_array($value)) {
            if (!($parameter->getExtraProperties()['list'] ?? false) || !array_is_list($value)) {
                return false;
            }

            return array_all($value, static fn (mixed $id): bool => is_string($id) && Uuid::isValid($id));
        }

        return is_string($value) && Uuid::isValid($value);
    }
}
