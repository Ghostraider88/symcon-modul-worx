<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WorxMower/module.php';

final class WorxMowerProfileTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\ProfileManager::reset();
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
}