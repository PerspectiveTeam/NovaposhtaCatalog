<?php


namespace Perspective\NovaposhtaCatalog\Model\Package;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Perspective\NovaposhtaCatalog\Api\Data\PackageInterface;

class Package extends \Magento\Framework\Model\AbstractExtensibleModel implements PackageInterface
{
    private \ReflectionClassFactory $reflectionClassFactory;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
     * @param \ReflectionClassFactory $reflectionClassFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer,
        \ReflectionClassFactory $reflectionClassFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
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
        $this->reflectionClassFactory = $reflectionClassFactory;
    }
    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package::class);
    }

    public function setDescriptionUa($data)
    {
        return $this->setData(self::DESCRIPTION_UA, $data);
    }

    public function setDescriptionRu($data)
    {
        return $this->setData(self::DESCRIPTION_RU, $data);
    }

    public function setRef($data)
    {
        return $this->setData(self::REF, $data);
    }

    public function getDescriptionUa()
    {
        return $this->getData(self::DESCRIPTION_UA);
    }

    public function getDescriptionRu()
    {
        return $this->getData(self::DESCRIPTION_RU);
    }

    public function getRef()
    {
        return $this->getData(self::REF);
    }

    public function setLength($data)
    {
        return $this->setData(self::LENGTH, $data);
    }

    public function setWidth($data)
    {
        return $this->setData(self::WIDTH, $data);
    }

    public function setHeight($data)
    {
        return $this->setData(self::HEIGHT, $data);
    }

    public function getLength($data)
    {
        return $this->getData(self::LENGTH);
    }

    public function getWidth($data)
    {
        return $this->getData(self::WIDTH);
    }

    public function getHeight($data)
    {
        return $this->getData(self::HEIGHT);
    }
    public function getCustomAttributesCodes()
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
            $this->reflectionClass = $this->reflectionClassFactory->create(['argument' => self::class]);
        }else{
            $this->reflectionClass = $this->reflectionClassFactory->create(['objectOrClass' => self::class]);
        }

        $constants = $this->reflectionClass->getConstants();
        array_push($constants, 'id');
        return $constants;
    }
}
