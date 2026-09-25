<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WorxCloud/module.php';

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
}