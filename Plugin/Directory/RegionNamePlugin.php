<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Plugin\Directory;

use Magento\Directory\Model\Region;

class RegionNamePlugin
{
    private const UKRAINE_REGION_NAMES = [
        'UA-71' => 'Черкаська область',
        'UA-74' => 'Чернігівська область',
        'UA-77' => 'Чернівецька область',
        'UA-12' => 'Дніпропетровська область',
        'UA-14' => 'Донецька область',
        'UA-26' => 'Івано-Франківська область',
        'UA-63' => 'Харківська область',
        'UA-65' => 'Херсонська область',
        'UA-68' => 'Хмельницька область',
        'UA-35' => 'Кіровоградська область',
        'UA-32' => 'Київська область',
        'UA-09' => 'Луганська область',
        'UA-46' => 'Львівська область',
        'UA-48' => 'Миколаївська область',
        'UA-51' => 'Одеська область',
        'UA-53' => 'Полтавська область',
        'UA-56' => 'Рівненська область',
        'UA-59' => 'Сумська область',
        'UA-61' => 'Тернопільська область',
        'UA-05' => 'Вінницька область',
        'UA-07' => 'Волинська область',
        'UA-21' => 'Закарпатська область',
        'UA-23' => 'Запорізька область',
        'UA-18' => 'Житомирська область',
        'UA-43' => 'АР Крим',
        'UA-30' => 'Київ',
        'UA-40' => 'Севастополь',
    ];

    public function afterGetName(Region $subject, ?string $result): ?string
    {
        if ($subject->getCountryId() !== 'UA') {
            return $result;
        }

        $code = $subject->getCode();
        return self::UKRAINE_REGION_NAMES[$code] ?? $result;
    }
}
