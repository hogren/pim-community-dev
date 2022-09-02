<?php

namespace Specification\Akeneo\Pim\Enrichment\Component\Product\Updater\Setter;

use Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Updater\TwoWayAssociationUpdater;
use Akeneo\Pim\Enrichment\Component\Product\Association\MissingAssociationAdder;
use Akeneo\Pim\Enrichment\Component\Product\Model\AssociationInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\Group;
use Akeneo\Pim\Enrichment\Component\Product\Model\GroupInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\Product;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductAssociation;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModel;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelAssociation;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\GroupRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductModelRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Updater\Adder\AssociationFieldAdder;
use Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\AssociationFieldSetter;
use Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\FieldSetterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\SetterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Updater\TwoWayAssociationUpdaterInterface;
use Akeneo\Pim\Structure\Component\Model\AssociationType;
use Akeneo\Pim\Structure\Component\Model\AssociationTypeInterface;
use Akeneo\Pim\Structure\Component\Repository\AssociationTypeRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Ramsey\Uuid\Uuid;

class AssociationFieldSetterSpec extends ObjectBehavior
{
    function let(
        ProductRepositoryInterface $productRepository,
        ProductModelRepositoryInterface $productModelRepository,
        GroupRepositoryInterface $groupRepository,
        MissingAssociationAdder $missingAssociationAdder,
        TwoWayAssociationUpdaterInterface $twoWayAssociationUpdater,
        AssociationTypeRepositoryInterface $associationTypeRepository,
        AssociationTypeInterface $xsell,
    ) {
        $xsell->getCode()->willReturn('xsell');
        $xsell->isQuantified()->willReturn(false);
        $xsell->isTwoWay()->willReturn(false);
        $associationTypeRepository->findOneByIdentifier('xsell')->willReturn($xsell);

        $this->beConstructedWith(
            $productRepository,
            $productModelRepository,
            $groupRepository,
            $twoWayAssociationUpdater,
            $missingAssociationAdder,
            $associationTypeRepository,
            ['associations']
        );
    }

    function it_is_a_setter()
    {
        $this->shouldImplement(SetterInterface::class);
        $this->shouldImplement(FieldSetterInterface::class);
    }

    function it_supports_associations_field()
    {
        $this->supportsField('associations')->shouldReturn(true);
        $this->supportsField('groups')->shouldReturn(false);
    }

    function it_checks_valid_association_data_format(ProductInterface $product)
    {
        $this->shouldThrow(
            InvalidPropertyTypeException::arrayExpected(
                'associations',
                AssociationFieldSetter::class,
                'not an array'
            )
        )->during('setFieldData', [$product, 'associations', 'not an array']);

        $this->shouldThrow(
            InvalidPropertyTypeException::validArrayStructureExpected(
                'associations',
                'association format is not valid for the association type "0".',
                AssociationFieldSetter::class,
                [0 => []]
            )
        )->during('setFieldData', [$product, 'associations', [0 => []]]);

        $this->shouldThrow(
            InvalidPropertyTypeException::validArrayStructureExpected(
                'associations',
                'association format is not valid for the association type "assoc_type_code".',
                AssociationFieldSetter::class,
                ['assoc_type_code' => []]
            )
        )->during('setFieldData', [$product, 'associations', ['assoc_type_code' => []]]);

        $this->shouldThrow(
            InvalidPropertyTypeException::validArrayStructureExpected(
                'associations',
                'association format is not valid for the association type "assoc_type_code".',
                AssociationFieldSetter::class,
                ['assoc_type_code' => ['products' => [1], 'groups' => [], 'product_models' => [],]]
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['assoc_type_code' => ['products' => [1], 'groups' => [], 'product_models' => []]],
            ]
        );

        $this->shouldThrow(
            InvalidPropertyTypeException::validArrayStructureExpected(
                'associations',
                'association format is not valid for the association type "assoc_type_code".',
                AssociationFieldSetter::class,
                ['assoc_type_code' => ['products' => [], 'groups' => [2]]]
            )
        )->during(
            'setFieldData',
            [$product, 'associations', ['assoc_type_code' => ['products' => [], 'groups' => [2]]]]
        );

        $this->shouldThrow(
            new InvalidPropertyTypeException(
                'products',
                'string',
                AssociationFieldSetter::class,
                'Property "products" in association "assoc_type_code" expects an array as data, "string" given.',
                200
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['assoc_type_code' => ['products' => 'string', 'groups' => [], 'product_models' => []]],
            ]
        );
    }

