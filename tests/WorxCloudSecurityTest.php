<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WorxCloud/module.php';

final class WorxCloudRequestTestDouble extends WorxCloud
{
    public array $requests = [];
    public array $requestResponses = [];
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

    protected function getTime()
    {
        return time();
    }

    protected function request(string $method, string $path, $body, string $token)
    {
        $this->requests[] = [$method, $path, $body, $token];
        return count($this->requestResponses) > 0 ? array_shift($this->requestResponses) : ['accepted' => true];
    }
}

final class WorxCloudMqttParentTestDouble extends IPSModule
{
    public array $messages = [];

    public function GetForwardDataFilter()
    {
        return '.*';
    }

    public function ForwardData($JSONString)
    {
        $message = json_decode($JSONString, true);
        $this->messages[] = [
            'Topic'   => $message['Topic'] ?? '',
            'Payload' => json_decode($message['Payload'] ?? '', true),
        ];
        return '';
    }
}

final class WorxCloudWsTestDouble extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Active', true);
    }
}

final class WorxCloudHttpTestDouble extends WorxCloud
{
    public array $httpResponses = [];

    protected function getTime()
    {
        return time();
    }

    protected function executeHttpRequest(string $url, string $method, array $headers, ?string $body): array
    {
        return array_shift($this->httpResponses);
    }
}

final class WorxCloudSecurityTest extends TestCase
{
    public function testRequestRejectsTransportHttpAndMalformedReadResponses(): void
    {
        $request = new ReflectionMethod(WorxCloud::class, 'request');
        $request->setAccessible(true);
        $responses = [
            ['response' => false, 'code' => 0, 'error' => 28],
            ['response' => 'service unavailable', 'code' => 503, 'error' => 0],
            ['response' => '{"error":"rate limit exceeded"}', 'code' => 429, 'error' => 0],
            ['response' => '', 'code' => 200, 'error' => 0],
            ['response' => '{invalid json', 'code' => 200, 'error' => 0],
        ];

        foreach ($responses as $response) {
            IPS\Kernel::reset();
            $module = new WorxCloudHttpTestDouble(1);
            $module->Create();
            $module->httpResponses = [$response];

            self::assertNull($request->invoke($module, 'GET', '/api/v2/product-items?status=1', null, 'test-token'));
        }
    }

    public function testAuthenticationRejectsTransportHttpAndMalformedResponses(): void
    {
        $authentication = new ReflectionMethod(WorxCloud::class, 'authRequest');
        $authentication->setAccessible(true);
        $responses = [
            ['response' => false, 'code' => 0, 'error' => 28],
            ['response' => '{"error":"unauthorized"}', 'code' => 401, 'error' => 0],
            ['response' => '', 'code' => 200, 'error' => 0],
            ['response' => '{invalid json', 'code' => 200, 'error' => 0],
        ];

        foreach ($responses as $response) {
            IPS\Kernel::reset();
            $module = new WorxCloudHttpTestDouble(1);
            $module->Create();
            $module->httpResponses = [$response];

            self::assertNull($authentication->invoke($module, ['username' => 'user', 'password' => 'secret']));
        }
    }

    public function testAuthenticationParsesSuccessfulTokenResponse(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudHttpTestDouble(1);
        $module->Create();
        $module->httpResponses = [['response' => '{"access_token":"test-token"}', 'code' => 200, 'error' => 0]];
        $authentication = new ReflectionMethod(WorxCloud::class, 'authRequest');
        $authentication->setAccessible(true);

        self::assertSame(['access_token' => 'test-token'], $authentication->invoke($module, ['username' => 'user', 'password' => 'secret']));
    }
    public function testExpiredAccessTokenIsRenewedWithRefreshToken(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudHttpTestDouble(1);
        $module->Create();
        $writeString = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeString->setAccessible(true);
        $writeInteger = new ReflectionMethod(IPSModule::class, 'WriteAttributeInteger');
        $writeInteger->setAccessible(true);
        $writeString->invoke($module, 'AccessToken', 'expired-access-token');
        $writeString->invoke($module, 'RefreshToken', 'valid-refresh-token');
        $writeInteger->invoke($module, 'TokenExpires', time() - 1);
        $module->httpResponses = [[
            'response' => '{"access_token":"refreshed-access-token","refresh_token":"rotated-refresh-token","expires_in":3600}',
            'code'     => 200,
            'error'    => 0,
        ]];

        $getToken = new ReflectionMethod(WorxCloud::class, 'getToken');
        $getToken->setAccessible(true);
        self::assertSame('refreshed-access-token', $getToken->invoke($module));

        $readString = new ReflectionMethod(IPSModule::class, 'ReadAttributeString');
        $readString->setAccessible(true);
        $readInteger = new ReflectionMethod(IPSModule::class, 'ReadAttributeInteger');
        $readInteger->setAccessible(true);
        self::assertSame('rotated-refresh-token', $readString->invoke($module, 'RefreshToken'));
        self::assertGreaterThan(time(), $readInteger->invoke($module, 'TokenExpires'));
    }

