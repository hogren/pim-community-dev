<?php

declare(strict_types=1);

namespace Akeneo\Pim\Automation\IdentifierGenerator\Infrastructure\Subscriber;

use Akeneo\Pim\Automation\IdentifierGenerator\Application\Generate\GenerateIdentifierCommand;
use Akeneo\Pim\Automation\IdentifierGenerator\Application\Generate\GenerateIdentifierHandler;
use Akeneo\Pim\Automation\IdentifierGenerator\Application\Validation\Error;
use Akeneo\Pim\Automation\IdentifierGenerator\Application\Validation\ErrorList;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Exception\UnableToSetIdentifierException;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\IdentifierGenerator;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\ProductProjection;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Repository\IdentifierGeneratorRepository;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\Product\UniqueProductEntity;
use Akeneo\Pim\Enrichment\Component\Product\Value\ScalarValue;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

/**
 * @copyright 2022 Akeneo SAS (https://www.akeneo.com)
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class SetIdentifiersSubscriber implements EventSubscriberInterface
{
    /** @var IdentifierGenerator[]|null */
    private ?array $identifierGenerators = null;

    public function __construct(
        private IdentifierGeneratorRepository $identifierGeneratorRepository,
        private GenerateIdentifierHandler $generateIdentifierCommandHandler,
        private ValidatorInterface $validator,
        private MetadataFactoryInterface $metadataFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            /**
             * These events have to be executed
             * - after AddDefaultValuesSubscriber (it adds default values from parent and may set values for the
             *   computation of the match or the generation)
             * - before ComputeEntityRawValuesSubscriber (it generates the raw_values)
             */
            StorageEvents::PRE_SAVE => ['setIdentifier', 90],
            StorageEvents::PRE_SAVE_ALL => ['setIdentifiers', 90],
        ];
    }

    public function setIdentifier(GenericEvent $event): void
    {
        $object = $event->getSubject();
        if (!$object instanceof ProductInterface) {
            return;
        }

        $this->generateIdentifier($object);
    }

    public function setIdentifiers(GenericEvent $event): void
    {
        if (!\is_array($event->getSubject())) {
            return;
        }
        foreach ($event->getSubject() as $subject) {
            if (!$subject instanceof ProductInterface) {
                return;
            }
        }

        foreach ($event->getSubject() as $product) {
            $this->generateIdentifier($product);
        }
    }

    private function generateIdentifier(ProductInterface $product): void
    {
        foreach ($this->getIdentifierGenerators() as $identifierGenerator) {
            $identifier = null;
            $identifierValue = $product->getValue($identifierGenerator->target()->asString());
            if (null !== $identifierValue) {
                Assert::isInstanceOf($identifierValue, ScalarValue::class);
                $identifier = $identifierValue->getData();
                Assert::string($identifier);
            }
            $productProjection = new ProductProjection($identifier);
            if ($identifierGenerator->match($productProjection)) {
                try {
                    $this->setGeneratedIdentifier($identifierGenerator, $product);
                } catch (UnableToSetIdentifierException) {
                    // TODO CPM-807: A warning should be displayed during the Import
                    // TODO CPM-808: A warning should be displayed as flash message when saving from PEF
                }
            }
        }
    }

    private function setGeneratedIdentifier(
        IdentifierGenerator $identifierGenerator,
        ProductInterface $product
    ): void {
        $command = GenerateIdentifierCommand::fromIdentifierGenerator($identifierGenerator);
        $newIdentifier = ($this->generateIdentifierCommandHandler)($command);

        $value = ScalarValue::value($identifierGenerator->target()->asString(), $newIdentifier);
        Assert::isInstanceOf($value, ScalarValue::class);
        $product->addValue($value);
        $product->setIdentifier($newIdentifier);

        // Check if product identifier is unique
        $violations = $this->validator->validate($product, new UniqueProductEntity());
        if (\count($violations) === 0) {
            // Check if value matches the attribute constraints
            $attributeViolations = $this->validator->validate($value, $this->getConstraints($value));
            $violations->addAll($attributeViolations);
        }

        if (count($violations) > 0) {
            $product->removeValue($value);
            $product->setIdentifier(null);

            throw new UnableToSetIdentifierException(
                new ErrorList(\array_map(
                    fn (ConstraintViolationInterface $violation): Error => new Error(
                        (string) $violation->getMessage(),
                        $violation->getParameters(),
                        $violation->getPropertyPath()
                    ),
                    \iterator_to_array($violations)
                ))
            );
        } else {
            $violations = $this->validator->validate($product);
        }
    }

    /**
     * @return IdentifierGenerator[]
     */
    private function getIdentifierGenerators(): array
    {
        if (null === $this->identifierGenerators) {
            $this->identifierGenerators = $this->identifierGeneratorRepository->getAll();
        }

        return $this->identifierGenerators;
    }

    /**
     * @return Constraint[]
     */
    private function getConstraints(ScalarValue $value): array
    {
        $metadata = $this->metadataFactory->getMetadataFor($value);
        $membersMetadata = $metadata->getPropertyMetadata('data');
        $constraints = [];
        foreach ($membersMetadata as $memberMetadata) {
            $constraints = \array_merge($constraints, $memberMetadata->getConstraints());
        }

        return $constraints;
    }
}