    function it_sets_association_field(
        ProductRepositoryInterface $productRepository,
        IdentifiableObjectRepositoryInterface $productModelRepository,
        IdentifiableObjectRepositoryInterface $groupRepository,
        MissingAssociationAdder $missingAssociationAdder,
        ProductInterface $product,
        AssociationInterface $xsellAssociation,
        AssociationTypeInterface $xsell
    ) {
        $xsellAssociation->getAssociationType()->willReturn($xsell);

        $product->getAssociations()->willReturn(new ArrayCollection([$xsellAssociation->getWrappedObject()]));

        $assocProductOne = (new Product())->setIdentifier('assocProductOne');
        $assocProductTwo = (new Product())->setIdentifier('assocProductTwo');
        $assocProductThree = (new Product())->setIdentifier('assocProductThree');
        $assocProductModelOne = new ProductModel();
        $assocProductModelOne->setCode('assocProductModelOne');
        $assocProductModelTwo = new ProductModel();
        $assocProductModelTwo->setCode('assocProductModelTwo');
        $assocProductModelThree = new ProductModel();
        $assocProductModelThree->setCode('assocProductModelThree');
        $assocGroupOne = new Group();
        $assocGroupOne->setCode('assocGroupOne');
        $assocGroupTwo = new Group();
        $assocGroupTwo->setCode('assocGroupTwo');

        $productRepository->findOneByIdentifier('assocProductOne')->willReturn($assocProductOne);
        $productRepository->findOneByIdentifier('assocProductTwo')->willReturn($assocProductTwo);
        $productRepository->findOneByIdentifier('assocProductThree')->willReturn($assocProductThree);

        $productModelRepository->findOneByIdentifier('assocProductModelOne')->willReturn($assocProductModelOne);
        $productModelRepository->findOneByIdentifier('assocProductModelTwo')->willReturn($assocProductModelTwo);
        $productModelRepository->findOneByIdentifier('assocProductModelThree')->willReturn($assocProductModelThree);

        $groupRepository->findOneByIdentifier('assocGroupOne')->willReturn($assocGroupOne);
        $groupRepository->findOneByIdentifier('assocGroupTwo')->willReturn($assocGroupTwo);

        $missingAssociationAdder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociatedProducts('xsell')->willReturn(
            new ArrayCollection([$assocProductOne, $assocProductThree])
        );
        $product->getAssociatedProductModels('xsell')->willReturn(
            new ArrayCollection([$assocProductModelThree])
        );
        $product->getAssociatedGroups('xsell')->willReturn(
            new ArrayCollection([$assocGroupOne, $assocGroupTwo])
        );

        $product->removeAssociatedProduct($assocProductThree, 'xsell')->shouldBeCalled();
        $product->addAssociatedProduct($assocProductTwo, 'xsell')->shouldBeCalled();
        $product->removeAssociatedProductModel($assocProductModelThree, 'xsell')->shouldBeCalled();
        $product->addAssociatedProductModel($assocProductModelOne, 'xsell')->shouldBeCalled();
        $product->addAssociatedProductModel($assocProductModelTwo, 'xsell')->shouldBeCalled();
        $product->removeAssociatedGroup($assocGroupTwo, 'xsell')->shouldBeCalled();

        $product->removeAssociatedProduct($assocProductOne, 'xsell')->shouldNotBeCalled();
        $product->addAssociatedProduct($assocProductOne, 'xsell')->shouldNotBeCalled();
        $product->addAssociatedGroup(Argument::cetera())->shouldNotBeCalled();

        $this->setFieldData(
            $product,
            'associations',
            [
                'xsell' => [
                    'products' => ['assocProductOne', 'assocProductTwo'],
                    'product_models' => ['assocProductModelOne', 'assocProductModelTwo'],
                    'groups' => ['assocGroupOne'],
                ],
            ]
        );
    }

