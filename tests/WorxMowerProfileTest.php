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

    public function testRegisteredMowerVariablesDoNotUseLegacyProfiles(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $method = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $method->setAccessible(true);
        $method->invoke($module);

        foreach (IPS\VariableManager::getVariableList() as $variableID) {
            $variable = IPS_GetVariable($variableID);
            $presentation = IPS\VariableManager::getVariablePresentation($variableID);

            self::assertNotSame(VARIABLE_PRESENTATION_LEGACY, $presentation['PRESENTATION'] ?? null);
            self::assertSame('', $variable['VariableProfile']);
            self::assertSame('', $variable['VariableCustomProfile']);
        }
    }
    public function testConfirmedTimeExtensionUsesValuePresentationWithLiteralPercentSuffix(): void
    {
        $module = new WorxMower(1);
        $method = new ReflectionMethod(WorxMower::class, 'valuePresentation');
        $method->setAccessible(true);

        $presentation = $method->invoke($module, ' %');

        self::assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $presentation['PRESENTATION']);
        self::assertSame(' %', $presentation['SUFFIX']);
        self::assertArrayNotHasKey('PERCENTAGE', $presentation);
    }

    public function testTimeExtensionInputSliderSupportsFullSignedAppRange(): void
    {
        $module = new WorxMower(1);
        $method = new ReflectionMethod(WorxMower::class, 'sliderPresentation');
        $method->setAccessible(true);

        $presentation = $method->invoke($module, -100, 100, ' %');

        self::assertSame(VARIABLE_PRESENTATION_SLIDER, $presentation['PRESENTATION']);
        self::assertSame(-100, $presentation['MIN']);
        self::assertSame(100, $presentation['MAX']);
        self::assertSame(1, $presentation['STEP_SIZE']);
        self::assertFalse($presentation['PERCENTAGE']);
        self::assertSame(' %', $presentation['SUFFIX']);
    }

    public function testPresentationOptionsProvideSymconEditorDefaults(): void
    {
        $module = new WorxMower(1);
        $requiredKeys = ['IconActive', 'IconValue', 'ColorActive', 'ColorValue', 'ContentColorActive', 'ContentColorValue'];

        foreach ([
            ['booleanValuePresentation', ['Aus', 'Ein']],
            ['enumerationPresentation', [[0 => 'Aus', 1 => 'Ein']]],
        ] as [$methodName, $arguments]) {
            $method = new ReflectionMethod(WorxMower::class, $methodName);
            $method->setAccessible(true);
            $presentation = $method->invokeArgs($module, $arguments);
            $options = json_decode($presentation['OPTIONS'], true, 512, JSON_THROW_ON_ERROR);

            foreach ($options as $option) {
                foreach ($requiredKeys as $key) {
                    self::assertArrayHasKey($key, $option, $methodName . ' option is missing ' . $key);
                }
                self::assertFalse($option['IconActive']);
                self::assertFalse($option['ColorActive']);
                self::assertFalse($option['ContentColorActive']);
            }
        }
    }

    public function testStatusAndErrorTextVariablesContainReadableConfirmedLabels(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $module->Create();
        $method = new ReflectionMethod(WorxMower::class, 'applyDevice');
        $method->setAccessible(true);
        $method->invoke($module, [
            'online'       => true,
            'protocol'     => 0,
            'capabilities' => [],
            'last_status'  => ['payload' => ['dat' => ['ls' => 1, 'le' => 0]]],
        ]);

        $stateTextID = IPS_GetObjectIDByIdent('StateText', $instanceID);
        $errorTextID = IPS_GetObjectIDByIdent('ErrorText', $instanceID);
        self::assertSame(VARIABLETYPE_STRING, IPS_GetVariable($stateTextID)['VariableType']);
        self::assertSame(VARIABLETYPE_STRING, IPS_GetVariable($errorTextID)['VariableType']);
        self::assertSame('In der Ladestation', GetValue($stateTextID));
        self::assertSame('Kein Fehler', GetValue($errorTextID));
    }

    public function testMowerReportStoresNegativeAppScaleInConfirmedVariable(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $variableID = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetParent($variableID, $instanceID);
        IPS_SetIdent($variableID, 'TimeExtension');

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
                    'cfg' => ['sc' => ['p' => -40]],
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
            'cfg'          => ['sc' => ['p' => -40]],
            'last_status'  => ['payload' => ['dat' => ['ls' => 0, 'le' => 0]]],
        ]);

        self::assertSame(-40, GetValue($variableID));
    }
}