<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WorxMower/module.php';

final class WorxMowerTestDouble extends WorxMower
{
    public function readAttributeForTest(string $name): string
    {
        return $this->ReadAttributeString($name);
    }

    public function Update(): bool
    {
        return false;
    }

    protected function getTime()
    {
        return time();
    }
}

final class WorxMowerTranslationTestDouble extends WorxMower
{
    public function Translate($Text): string
    {
        return 'EN:' . $Text;
    }
}

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
            $presentation = IPS_GetVariable($variableID)['VariablePresentation'];

            self::assertNotSame(VARIABLE_PRESENTATION_LEGACY, $presentation['PRESENTATION'] ?? null);
            self::assertSame('', $variable['VariableProfile']);
            self::assertSame('', $variable['VariableCustomProfile']);
        }
    }

    public function testReregisteringVariablesClearsExistingCustomProfilesBeforeApplyingModernPresentations(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $method = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $method->setAccessible(true);
        $method->invoke($module);

        $variableID = IPS_GetObjectIDByIdent('TimeExtensionSet', $instanceID);
        $userVariableID = IPS_CreateVariable(1);
        IPS_SetParent($userVariableID, $instanceID);
        IPS_SetIdent($userVariableID, 'UserAddedVariable');
        $profile = 'WorxMower.ProfileMigrationTest';
        $userProfile = 'WorxMower.ProfileMigrationUserTest';
        IPS_CreateVariableProfile($profile, 1);
        IPS_CreateVariableProfile($userProfile, 1);
        try {
            IPS_SetVariableCustomProfile($variableID, $profile);
            IPS_SetVariableCustomProfile($userVariableID, $userProfile);
            self::assertSame($profile, IPS_GetVariable($variableID)['VariableCustomProfile']);
            self::assertSame($userProfile, IPS_GetVariable($userVariableID)['VariableCustomProfile']);

            $method->invoke($module);

            $variable = IPS_GetVariable($variableID);
            self::assertSame('', $variable['VariableProfile']);
            self::assertSame('', $variable['VariableCustomProfile']);
            self::assertSame(VARIABLE_PRESENTATION_SLIDER, $variable['VariablePresentation']['PRESENTATION']);
            self::assertSame(-100, $variable['VariablePresentation']['MIN']);
            self::assertSame(100, $variable['VariablePresentation']['MAX']);
            self::assertSame(' %', $variable['VariablePresentation']['SUFFIX']);
            self::assertSame($userProfile, IPS_GetVariable($userVariableID)['VariableCustomProfile']);
        } finally {
            if (IPS_GetVariable($variableID)['VariableCustomProfile'] !== '') {
                IPS_SetVariableCustomProfile($variableID, '');
            }
            if (IPS_GetVariable($userVariableID)['VariableCustomProfile'] !== '') {
                IPS_SetVariableCustomProfile($userVariableID, '');
            }
            IPS_DeleteVariable($userVariableID);
            IPS_DeleteVariableProfile($profile);
            IPS_DeleteVariableProfile($userProfile);
        }
    }

    public function testStatusAndErrorCodesUsePlainValuePresentationAndAreOrderedBesideText(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $method = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $method->setAccessible(true);
        $method->invoke($module);

        $statusID = IPS_GetObjectIDByIdent('State', $instanceID);
        $errorID = IPS_GetObjectIDByIdent('Error', $instanceID);
        $stateTextID = IPS_GetObjectIDByIdent('StateText', $instanceID);
        $errorTextID = IPS_GetObjectIDByIdent('ErrorText', $instanceID);
        foreach ([$statusID, $errorID, $stateTextID, $errorTextID] as $readOnlyID) {
            self::assertFalse(HasAction($readOnlyID));
            self::assertArrayNotHasKey('OPTIONS', IPS_GetVariable($readOnlyID)['VariablePresentation']);
        }

        // Emulate the old scattered positions already present on an installed instance.
        IPS_SetPosition(IPS_GetObjectIDByIdent('State', $instanceID), 80);
        IPS_SetPosition(IPS_GetObjectIDByIdent('StateText', $instanceID), 10);
        IPS_SetPosition(IPS_GetObjectIDByIdent('Error', $instanceID), 70);
        IPS_SetPosition(IPS_GetObjectIDByIdent('ErrorText', $instanceID), 20);
        $method->invoke($module);
        $positions = [];
        foreach (['State', 'StateText', 'Error', 'ErrorText'] as $ident) {
            $variableID = IPS_GetObjectIDByIdent($ident, $instanceID);
            $positions[$ident] = IPS_GetObject($variableID)['ObjectPosition'];
        }

        self::assertSame(0, $positions['State']);
        self::assertSame(1, $positions['StateText']);
        self::assertSame(2, $positions['Error']);
        self::assertSame(3, $positions['ErrorText']);

        $presentationMethod = new ReflectionMethod(WorxMower::class, 'valuePresentation');
        $presentationMethod->setAccessible(true);
        $presentation = $presentationMethod->invoke($module, '');
        self::assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $presentation['PRESENTATION']);
        self::assertSame('', $presentation['SUFFIX']);
        self::assertArrayNotHasKey('OPTIONS', $presentation);
    }

    public function testFirmwareAutoUpdateHasSeparateCapabilityGatedConfirmedAndCommandVariables(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);
        $enableAction = new ReflectionMethod(IPSModule::class, 'EnableAction');
        $enableAction->setAccessible(true);
        $enableAction->invoke($module, 'FirmwareAutoUpgradeSet');
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'PendingFirmwareAutoUpgrade', '');
        $registerAttribute->invoke($module, 'PendingFirmwareAutoUpgradeSerial', '');
        $updateFirmwareAutoUpgrade = new ReflectionMethod(WorxMower::class, 'updateFirmwareAutoUpgrade');
        $updateFirmwareAutoUpgrade->setAccessible(true);
        $device = [
            'online'                => true,
            'capabilities'          => ['ota_upgrade'],
            'firmware_auto_upgrade' => true,
            'last_status'           => ['payload' => ['dat' => []]],
        ];

        $updateFirmwareAutoUpgrade->invoke($module, $device);
        $confirmedID = IPS_GetObjectIDByIdent('FirmwareAutoUpgrade', $instanceID);
        $commandID = IPS_GetObjectIDByIdent('FirmwareAutoUpgradeSet', $instanceID);
        self::assertTrue(GetValueBoolean($confirmedID));
        self::assertTrue(GetValueBoolean($commandID));
        self::assertFalse(IPS_GetObject($confirmedID)['ObjectIsHidden']);
        self::assertFalse(IPS_GetObject($commandID)['ObjectIsHidden']);
        self::assertFalse(HasAction($confirmedID));
        self::assertTrue(HasAction($commandID));
        self::assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, IPS_GetVariable($confirmedID)['VariablePresentation']['PRESENTATION']);
        self::assertSame(VARIABLE_PRESENTATION_SWITCH, IPS_GetVariable($commandID)['VariablePresentation']['PRESENTATION']);

        $device['capabilities'] = [];
        $device['firmware_auto_upgrade'] = false;
        $updateFirmwareAutoUpgrade->invoke($module, $device);
        self::assertTrue(IPS_GetObject($confirmedID)['ObjectIsHidden']);
        self::assertTrue(IPS_GetObject($commandID)['ObjectIsHidden']);
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

    public function testRainDelayUsesWireRangeAndThirtyMinuteSteps(): void
    {
        $module = new WorxMower(1);
        $validation = new ReflectionMethod(WorxMower::class, 'validatedRainDelay');
        $validation->setAccessible(true);
        foreach ([0, 30, 180, 720] as $minutes) {
            self::assertSame($minutes, $validation->invoke($module, $minutes));
        }

        foreach ([-30, 1, 15, 31, 721] as $minutes) {
            try {
                $validation->invoke($module, $minutes);
                self::fail('Expected invalid rain-delay value ' . $minutes . ' to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $slider = new ReflectionMethod(WorxMower::class, 'sliderPresentation');
        $slider->setAccessible(true);
        $presentation = $slider->invoke($module, 0, 720, ' min', 30);
        self::assertSame(0, $presentation['MIN']);
        self::assertSame(720, $presentation['MAX']);
        self::assertSame(30, $presentation['STEP_SIZE']);
        self::assertSame(' min', $presentation['SUFFIX']);
    }

    public function testWorxAppChangesSynchronizeSettingInputsWithConfirmedDeviceValues(): void
    {
        IPS\Kernel::reset();
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);

        $registerProperty = new ReflectionMethod(IPSModule::class, 'RegisterPropertyString');
        $registerProperty->setAccessible(true);
        $registerProperty->invoke($module, 'Serial', 'SERIAL-TEST');
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        foreach ([
            'PendingCommand', 'PendingRainDelay', 'PendingRainDelaySerial', 'ReportedRainDelay',
            'PendingLock', 'PendingLockSerial', 'PendingSchedule', 'PendingScheduleSerial',
            'PendingSchedulePurpose', 'ReportedSchedule', 'FailedSchedule', 'FailedScheduleSerial',
        ] as $attribute) {
            $default = $attribute === 'PendingSchedulePurpose' ? 'schedule' : '';
            $registerAttribute->invoke($module, $attribute, $default);
        }

        $rainDelaySetID = IPS_GetObjectIDByIdent('RainDelaySet', $instanceID);
        $timeExtensionSetID = IPS_GetObjectIDByIdent('TimeExtensionSet', $instanceID);
        $lockCommandID = IPS_GetObjectIDByIdent('LockCommand', $instanceID);
        SetValue($rainDelaySetID, 180);
        SetValue($timeExtensionSetID, -20);
        SetValue($lockCommandID, true);

        $applyDevice = new ReflectionMethod(WorxMower::class, 'applyDevice');
        $applyDevice->setAccessible(true);
        foreach ([
            ['rd' => 330, 'p' => 30, 'lock' => 0],
            ['rd' => 240, 'p' => -50, 'lock' => 1],
        ] as $report) {
            $applyDevice->invoke($module, [
                'online'       => true,
                'protocol'     => 0,
                'capabilities' => ['rain_delay', 'unrestricted_mowing_time', 'lock'],
                'last_status'  => [
                    'payload' => [
                        'cfg' => [
                            'rd' => $report['rd'],
                            'sc' => ['p' => $report['p'], 'd' => [['17:00', 120, 1]]],
                        ],
                        'dat' => ['ls' => 1, 'le' => 0, 'lk' => $report['lock']],
                    ],
                ],
            ]);
            self::assertSame($report['rd'], GetValue($rainDelaySetID));
            self::assertSame($report['p'], GetValue($timeExtensionSetID));
            self::assertSame((bool) $report['lock'], GetValue($lockCommandID));
        }

        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'PendingRainDelay', json_encode(['desired' => 330, 'previous' => 240], JSON_THROW_ON_ERROR));
        $writeAttribute->invoke($module, 'PendingRainDelaySerial', 'SERIAL-TEST');
        $writeAttribute->invoke($module, 'PendingLock', json_encode(['desired' => false, 'previous' => true], JSON_THROW_ON_ERROR));
        $writeAttribute->invoke($module, 'PendingLockSerial', 'SERIAL-TEST');
        $writeAttribute->invoke($module, 'PendingSchedule', json_encode([
            'p' => 40,
            'd' => [['17:00', 120, 1]],
        ], JSON_THROW_ON_ERROR));
        $writeAttribute->invoke($module, 'PendingScheduleSerial', 'SERIAL-TEST');
        $writeAttribute->invoke($module, 'PendingSchedulePurpose', 'time_extension');
        SetValue($rainDelaySetID, 330);
        SetValue($timeExtensionSetID, 40);
        SetValue($lockCommandID, false);

        $applyDevice->invoke($module, [
            'online'       => true,
            'protocol'     => 0,
            'capabilities' => ['rain_delay', 'unrestricted_mowing_time', 'lock'],
            'last_status'  => [
                'payload' => [
                    'cfg' => [
                        'rd' => 240,
                        'sc' => ['p' => -50, 'd' => [['17:00', 120, 1]]],
                    ],
                    'dat' => ['ls' => 1, 'le' => 0, 'lk' => 1],
                ],
            ],
        ]);
        self::assertSame(330, GetValue($rainDelaySetID));
        self::assertSame(40, GetValue($timeExtensionSetID));
        self::assertFalse(GetValue($lockCommandID));
    }

    public function testAutoScheduleAppEchoKeepsPendingInputUntilConfirmedAndThenTracksAppChanges(): void
    {
        IPS\Kernel::reset();
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMowerTestDouble($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);

        $registerProperty = new ReflectionMethod(IPSModule::class, 'RegisterPropertyString');
        $registerProperty->setAccessible(true);
        $registerProperty->invoke($module, 'Serial', 'SERIAL-TEST');
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        foreach ([
            'PendingCommand', 'PendingCommandSerial',
            'PendingRainDelay', 'PendingRainDelaySerial', 'ReportedRainDelay',
            'PendingLock', 'PendingLockSerial',
            'PendingAutoSchedule', 'PendingAutoScheduleSerial',
            'PendingFirmwareAutoUpgrade', 'PendingFirmwareAutoUpgradeSerial',
            'PendingSchedule', 'PendingScheduleSerial', 'PendingSchedulePurpose',
            'ReportedSchedule', 'FailedSchedule', 'FailedScheduleSerial',
        ] as $attribute) {
            $default = $attribute === 'PendingSchedulePurpose' ? 'schedule' : '';
            $registerAttribute->invoke($module, $attribute, $default);
        }
        $registerTimer = new ReflectionMethod(IPSModule::class, 'RegisterTimer');
        $registerTimer->setAccessible(true);
        $registerTimer->invoke($module, 'AutoScheduleConfirmationTimeout', 0, '');

        $applyDevice = new ReflectionMethod(WorxMower::class, 'applyDevice');
        $applyDevice->setAccessible(true);
        $device = [
            'online'        => true,
            'protocol'      => 0,
            'capabilities'  => [],
            'auto_schedule' => false,
            'last_status'   => ['payload' => [
                'cfg' => ['sc' => ['m' => 1, 'p' => 0, 'd' => [['17:00', 120, 1]]]],
                'dat' => ['ls' => 1, 'le' => 0],
            ]],
        ];
        $applyDevice->invoke($module, $device);

        $confirmedID = IPS_GetObjectIDByIdent('AutoSchedule', $instanceID);
        $setID = IPS_GetObjectIDByIdent('AutoScheduleSet', $instanceID);
        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'PendingAutoSchedule', '{"desired":true,"previous":false}');
        $writeAttribute->invoke($module, 'PendingAutoScheduleSerial', 'SERIAL-TEST');
        SetValue($setID, true);

        $applyDevice->invoke($module, $device);
        self::assertFalse(GetValueBoolean($confirmedID));
        self::assertTrue(GetValueBoolean($setID));
        self::assertNotSame('', $module->readAttributeForTest('PendingAutoSchedule'));

        $device['auto_schedule'] = true;
        $applyDevice->invoke($module, $device);
        self::assertTrue(GetValueBoolean($confirmedID));
        self::assertTrue(GetValueBoolean($setID));
        self::assertSame('', $module->readAttributeForTest('PendingAutoSchedule'));

        $device['auto_schedule'] = false;
        $applyDevice->invoke($module, $device);
        self::assertFalse(GetValueBoolean($confirmedID));
        self::assertFalse(GetValueBoolean($setID));
    }

    public function testRainDelayConfirmationIgnoresStaleEchoAndAdoptsLaterAppChange(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMowerTestDouble($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);

        $registerProperty = new ReflectionMethod(IPSModule::class, 'RegisterPropertyString');
        $registerProperty->setAccessible(true);
        $registerProperty->invoke($module, 'Serial', 'SERIAL-TEST');
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'ReportedRainDelay', '180');
        $registerAttribute->invoke($module, 'PendingRainDelay', json_encode(['desired' => 330, 'previous' => 180]));
        $registerAttribute->invoke($module, 'PendingRainDelaySerial', 'SERIAL-TEST');
        $registerTimer = new ReflectionMethod(IPSModule::class, 'RegisterTimer');
        $registerTimer->setAccessible(true);
        $registerTimer->invoke($module, 'RainDelayConfirmationTimeout', 120000, '', 0);
        $settingStatusID = IPS_GetObjectIDByIdent('SettingStatus', $instanceID);
        SetValue($settingStatusID, 'Regenverzögerung gesendet; Rückmeldung des Mähers steht aus.');

        $confirm = new ReflectionMethod(WorxMower::class, 'confirmRainDelay');
        $confirm->setAccessible(true);
        $confirm->invoke($module, 180);
        self::assertSame('180', $module->readAttributeForTest('ReportedRainDelay'));
        self::assertSame(330, json_decode($module->readAttributeForTest('PendingRainDelay'), true)['desired']);
        self::assertSame('Regenverzögerung gesendet; Rückmeldung des Mähers steht aus.', GetValue($settingStatusID));

        $confirm->invoke($module, 330);
        self::assertSame('', $module->readAttributeForTest('PendingRainDelay'));
        self::assertSame('', $module->readAttributeForTest('PendingRainDelaySerial'));
        self::assertSame('Regenverzögerung vom Mäher zurückgelesen und bestätigt.', GetValue($settingStatusID));

        $confirm->invoke($module, 180);
        self::assertSame('180', $module->readAttributeForTest('ReportedRainDelay'));
        self::assertSame('Regenverzögerung aus Worx übernommen: 180 Minuten.', GetValue($settingStatusID));
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

    public function testManualEdgeCutAppearsOnlyWhenProtocolAndCapabilitySupportIt(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $register = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $register->setAccessible(true);
        $register->invoke($module);
        $apply = new ReflectionMethod(WorxMower::class, 'applyDevice');
        $apply->setAccessible(true);
        $controlID = IPS_GetObjectIDByIdent('Control', $instanceID);

        $apply->invoke($module, ['protocol' => 1, 'capabilities' => ['follow_border']]);
        self::assertNotContains(4, $this->controlOptions($controlID));

        $apply->invoke($module, ['protocol' => 0, 'capabilities' => []]);
        self::assertNotContains(4, $this->controlOptions($controlID));

        $apply->invoke($module, ['protocol' => 0, 'capabilities' => ['follow_border']]);
        self::assertContains(4, $this->controlOptions($controlID));

        $apply->invoke($module, ['protocol' => 0, 'capabilities' => []]);
        self::assertNotContains(4, $this->controlOptions($controlID));
    }

    public function testStatusAndErrorTextVariablesContainReadableConfirmedLabels(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMower($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        $registerAttribute->invoke($module, 'PendingCommand', '');
        $registerAttribute->invoke($module, 'PendingSchedule', '');
        $registerAttribute->invoke($module, 'PendingSchedulePurpose', 'schedule');
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

        $method->invoke($module, [
            'online'       => true,
            'protocol'     => 0,
            'capabilities' => [],
            'last_status'  => ['payload' => ['dat' => ['ls' => 7, 'le' => 5]]],
        ]);
        self::assertSame('Mäht', GetValue($stateTextID));
        self::assertSame('Regen', GetValue($errorTextID));
    }

    public function testMowerStateAndErrorTextUseSymconTranslations(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMowerTranslationTestDouble($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);
        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        foreach ([
            'PendingCommand', 'PendingSchedule', 'PendingSchedulePurpose',
        ] as $attribute) {
            $registerAttribute->invoke($module, $attribute, $attribute === 'PendingSchedulePurpose' ? 'schedule' : '');
        }

        $applyDevice = new ReflectionMethod(WorxMower::class, 'applyDevice');
        $applyDevice->setAccessible(true);
        $applyDevice->invoke($module, [
            'online'       => true,
            'protocol'     => 0,
            'capabilities' => [],
            'last_status'  => ['payload' => ['dat' => ['ls' => 7, 'le' => 5]]],
        ]);

        $stateTextID = IPS_GetObjectIDByIdent('StateText', $instanceID);
        $errorTextID = IPS_GetObjectIDByIdent('ErrorText', $instanceID);
        self::assertSame('EN:Mäht', GetValue($stateTextID));
        self::assertSame('EN:Regen', GetValue($errorTextID));

        $locale = json_decode(file_get_contents(__DIR__ . '/../WorxMower/locale.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Mowing', $locale['translations']['en']['Mäht']);
        self::assertSame('Rain', $locale['translations']['en']['Regen']);
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
        $registerAttribute->invoke($module, 'PendingSchedule', '');
        $registerAttribute->invoke($module, 'PendingSchedulePurpose', 'schedule');
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

    public function testTimedOutSettingsRestoreTheLastConfirmedInputs(): void
    {
        $instanceID = IPS\ObjectManager::registerObject(1);
        $module = new WorxMowerTestDouble($instanceID);
        $registerVariables = new ReflectionMethod(WorxMower::class, 'registerVariables');
        $registerVariables->setAccessible(true);
        $registerVariables->invoke($module);

        $registerAttribute = new ReflectionMethod(IPSModule::class, 'RegisterAttributeString');
        $registerAttribute->setAccessible(true);
        foreach ([
            'PendingAutoSchedule', 'PendingAutoScheduleSerial',
            'PendingLock', 'PendingLockSerial',
            'PendingRainDelay', 'PendingRainDelaySerial',
            'PendingSchedule', 'PendingScheduleSerial', 'PendingSchedulePurpose',
            'FailedSchedule', 'FailedScheduleSerial',
        ] as $attribute) {
            $default = $attribute === 'PendingSchedulePurpose' ? 'schedule' : '';
            $registerAttribute->invoke($module, $attribute, $default);
        }

        $registerTimer = new ReflectionMethod(IPSModule::class, 'RegisterTimer');
        $registerTimer->setAccessible(true);
        foreach ([
            'AutoScheduleConfirmationTimeout',
            'LockConfirmationTimeout',
            'RainDelayConfirmationTimeout',
            'ScheduleConfirmationTimeout',
        ] as $timer) {
            $registerTimer->invoke($module, $timer, 0, '');
        }

        $variables = [
            'AutoSchedule'           => false,
            'AutoScheduleSet'        => true,
            'Locked'                 => false,
            'LockCommand'            => true,
            'RainDelay'              => 180,
            'RainDelaySet'           => 330,
            'TimeExtension'          => -40,
            'TimeExtensionSet'       => 60,
        ];
        $ids = [];
        foreach ($variables as $ident => $value) {
            $ids[$ident] = IPS_GetObjectIDByIdent($ident, $instanceID);
            SetValue($ids[$ident], $value);
        }

        $writeAttribute = new ReflectionMethod(IPSModule::class, 'WriteAttributeString');
        $writeAttribute->setAccessible(true);
        $writeAttribute->invoke($module, 'PendingAutoSchedule', '{"desired":true,"previous":false}');
        $module->AutoScheduleConfirmationTimeout();
        self::assertFalse(GetValue($ids['AutoScheduleSet']));

        $writeAttribute->invoke($module, 'PendingLock', '{"desired":true,"previous":false}');
        $module->LockConfirmationTimeout();
        self::assertFalse(GetValue($ids['LockCommand']));

        $writeAttribute->invoke($module, 'PendingRainDelay', '{"desired":330,"previous":180}');
        $module->RainDelayConfirmationTimeout();
        self::assertSame(180, GetValue($ids['RainDelaySet']));

        $writeAttribute->invoke($module, 'PendingSchedule', '{"p":60,"d":[]}');
        $writeAttribute->invoke($module, 'PendingSchedulePurpose', 'time_extension');
        $module->ScheduleConfirmationTimeout();
        self::assertSame(-40, GetValue($ids['TimeExtensionSet']));
    }

    private function controlOptions(int $controlID): array
    {
        $presentation = IPS_GetVariable($controlID)['VariablePresentation'];
        $options = json_decode($presentation['OPTIONS'], true, 512, JSON_THROW_ON_ERROR);
        return array_map(static fn (array $option): int => (int) $option['Value'], $options);
    }

}
