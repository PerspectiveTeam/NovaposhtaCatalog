<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testReadsXmlDefaults(): void
    {
        $rows = [
            'item1' => ['magento' => 'UA-32', 'novaposhta' => 'kyiv-area'],
            'item2' => ['magento' => 'UA-30', 'novaposhta' => 'kyiv-area'],
        ];

        self::assertSame(
            ['UA-32' => 'kyiv-area', 'UA-30' => 'kyiv-area'],
            $this->createHelper($rows)->getDirectoryMap()
        );
    }

    public function testReadsJsonSavedBySystemConfigurationAtRequestedStore(): void
    {
        $rows = [
            '_row_1' => ['magento' => 'UA-32', 'novaposhta' => 'kyiv-area'],
            '_row_2' => ['magento' => 'UA-46', 'novaposhta' => 'lviv-area'],
            '_row_3' => ['magento' => '', 'novaposhta' => 'unused-area'],
            '__empty' => '',
        ];
        $storedValue = (new Json())->serialize($rows);

        self::assertSame(
            ['UA-32' => 'kyiv-area', 'UA-46' => 'lviv-area'],
            $this->createHelper($storedValue, 2)->getDirectoryMap(2)
        );
    }

    public function testEmptyOrInvalidConfigurationReturnsEmptyMap(): void
    {
        foreach ([null, '', [], '[]', '{}', '{invalid', 'null', 'false', '42', '"text"'] as $value) {
            self::assertSame([], $this->createHelper($value)->getDirectoryMap());
        }
    }

    private function createHelper($value, ?int $storeId = null): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('getValue')
            ->with(Config::XML_PATH_DIRECTORY_MAP, ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn($value);

        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);

        return new Config($context);
    }
}
