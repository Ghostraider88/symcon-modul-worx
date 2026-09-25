<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WorxCloud/module.php';

final class WorxCloudRequestTestDouble extends WorxCloud
{
    public array $requests = [];
    public bool $pollCalled = false;

    public function Poll(): bool
    {
        $this->pollCalled = true;
        return true;
    }

    protected function getToken(): string
    {
        return 'test-token';
    }

    protected function request(string $method, string $path, $body, string $token)
    {
        $this->requests[] = [$method, $path, $body, $token];
        return ['accepted' => true];
    }
}

final class WorxCloudSecurityTest extends TestCase
{
    public function testDeviceSerialIsMaskedInApiDebugPath(): void
    {
        $module = new WorxCloud(1);
        $method = new ReflectionMethod(WorxCloud::class, 'sanitizeApiPathForDebug');
        $method->setAccessible(true);

        $path = '/api/v2/product-items/SERIAL-TEST-1234';
        $sanitized = $method->invoke($module, $path);

        self::assertSame('/api/v2/product-items/{serial}', $sanitized);
        self::assertStringNotContainsString('SERIAL-TEST-1234', $sanitized);
    }

    public function testStaticInventoryPathRemainsUsefulInApiDebugLog(): void
    {
        $module = new WorxCloud(1);
        $method = new ReflectionMethod(WorxCloud::class, 'sanitizeApiPathForDebug');
        $method->setAccessible(true);

        self::assertSame('/api/v2/product-items?status=1', $method->invoke($module, '/api/v2/product-items?status=1'));
    }

    public function testFirmwareAutoUpgradeWriteRequiresCapabilityBooleanAndOnlineDevice(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloud(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $device = [
            'serial_number'         => 'SERIAL-TEST',
            'online'                => true,
            'capabilities'          => [],
            'firmware_auto_upgrade' => false,
        ];
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->SetFirmwareAutoUpgrade('SERIAL-TEST', true));

        $device['capabilities'] = ['ota_upgrade'];
        $device['firmware_auto_upgrade'] = 'false';
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->SetFirmwareAutoUpgrade('SERIAL-TEST', true));

        $device['firmware_auto_upgrade'] = false;
        $device['online'] = false;
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->SetFirmwareAutoUpgrade('SERIAL-TEST', true));
    }

    public function testFirmwareAutoUpgradeWritesOnlyThePreferenceAndRefreshesCloudState(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudRequestTestDouble(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'Devices', json_encode([[
            'serial_number'         => 'SERIAL-TEST',
            'online'                => true,
            'capabilities'          => ['ota_upgrade'],
            'firmware_auto_upgrade' => false,
        ]]));

        self::assertTrue($module->SetFirmwareAutoUpgrade('SERIAL-TEST', true));
        self::assertSame([
            ['PUT', '/api/v2/product-items/SERIAL-TEST', ['firmware_auto_upgrade' => true], 'test-token'],
        ], $module->requests);
        self::assertTrue($module->pollCalled);
    }

    public function testRainDelayCloudCommandRejectsValuesOutsideTheDeviceSteps(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloud(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'Devices', json_encode([[
            'serial_number' => 'SERIAL-TEST',
            'protocol' => 0,
            'capabilities' => ['rain_delay'],
            'online' => true,
            'mqtt_topics' => ['command_in' => 'test/topic'],
        ]]));

        foreach ([-30, 1, 15, 31, 721] as $minutes) {
            self::assertFalse($module->SetRainDelay('SERIAL-TEST', $minutes));
        }
    }
}
