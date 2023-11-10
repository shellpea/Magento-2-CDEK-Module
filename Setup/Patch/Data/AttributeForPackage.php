<?php

namespace Shellpea\CDEK\Setup\Patch\Data;

use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Api\Data\AttributeGroupInterfaceFactory;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class AttributeForPackage implements DataPatchInterface, PatchRevertableInterface
{
    private $logger;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var AttributeFactory
     */
    private $attributeFactory;

    /**
     * @var AttributeGroupInterfaceFactory
     */
    private $attributeGroup;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param LoggerInterface $logger
     * @param AttributeFactory $attributeFactory
     * @param EavSetupFactory $eavSetupFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param AttributeGroupInterfaceFactory $attributeGroup
     */
    public function __construct(
        LoggerInterface $logger,
        AttributeFactory $attributeFactory,
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $moduleDataSetup,
        AttributeGroupInterfaceFactory $attributeGroup
    ) {
        $this->logger = $logger;
        $this->attributeGroup = $attributeGroup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attributeFactory = $attributeFactory;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->createAttribute('length_for_cdek', 'Package Length For Cdek (cm)');
        $this->createAttribute('width_for_cdek', 'Package Width For Cdek (cm)');
        $this->createAttribute('height_for_cdek', 'Package Height For Cdek (cm)');
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function createAttribute($attributeCode, $attributeLabel)
    {
        $eavAttribute = $this->attributeFactory->create()->getCollection()
            ->addFieldToFilter(Set::KEY_ENTITY_TYPE_ID, 4)
            ->addFieldToFilter('attribute_code', $attributeCode)
            ->getFirstItem();
        if (!$eavAttribute->getId()) {
            $attributeGroup = $this->attributeGroup->create(['setup' => $this->moduleDataSetup])->getCollection()
                ->addFieldToFilter('attribute_group_code', 'general')->getFirstItem();
            $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $eavSetup->addAttribute(
                Product::ENTITY,
                $attributeCode,
                [
                    'type' => 'decimal',
                    'label' => $attributeLabel,
                    'attribute_model' => '',
                    'input' => 'text',
                    'required' => false,
                    'used_in_product_listing' => true,
                    'user_defined' => true,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'unique' => false,
                    'group' => $attributeGroup->getAttributeGroupName(),
                ]
            );
            $this->logger->info('Created Attribute ' . $attributeCode);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute('catalog_product', 'length_for_cdek');
        $eavSetup->removeAttribute('catalog_product', 'width_for_cdek');
        $eavSetup->removeAttribute('catalog_product', 'height_for_cdek');

        $this->logger->info('Removed attributes');
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
