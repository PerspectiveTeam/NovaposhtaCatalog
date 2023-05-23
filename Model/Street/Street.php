<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Street;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Model\AbstractExtensibleModel;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street as StreetResource;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterface;

class Street extends AbstractExtensibleModel implements StreetInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'novaposhta_street_model';

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

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(StreetResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getStreetId(): int
    {
        return (int)$this->getData(self::STREET_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStreetId(int $streetId): StreetInterface
    {
        return $this->setData(self::STREET_ID, $streetId);
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
    public function setRef(string $ref): StreetInterface
    {
        return $this->setData(self::REF, $ref);
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritDoc
     */
    public function setDescription(string $description): StreetInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritDoc
     */
    public function getStreetTypeRef(): string
    {
        return $this->getData(self::STREET_TYPE_REF);
    }

    /**
     * @inheritDoc
     */
    public function setStreetTypeRef(string $streetTypeRef): StreetInterface
    {
        return $this->setData(self::STREET_TYPE_REF, $streetTypeRef);
    }

    /**
     * @inheritDoc
     */
    public function getStreetType(): string
    {
        return $this->getData(self::STREET_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setStreetType(string $streetType): StreetInterface
    {
        return $this->setData(self::STREET_TYPE, $streetType);
    }

    /**
     * @inheritDoc
     */
    public function getCityRef(): string
    {
        return $this->getData(self::CITY_REF);
    }

    /**
     * @inheritDoc
     */
    public function setCityRef(string $cityRef): StreetInterface
    {
        return $this->setData(self::CITY_REF, $cityRef);
    }
    public function getCustomAttributesCodes()
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
            $this->reflectionClass = $this->reflectionClassFactory->create(['argument' => self::class]);
        }else{
            $this->reflectionClass = $this->reflectionClassFactory->create(['objectOrClass' => self::class]);
        }

        $constants = $this->reflectionClass->getConstants();
        array_push($constants, self::STREET_ID);
        return $constants;
    }
}