    function it_sets_association_field_with_uuids(
        ProductRepositoryInterface $productRepository,
        MissingAssociationAdder $missingAssociationAdder,
        AssociationTypeRepositoryInterface $associationTypeRepository,
        ProductInterface $product,
        AssociationInterface $xsellAssociation,
        AssociationTypeInterface $xsell
    ) {
        $xsellAssociation->getAssociationType()->willReturn($xsell);
        $associationTypeRepository->findOneByIdentifier('xsell')->willReturn($xsell);

        $product->getAssociations()->willReturn(new ArrayCollection([$xsellAssociation->getWrappedObject()]));

        $assocProductOne = (new Product())->setIdentifier('assocProductOne');
        $assocProductTwo = (new Product())->setIdentifier('assocProductTwo');
        $assocProductThree = (new Product())->setIdentifier('assocProductThree');

        $productRepository->find($assocProductOne->getUuid()->toString())->willReturn($assocProductOne);
        $productRepository->find($assocProductTwo->getUuid()->toString())->willReturn($assocProductTwo);
        $productRepository->find($assocProductThree->getUuid()->toString())->willReturn($assocProductThree);

        $missingAssociationAdder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociatedProducts('xsell')->willReturn(
            new ArrayCollection([$assocProductOne, $assocProductThree])
        );

        $product->removeAssociatedProduct($assocProductThree, 'xsell')->shouldBeCalled();
        $product->addAssociatedProduct($assocProductTwo, 'xsell')->shouldBeCalled();
        $product->removeAssociatedProduct($assocProductOne, 'xsell')->shouldNotBeCalled();
        $product->addAssociatedProduct($assocProductOne, 'xsell')->shouldNotBeCalled();

        $this->setFieldData(
            $product,
            'associations',
            [
                'xsell' => [
                    'product_uuids' => [$assocProductOne->getUuid()->toString(), $assocProductTwo->getUuid()->toString()],
                ],
            ]
        );
    }

    function it_creates_inversed_association_on_product(
        ProductRepositoryInterface $productRepository,
        ProductModelRepositoryInterface $productModelRepository,
        AssociationTypeRepositoryInterface $associationTypeRepository,
        MissingAssociationAdder $missingAssociationAdder,
        TwoWayAssociationUpdater $twoWayAssociationUpdater
    ) {
        $compatibilityAssociationType = new AssociationType();
        $compatibilityAssociationType->setIsTwoWay(true);
        $compatibilityAssociationType->setCode('COMPATIBILITY');
        $associationTypeRepository->findOneByIdentifier('COMPATIBILITY')->willReturn($compatibilityAssociationType);

        $compatibilityAssociation = new ProductAssociation();
        $compatibilityAssociation->setAssociationType($compatibilityAssociationType);

        $product = new Product();
        $product->addAssociation($compatibilityAssociation);

        $productAssociated = (new Product())->setIdentifier('productAssociated');

        $productModelAssociated = new ProductModel();
        $productModelAssociated->setCode('productModelAssociated');

        $productRepository->findOneByIdentifier('productAssociated')->willReturn($productAssociated);
        $productModelRepository->findOneByIdentifier('productModelAssociated')->willReturn($productModelAssociated);

        $missingAssociationAdder->addMissingAssociations($product)->shouldBeCalled();
        $twoWayAssociationUpdater
            ->createInversedAssociation($product, 'COMPATIBILITY', $productAssociated)
            ->shouldBeCalled();
        $twoWayAssociationUpdater
            ->createInversedAssociation($product, 'COMPATIBILITY', $productModelAssociated)
            ->shouldBeCalled();

        $this->setFieldData(
            $product,
            'associations',
            [
                'COMPATIBILITY' => [
                    'products' => ['productAssociated'],
                    'product_models' => ['productModelAssociated'],
                ],
            ]
        );
    }