    public function testRejectedRefreshTokenFallsBackToPasswordGrant(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudHttpTestDouble(1);
        $module->Create();
        $writeString = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeString->setAccessible(true);
        $writeInteger = new ReflectionMethod(IPSModule::class, 'WriteAttributeInteger');
        $writeInteger->setAccessible(true);
        $writeString->invoke($module, 'RefreshToken', 'rejected-refresh-token');
        $writeInteger->invoke($module, 'TokenExpires', 0);
        $module->httpResponses = [
            ['response' => '{"error":"invalid_grant"}', 'code' => 400, 'error' => 0],
            ['response' => '{"access_token":"password-grant-token","expires_in":3600}', 'code' => 200, 'error' => 0],
        ];

        $getToken = new ReflectionMethod(WorxCloud::class, 'getToken');
        $getToken->setAccessible(true);
        self::assertSame('password-grant-token', $getToken->invoke($module));
    }

    public function testRequestAcceptsValidJsonAndEmptySuccessfulMutation(): void
    {
        $request = new ReflectionMethod(WorxCloud::class, 'request');
        $request->setAccessible(true);

        IPS\Kernel::reset();
        $module = new WorxCloudHttpTestDouble(1);
        $module->Create();
        $module->httpResponses = [['response' => '{"auto_schedule":true}', 'code' => 200, 'error' => 0]];
        self::assertSame(['auto_schedule' => true], $request->invoke($module, 'GET', '/api/v2/product-items', null, 'test-token'));

        IPS\Kernel::reset();
        $module = new WorxCloudHttpTestDouble(1);
        $module->Create();
        $module->httpResponses = [['response' => '', 'code' => 204, 'error' => 0]];
        self::assertSame([], $request->invoke($module, 'PUT', '/api/v2/product-items/SERIAL', ['auto_schedule' => true], 'test-token'));
    }

    public function testUnauthorizedResponseExpiresCachedToken(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudHttpTestDouble(1);
        $module->Create();
        $module->httpResponses = [['response' => '', 'code' => 401, 'error' => 0]];
        $writeExpires = new ReflectionMethod(IPSModule::class, 'WriteAttributeInteger');
        $writeExpires->setAccessible(true);
        $writeExpires->invoke($module, 'TokenExpires', time() + 900);

        $request = new ReflectionMethod(WorxCloud::class, 'request');
        $request->setAccessible(true);
        self::assertNull($request->invoke($module, 'GET', '/api/v2/product-items', null, 'test-token'));

        $readExpires = new ReflectionMethod(IPSModule::class, 'ReadAttributeInteger');
        $readExpires->setAccessible(true);
        self::assertSame(0, $readExpires->invoke($module, 'TokenExpires'));
    }

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

    public function testFirmwareUpgradeInfoRequiresCapabilityAndReturnsOnlySanitizedFields(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudRequestTestDouble(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $device = ['serial_number' => 'SERIAL-TEST', 'capabilities' => []];
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->GetFirmwareUpgradeInfo('SERIAL-TEST'));
        self::assertSame([], $module->requests);

        $device['capabilities'] = ['ota_upgrade'];
        $device['firmware_version'] = '3.52.0+1';
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        $module->requestResponses = [[
            'latest_version'  => '3.53.0',
            'ota_supported'   => true,
            'update_available' => true,
            'upgrade_failed'  => false,
            'changelog'       => 'not exposed',
            'serial_number'   => 'must not leak',
        ]];
        self::assertSame([
            'current_version' => '3.52.0+1',
            'latest_version'  => '3.53.0',
            'ota_supported'   => true,
            'update_available' => true,
            'upgrade_failed'  => false,
        ], $module->GetFirmwareUpgradeInfo('SERIAL-TEST'));
        self::assertSame([
            ['GET', '/api/v2/product-items/SERIAL-TEST/firmware-upgrade', null, 'test-token'],
        ], $module->requests);
    }

    public function testFirmwareUpgradeStartRequiresOnlineCapableDeviceAndFreshAvailableOta(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudRequestTestDouble(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $device = ['serial_number' => 'SERIAL-TEST', 'online' => true, 'capabilities' => ['ota_upgrade']];
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));

        $module->requestResponses = [['ota_supported' => true, 'update_available' => false]];
        self::assertFalse($module->StartFirmwareUpgrade('SERIAL-TEST'));
        self::assertSame([
            ['GET', '/api/v2/product-items/SERIAL-TEST/firmware-upgrade', null, 'test-token'],
        ], $module->requests);

