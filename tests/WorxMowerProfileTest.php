<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WorxMower/module.php';

final class WorxMowerProfileTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
    }

    public function testTimeExtensionProfileIsDedicatedAndUsesTheAppRange(): void
    {
        IPS_CreateVariableProfile('WORX.Percent', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('WORX.Percent', 0, 100, 1);

        $module = new WorxMower(1);
        $method = new ReflectionMethod(WorxMower::class, 'registerProfiles');
        $method->setAccessible(true);
        $method->invoke($module);

        $genericProfile = IPS_GetVariableProfile('WORX.Percent');
        self::assertSame(0.0, $genericProfile['MinValue']);
        self::assertSame(100.0, $genericProfile['MaxValue']);

        $timeExtensionProfile = IPS_GetVariableProfile('WORXMOWER.TimeExtension');
        self::assertSame(VARIABLETYPE_INTEGER, $timeExtensionProfile['ProfileType']);
        self::assertSame(-100.0, $timeExtensionProfile['MinValue']);
        self::assertSame(100.0, $timeExtensionProfile['MaxValue']);
        self::assertSame(1.0, $timeExtensionProfile['StepSize']);
    }

    public function testExistingVariablesReceiveTheUpdatedNegativeRangeProfile(): void
    {
        IPS_CreateVariableProfile('WORX.Percent', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('WORX.Percent', 0, 100, 1);
        IPS_CreateVariableProfile('WORXMOWER.TimeExtension', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('WORXMOWER.TimeExtension', -100, 100, 1);

        $instanceID = IPS\ObjectManager::registerObject(1);
        foreach (['TimeExtension', 'TimeExtensionSet'] as $ident) {
            $variableID = IPS_CreateVariable(VARIABLETYPE_INTEGER);
            IPS_SetParent($variableID, $instanceID);
            IPS_SetIdent($variableID, $ident);
            IPS_SetVariableCustomProfile($variableID, 'WORX.Percent');
        }

        $module = new WorxMower($instanceID);
        $method = new ReflectionMethod(WorxMower::class, 'applyTimeExtensionProfiles');
        $method->setAccessible(true);
        $method->invoke($module);

        foreach (['TimeExtension', 'TimeExtensionSet'] as $ident) {
            $variableID = IPS_GetObjectIDByIdent($ident, $instanceID);
            self::assertSame('WORXMOWER.TimeExtension', IPS_GetVariable($variableID)['VariableCustomProfile']);
        }
    }

    public function testMowerReportStoresNegativeAppScaleInConfirmedVariable(): void
    {
        IPS_CreateVariableProfile('WORXMOWER.TimeExtension', VARIABLETYPE_INTEGER);
        IPS_SetVariableProfileValues('WORXMOWER.TimeExtension', -100, 100, 1);

        $instanceID = IPS\ObjectManager::registerObject(1);
        $variableID = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetParent($variableID, $instanceID);
        IPS_SetIdent($variableID, 'TimeExtension');
        IPS_SetVariableCustomProfile($variableID, 'WORXMOWER.TimeExtension');

        $module = new WorxMower($instanceID);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'PendingCommand', '');
        $method = new ReflectionMethod(WorxMower::class, 'applyDevice');
        $method->setAccessible(true);
        $method->invoke($module, [
            'online'       => true,
            'protocol'     => 0,
            'capabilities' => ['unrestricted_mowing_time'],
            'last_status'  => [
                'payload' => [
                    'cfg' => ['sc' => ['p' => 30]],
                    'dat' => ['ls' => 0, 'le' => 0],
                ],
            ],
        ]);

        self::assertSame(-40, GetValue($variableID));
        // The account inventory exposes the same configuration at the device root.
        $method->invoke($module, [
            'online'       => true,
            'protocol'     => 0,
            'capabilities' => ['unrestricted_mowing_time'],
            'cfg'          => ['sc' => ['p' => 30]],
            'last_status'  => ['payload' => ['dat' => ['ls' => 0, 'le' => 0]]],
        ]);

        self::assertSame(-40, GetValue($variableID));
    }
}
