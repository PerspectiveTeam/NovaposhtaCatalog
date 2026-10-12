<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Area;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Perspective\NovaposhtaCatalog\Api\Data\AreaInterface;

class Area extends AbstractExtensibleModel implements AreaInterface
{
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area::class);
    }

    /**
     * @inheritDoc
     */
    public function setRef($data)
    {
        return $this->setData(self::REF, $data);
    }

    /**
     * @inheritDoc
     */
    public function setAreasCenter($data)
    {
        return $this->setData(self::AREAS_CENTER, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDescriptionUa($data)
    {
        return $this->setData(self::DESCRIPTION_UA, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDescriptionRu($data)
    {
        return $this->setData(self::DESCRIPTION_RU, $data);
    }

    /**
     * @inheritDoc
     */
    public function getRef(): ?string
    {
        return $this->getData(self::REF);
    }

    /**
     * @inheritDoc
     */
    public function getAreasCenter()
    {
        return $this->getData(self::AREAS_CENTER);
    }

    /**
     * @inheritDoc
     */
    public function getDescriptionUa()
    {
        return $this->getData(self::DESCRIPTION_UA);
    }

    /**
     * @inheritDoc
     */
    public function getDescriptionRu()
    {
        return $this->getData(self::DESCRIPTION_RU);
    }

    /**
     * @inheritDoc
     */
    public function getCustomAttributesCodes()
    {
        return [self::ID, self::REF, self::AREAS_CENTER, self::DESCRIPTION_UA, self::DESCRIPTION_RU];
    }
}