        $module->requests = [];
        $module->requestResponses = [
            ['ota_supported' => true, 'update_available' => true],
            [],
        ];
        self::assertTrue($module->StartFirmwareUpgrade('SERIAL-TEST'));
        self::assertSame([
            ['GET', '/api/v2/product-items/SERIAL-TEST/firmware-upgrade', null, 'test-token'],
            ['POST', '/api/v2/product-items/SERIAL-TEST/firmware-upgrade', [], 'test-token'],
        ], $module->requests);

        $module->requests = [];
        $device['online'] = false;
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->StartFirmwareUpgrade('SERIAL-TEST'));
        self::assertSame([], $module->requests);
    }

    public function testAutoScheduleWriteRequiresReportedBooleanAndOnlineDevice(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloud(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);

        $device = [
            'serial_number' => 'SERIAL-TEST',
            'online'        => true,
        ];
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->SetAutoSchedule('SERIAL-TEST', true));

        $device['auto_schedule'] = 'false';
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->SetAutoSchedule('SERIAL-TEST', true));

        $device['auto_schedule'] = false;
        $device['online'] = false;
        $writeAttribute->invoke($module, 'Devices', json_encode([$device]));
        self::assertFalse($module->SetAutoSchedule('SERIAL-TEST', true));
    }

    public function testAutoScheduleWriteUpdatesOnlyTheCloudPreferenceAndPollsForConfirmation(): void
    {
        IPS\Kernel::reset();
        $module = new WorxCloudRequestTestDouble(1);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'Devices', '[]');
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'Devices', json_encode([[
            'serial_number' => 'SERIAL-TEST',
            'online'        => true,
            'auto_schedule' => false,
        ]]));

        self::assertTrue($module->SetAutoSchedule('SERIAL-TEST', true));
        self::assertSame([
            ['PUT', '/api/v2/product-items/SERIAL-TEST', ['auto_schedule' => true], 'test-token'],
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
            'protocol'      => 0,
            'capabilities'  => ['rain_delay'],
            'online'        => true,
            'mqtt_topics'   => ['command_in' => 'test/topic'],
        ]]));

        foreach ([-30, 1, 15, 31, 721] as $minutes) {
            self::assertFalse($module->SetRainDelay('SERIAL-TEST', $minutes));
        }
    }

    public function testRainDelayCloudCommandPublishesValidValuesAboveTheFormerLimit(): void
    {
        IPS\Kernel::reset();
        IPS\InstanceManager::createInstance(1, [
            'Class'      => WorxCloudRequestTestDouble::class,
            'ModuleID'   => '{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}',
            'ModuleName' => 'Worx Cloud Test',
            'ModuleType' => 2,
        ]);
        IPS\InstanceManager::createInstance(2, [
            'Class'      => WorxCloudMqttParentTestDouble::class,
            'ModuleID'   => '{00000000-0000-0000-0000-000000000001}',
            'ModuleName' => 'MQTT Parent Test',
            'ModuleType' => 1,
        ]);
        IPS\InstanceManager::connectInstance(1, 2);
        $module = IPS\InstanceManager::getInstanceInterface(1);
        $mqtt = IPS\InstanceManager::getInstanceInterface(2);
        $module->SetProperty('Email', 'test@example.invalid');
        $module->SetProperty('Password', 'secret');
        (new ReflectionMethod(IPSModule::class, 'ApplyChanges'))->invoke($module);
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'Devices', json_encode([[
            'serial_number' => 'SERIAL-TEST',
            'protocol'      => 0,
            'capabilities'  => ['rain_delay'],
            'online'        => true,
            'mqtt_topics'   => ['command_in' => 'test/topic'],
        ]]));

        foreach ([330, 720] as $minutes) {
            self::assertTrue($module->SetRainDelay('SERIAL-TEST', $minutes));
        }

        self::assertSame([
            ['Topic'   => 'test/topic', 'Payload' => ['rd' => 330]],
            ['Topic'   => 'test/topic', 'Payload' => ['rd' => 720]],
        ], $mqtt->messages);
    }
    public function testMqttCommandsAreBlockedWhenTransportConfigurationIsDisabledOrInvalid(): void
    {
        foreach ([
            ['Cloud' => 'worx', 'UseMQTT' => false, 'Email' => 'test@example.invalid', 'Password' => 'secret'],
            ['Cloud' => 'worx', 'UseMQTT' => true, 'Email' => '', 'Password' => 'secret'],
            ['Cloud' => 'other', 'UseMQTT' => true, 'Email' => 'test@example.invalid', 'Password' => 'secret'],
        ] as $configuration) {
            IPS\Kernel::reset();
            IPS\InstanceManager::createInstance(1, [
                'Class'      => WorxCloudRequestTestDouble::class,
                'ModuleID'   => '{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}',
                'ModuleName' => 'Worx Cloud Test',
                'ModuleType' => 2,
            ]);
            IPS\InstanceManager::createInstance(2, [
                'Class'      => WorxCloudMqttParentTestDouble::class,
                'ModuleID'   => '{00000000-0000-0000-0000-000000000001}',
                'ModuleName' => 'MQTT Parent Test',
                'ModuleType' => 1,
            ]);
            IPS\InstanceManager::connectInstance(1, 2);
            $module = IPS\InstanceManager::getInstanceInterface(1);
            $mqtt = IPS\InstanceManager::getInstanceInterface(2);
            foreach ($configuration as $name => $value) {
                $module->SetProperty($name, $value);
            }
            (new ReflectionMethod(IPSModule::class, 'ApplyChanges'))->invoke($module);
            $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
            $writeAttribute->invoke($module, 'Devices', json_encode([[
                'serial_number' => 'SERIAL-TEST',
                'protocol'      => 0,
                'capabilities'  => ['rain_delay'],
                'online'        => true,
                'mqtt_topics'   => ['command_in' => 'test/topic'],
            ]]));

            self::assertFalse($module->SetRainDelay('SERIAL-TEST', 30));
            self::assertSame([], $mqtt->messages);
        }
    }

    public function testManagedWorxTransportIsDisabledWhenMqttOrCredentialsAreDisabled(): void
    {
        foreach ([
            ['Cloud' => 'worx', 'UseMQTT' => false, 'Email' => 'user@example.invalid', 'Password' => 'secret'],
            ['Cloud' => 'worx', 'UseMQTT' => true, 'Email' => '', 'Password' => 'secret'],
            ['Cloud' => 'other', 'UseMQTT' => true, 'Email' => 'user@example.invalid', 'Password' => 'secret'],
        ] as $configuration) {
            IPS\Kernel::reset();
            IPS\InstanceManager::createInstance(1, [
                'Class'      => WorxCloudRequestTestDouble::class,
                'ModuleID'   => '{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}',
                'ModuleName' => 'Worx Cloud Test',
                'ModuleType' => 2,
            ]);
            IPS\InstanceManager::createInstance(2, [
                'Class'      => WorxCloudMqttParentTestDouble::class,
                'ModuleID'   => '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}',
                'ModuleName' => 'Worx MQTT Test',
                'ModuleType' => 2,
            ]);
            IPS\InstanceManager::createInstance(3, [
                'Class'      => WorxCloudWsTestDouble::class,
                'ModuleID'   => '{D68FD31F-0E90-7019-F16C-1949BD3079EF}',
                'ModuleName' => 'Worx WS Test',
                'ModuleType' => 1,
            ]);
            IPS\InstanceManager::connectInstance(2, 3);
            IPS\InstanceManager::connectInstance(1, 2);
            $module = IPS\InstanceManager::getInstanceInterface(1);
            foreach ($configuration as $name => $value) {
                $module->SetProperty($name, $value);
            }

            $module->ApplyChanges();

            self::assertFalse(IPS_GetProperty(3, 'Active'));
        }
    }

    public function testDoesNotDisableTransportOutsideManagedWorxChain(): void
    {
        IPS\Kernel::reset();
        IPS\InstanceManager::createInstance(1, [
            'Class'      => WorxCloudRequestTestDouble::class,
            'ModuleID'   => '{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}',
            'ModuleName' => 'Worx Cloud Test',
            'ModuleType' => 2,
        ]);
        IPS\InstanceManager::createInstance(2, [
            'Class'      => WorxCloudMqttParentTestDouble::class,
            'ModuleID'   => '{00000000-0000-0000-0000-000000000001}',
            'ModuleName' => 'Other MQTT Test',
            'ModuleType' => 2,
        ]);
        IPS\InstanceManager::createInstance(3, [
            'Class'      => WorxCloudWsTestDouble::class,
            'ModuleID'   => '{D68FD31F-0E90-7019-F16C-1949BD3079EF}',
            'ModuleName' => 'Worx WS Test',
            'ModuleType' => 1,
        ]);
        IPS\InstanceManager::connectInstance(2, 3);
        IPS\InstanceManager::connectInstance(1, 2);
        $module = IPS\InstanceManager::getInstanceInterface(1);
        $module->SetProperty('UseMQTT', false);
        $module->SetProperty('Email', 'user@example.invalid');
        $module->SetProperty('Password', 'secret');
        $module->ApplyChanges();

        self::assertTrue(IPS_GetProperty(3, 'Active'));
    }
}