    function it_removes_inversed_association_on_product(
        ProductRepositoryInterface $productRepository,
        ProductModelRepositoryInterface $productModelRepository,
        AssociationTypeRepositoryInterface $associationTypeRepository,
        TwoWayAssociationUpdater $twoWayAssociationUpdater
    ) {
        $compatibilityAssociationType = new AssociationType();
        $compatibilityAssociationType->setIsTwoWay(true);
        $compatibilityAssociationType->setCode('COMPATIBILITY');
        $associationTypeRepository->findOneByIdentifier('COMPATIBILITY')->willReturn($compatibilityAssociationType);

        $productAssociated = (new Product())->setIdentifier('productAssociated');

        $productModelAssociated = new ProductModel();
        $productModelAssociated->setCode('productModelAssociated');

        $compatibilityAssociation = new ProductAssociation();
        $compatibilityAssociation->setAssociationType($compatibilityAssociationType);
        $compatibilityAssociation->addProduct($productAssociated);
        $compatibilityAssociation->addProductModel($productModelAssociated);

        $product = new Product();
        $product->addAssociation($compatibilityAssociation);

        $productRepository->findOneByIdentifier('productAssociated')->willReturn($productAssociated);
        $productModelRepository->findOneByIdentifier('productModelAssociated')->willReturn($productModelAssociated);

        $twoWayAssociationUpdater
            ->removeInversedAssociation($product, 'COMPATIBILITY', $productAssociated)
            ->shouldBeCalled();
        $twoWayAssociationUpdater
            ->removeInversedAssociation($product, 'COMPATIBILITY', $productModelAssociated)
            ->shouldBeCalled();

        $this->setFieldData(
            $product,
            'associations',
            [
                'COMPATIBILITY' => [
                    'products' => [],
                    'product_models' => [],
                ],
            ]
        );
    }

    function it_creates_and_removes_inversed_association_on_product_model(
        ProductRepositoryInterface $productRepository,
        ProductModelRepositoryInterface $productModelRepository,
        AssociationTypeRepositoryInterface $associationTypeRepository,
        MissingAssociationAdder $missingAssociationAdder,
        TwoWayAssociationUpdater $twoWayAssociationUpdater
    ) {
        $compatibilityAssociationType = new AssociationType();
        $compatibilityAssociationType->setIsTwoWay(true);
        $compatibilityAssociationType->setCode('COMPATIBILITY');
        $associationTypeRepository->findOneByIdentifier('COMPATIBILITY')->willReturn($compatibilityAssociationType);

        $productAssociated = (new Product())->setIdentifier('productAssociated');

        $productModelAssociated = new ProductModel();
        $productModelAssociated->setCode('productModelAssociated');

        $compatibilityAssociation = new ProductModelAssociation();
        $compatibilityAssociation->setAssociationType($compatibilityAssociationType);
        $compatibilityAssociation->addProductModel($productModelAssociated);

        $productModel = new ProductModel();
        $productModel->addAssociation($compatibilityAssociation);

        $productRepository->findOneByIdentifier('productAssociated')->willReturn($productAssociated);
        $productModelRepository->findOneByIdentifier('productModelAssociated')->willReturn($productModelAssociated);

        $missingAssociationAdder->addMissingAssociations($productModel)->shouldBeCalled();
        $twoWayAssociationUpdater
            ->createInversedAssociation($productModel, 'COMPATIBILITY', $productAssociated)
            ->shouldBeCalled();
        $twoWayAssociationUpdater
            ->removeInversedAssociation($productModel, 'COMPATIBILITY', $productModelAssociated)
            ->shouldBeCalled();

        $this->setFieldData(
            $productModel,
            'associations',
            [
                'COMPATIBILITY' => [
                    'products' => ['productAssociated'],
                    'product_models' => [],
                ],
            ]
        );
    }

    function it_fails_if_one_of_the_association_type_code_does_not_exist(
        MissingAssociationAdder $missingAssociationAdder,
        AssociationTypeRepositoryInterface $associationTypeRepository,
        ProductInterface $product
    ) {
        $associationTypeRepository->findOneByIdentifier('non valid association type code')->willReturn(null);
        $missingAssociationAdder->addMissingAssociations($product)->shouldBeCalled();

        $this->shouldThrow(
            InvalidPropertyException::validEntityCodeExpected(
                'associations',
                'association type code',
                'The association type does not exist or is quantified',
                AssociationFieldSetter::class,
                'non valid association type code'
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['non valid association type code' => ['groups' => [], 'products' => [], 'product_models' => []]],
            ]
        );
    }

