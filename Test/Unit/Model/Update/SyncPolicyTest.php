<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Model\Area\Area;
use Perspective\NovaposhtaCatalog\Model\Update\SyncPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SyncPolicyTest extends TestCase
{
    private int $now = 1760000000;

    public function testRequiresSeparatedConfirmationsAndRestoresTheSameRecordOnReappearance(): void
    {
        $policy = $this->policy();
        $record = $this->model()->setId(42);
        $policy->markPresent($record);
        $seen = $record->getData('last_seen_at');
        self::assertTrue($policy->markMissing($record));
        self::assertSame(1, $record->getData('is_active'));
        self::assertSame(1, $record->getData('missing_successful_syncs'));
        $this->now += 86399;
        self::assertFalse($policy->markMissing($record));
        self::assertSame(1, $record->getData('missing_successful_syncs'));
        ++$this->now;
        self::assertTrue($policy->markMissing($record));
        self::assertSame(0, $record->getData('is_active'));
        self::assertSame(2, $record->getData('missing_successful_syncs'));
        self::assertSame($seen, $record->getData('last_seen_at'));
        self::assertFalse($policy->markMissing($record));
        $policy->markPresent($record);
        self::assertSame(42, $record->getId());
        self::assertSame(1, $record->getData('is_active'));
        self::assertSame(0, $record->getData('missing_successful_syncs'));
        self::assertNull($record->getData('last_missing_at'));
        self::assertNotSame($seen, $record->getData('last_seen_at'));
    }

    public function testPresenceInterruptsMissingConfirmations(): void
    {
        $policy = $this->policy();
        $record = $this->model();
        $policy->markMissing($record);
        $this->now += 86400;
        $policy->markPresent($record);
        $policy->markMissing($record);
        self::assertSame(1, $record->getData('is_active'));
        self::assertSame(1, $record->getData('missing_successful_syncs'));
    }

    #[DataProvider('statuses')]
    public function testWarehouseAvailabilityFollowsValidatedApiStatus(string $status, int $active): void
    {
        $record = $this->model()->setData('warehouse_status', $status);
        $this->policy()->markPresent($record);
        self::assertSame($active, $record->getData('is_active'));
    }

    public static function statuses(): array
    {
        return [['Working', 1], ['NotWorking', 0], ['NotWorkingTemporary', 0], ['InProcessOpening', 0]];
    }

    public function testExactlyTwentyPercentLossIsAllowedButSameSizeReplacementCannotMaskLargerLoss(): void
    {
        $existing = [];
        foreach (range(1, 5) as $id) { $existing[(string)$id] = $this->model(); }
        $this->policy()->assertAcceptable($existing, array_slice($existing, 1, null, true), 'city-a');
        self::assertTrue(true);
        $this->expectException(LocalizedException::class);
        $this->policy()->assertAcceptable($existing, ['3' => $this->model(), '4' => $this->model(),
            '5' => $this->model(), '6' => $this->model(), '7' => $this->model()], 'city-a');
    }

    public function testInactiveReferencesDoNotInflateTheSafetyDenominator(): void
    {
        $inactive = $this->model()->setData('is_active', 0);
        $this->expectException(LocalizedException::class);
        $this->policy()->assertAcceptable(['a' => $inactive, 'b' => $inactive, 'c' => $this->model()], [], 'test');
    }

    public function testReviewedEmptySnapshotCanBeConfirmedWithExplicitThresholdOverride(): void
    {
        $this->policy(['max_missing_percent' => 100])->assertAcceptable(['a' => $this->model()], [], 'reviewed');
        self::assertTrue(true);
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidSafetySettingsFailClosed(string $name, mixed $value): void
    {
        $this->expectException(LocalizedException::class);
        $this->policy([$name => $value])->getSetting($name);
    }

    public static function invalidSettings(): array
    {
        return [['confirmations', 1], ['confirmation_hours', 0], ['max_missing_percent', 101],
            ['max_missing_percent', -1], ['confirmations', 'oops']];
    }

    private function policy(array $settings = []): SyncPolicy
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(fn ($path) => $settings[basename($path)] ?? null);
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturnCallback(fn () => $this->now);
        return new SyncPolicy($config, $clock);
    }

    private function model(): Area
    {
        return (new \ReflectionClass(Area::class))->newInstanceWithoutConstructor();
    }
}