    function it_fails_if_one_of_the_associated_products_does_not_exist(
        MissingAssociationAdder $missingAssociationAdder,
        IdentifiableObjectRepositoryInterface $productRepository,
        ProductInterface $product,
    ) {
        $productRepository->findOneByIdentifier('not existing product')->willReturn(null);

        $product->getAssociatedProducts('xsell')->willReturn(new ArrayCollection());
        $product->getAssociatedProductModels('xsell')->willReturn(new ArrayCollection());
        $product->getAssociatedGroups('xsell')->willReturn(new ArrayCollection());

        $missingAssociationAdder->addMissingAssociations($product)->shouldBeCalled();

        $this->shouldThrow(
            InvalidPropertyException::validEntityCodeExpected(
                'associations',
                'product identifier',
                'The product does not exist',
                AssociationFieldSetter::class,
                'not existing product'
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['xsell' => ['products' => ['not existing product']]],
            ]
        );
    }

    function it_fails_if_one_of_the_associated_product_models_does_not_exist(
        MissingAssociationAdder $missingAssociationAdder,
        IdentifiableObjectRepositoryInterface $productModelRepository,
        ProductModelInterface $productModel,
    ) {
        $productModelRepository->findOneByIdentifier('not existing product model')->willReturn(null);

        $productModel->getAssociatedProducts('xsell')->willReturn(new ArrayCollection());
        $productModel->getAssociatedProductModels('xsell')->willReturn(new ArrayCollection());
        $productModel->getAssociatedGroups('xsell')->willReturn(new ArrayCollection());

        $missingAssociationAdder->addMissingAssociations($productModel)->shouldBeCalled();

        $this->shouldThrow(
            InvalidPropertyException::validEntityCodeExpected(
                'associations',
                'product model identifier',
                'The product model does not exist',
                AssociationFieldSetter::class,
                'not existing product model'
            )
        )->during(
            'setFieldData',
            [
                $productModel,
                'associations',
                ['xsell' => ['product_models' => ['not existing product model']]],
            ]
        );
    }

    function it_fails_if_one_of_the_associated_product_uuids_does_not_exist(
        MissingAssociationAdder $missingAssociationAdder,
        ProductRepositoryInterface $productRepository,
        ProductInterface $product,
    ) {
        $notExistingUuid = Uuid::uuid4();
        $productRepository->find($notExistingUuid->toString())->willReturn(null);

        $product->getAssociatedProducts('xsell')->willReturn(new ArrayCollection());

        $missingAssociationAdder->addMissingAssociations($product)->shouldBeCalled();

        $this->shouldThrow(
            InvalidPropertyException::validEntityCodeExpected(
                'associations',
                'product uuid',
                'The product does not exist',
                AssociationFieldSetter::class,
                $notExistingUuid->toString()
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['xsell' => ['product_uuids' => [$notExistingUuid->toString()]]],
            ]
        );
    }

    function it_fails_if_one_of_the_associated_product_uuids_is_not_valid(ProductInterface $product)
    {
        $product->getAssociatedProducts('xsell')->willReturn(new ArrayCollection());

        $this->shouldThrow(
            InvalidPropertyTypeException::validArrayStructureExpected(
                'associations',
                'association format is not valid for the association type "xsell", "product_uuids" expects an array of valid uuids.',
                AssociationFieldSetter::class,
                ['xsell' => ['product_uuids' => ['not existing product']]]
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['xsell' => ['product_uuids' => ['not existing product']]],
            ]
        );
    }

    function it_fails_when_associating_products_by_identifier_and_uuid()
    {
        $uuid = Uuid::uuid4();

        $product = new Product();
        $this->shouldThrow(
            InvalidPropertyTypeException::validArrayStructureExpected(
                'associations',
                'association format is not valid for the association type "xsell", only one of "products" or "product_uuids" is expected',
                AssociationFieldSetter::class,
                [
                    'xsell' => [
                        'product_uuids' => [$uuid->toString()],
                        'products' => ['a_sku'],
                    ]
                ],
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                [
                    'xsell' => [
                        'product_uuids' => [$uuid->toString()],
                        'products' => ['a_sku'],
                    ]
                ],
            ]
        );
    }
}
