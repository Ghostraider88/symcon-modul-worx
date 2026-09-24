<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/WorxScheduleCodec.php';

final class WorxScheduleCodecTest extends TestCase
{
    private function makeSchedule(bool $secondary = false): array
    {
        $schedule = [
            'd' => [],
            'metadata' => ['keep' => true],
        ];
        if ($secondary) {
            $schedule['dd'] = [];
        }

        for ($day = 0; $day < 7; $day++) {
            $schedule['d'][] = ['08:00', 60, 0, 'unknown-slot-field'];
            if ($secondary) {
                $schedule['dd'][] = ['18:00', 30, 1, 'unknown-secondary-field'];
            }
        }

        return $schedule;
    }

    private function makeDevice(array $schedule, int $protocol = 0): array
    {
        return [
            'protocol' => $protocol,
            'last_status' => [
                'payload' => [
                    'cfg' => ['sc' => $schedule],
                ],
            ],
        ];
    }

    public function testReadsSundayFirstWireSlotsInAppWeekdayOrder(): void
    {
        $schedule = $this->makeSchedule();
        $device = $this->makeDevice($schedule);

        self::assertTrue(WorxScheduleCodec::supportsSchedule($device));
        $rows = WorxScheduleCodec::toRows($schedule);
        self::assertCount(7, $rows);
        self::assertSame('Montag', $rows[0]['DayName']);
        self::assertSame('Dienstag', $rows[1]['DayName']);
        self::assertSame('Sonntag', $rows[6]['DayName']);
    }

    public function testPreservesUnknownScheduleAndTupleFields(): void
    {
        $source = $this->makeSchedule();
        $rows = WorxScheduleCodec::toRows($source);
        $rows[1]['Start'] = '09:15';
        $rows[1]['Minutes'] = 75;
        $rows[1]['Border'] = true;

        $updated = WorxScheduleCodec::mergeRows($source, $rows);

        self::assertSame(['08:00', 60, 0, 'unknown-slot-field'], $updated['d'][0]);
        self::assertSame(['09:15', 75, 1, 'unknown-slot-field'], $updated['d'][1]);
        self::assertSame($source['metadata'], $updated['metadata']);
    }

    public function testMapsBothReceivedScheduleArrays(): void
    {
        $schedule = $this->makeSchedule(true);

        self::assertCount(14, WorxScheduleCodec::toRows($schedule));
        $rows = WorxScheduleCodec::toRows($schedule);
        $rows[1]['Minutes'] = 45;
        $updated = WorxScheduleCodec::mergeRows($schedule, $rows);

        self::assertSame(45, $updated['dd'][0][1]);
        self::assertSame('unknown-secondary-field', $updated['dd'][0][3]);
    }

    public function testSanitizedDeviceRecordKeepsRequiredFieldsAndRemovesIdentifiers(): void
    {
        $device = $this->makeDevice($this->makeSchedule());
        $device['firmware_version'] = '3.52.0+1';
        $device['capabilities'] = ['mqtt', 'rain_delay', 'multi_zone', 'lock'];
        $device['capabilities_available'] = ['display_pairing_shortcut'];
        $device['features'] = ['rain_delay' => true, 'multi_zone_zones' => 4, 'private_location' => 'must-not-be-exported'];
        $device['auto_schedule'] = false;
        $device['locked'] = false;
        $device['serial_number'] = 'must-not-be-exported';
        $device['setup_location'] = ['latitude' => 1.23, 'longitude' => 4.56];
        $device['last_status']['payload']['cfg']['rd'] = 120;
        $device['last_status']['payload']['cfg']['mz'] = [1, 2, 3, 4];
        $device['last_status']['payload']['cfg']['sc']['sn'] = 'must-not-be-exported';
        $device['last_status']['payload']['dat'] = ['tq' => -10, 'lz' => 2, 'mac' => 'must-not-be-exported'];
        $device['last_status']['payload']['cfg']['sc']['extension'] = [
            'location' => 'must-not-be-exported',
            'preserved' => true,
        ];

        $record = WorxScheduleCodec::sanitizedDeviceRecord($device);

        self::assertSame('3.52.0+1', $record['firmware_version']);
        self::assertSame(0, $record['protocol']);
        self::assertSame(['mqtt', 'rain_delay', 'multi_zone', 'lock'], $record['capabilities']);
        self::assertSame(['display_pairing_shortcut'], $record['capabilities_available']);
        self::assertSame(['multi_zone_zones' => 4, 'rain_delay' => true], $record['features']);
        self::assertSame(120, $record['cfg']['rd']);
        self::assertSame([1, 2, 3, 4], $record['cfg']['mz']);
        self::assertSame(['tq' => -10, 'lz' => 2], $record['dat']);
        self::assertFalse($record['auto_schedule']);
        self::assertFalse($record['locked']);
        self::assertArrayNotHasKey('serial_number', $record);
        self::assertArrayNotHasKey('setup_location', $record);
        self::assertArrayNotHasKey('sn', $record['cfg']['sc']);
        self::assertArrayNotHasKey('mac', $record['dat']);
        self::assertSame(['preserved' => true], $record['cfg']['sc']['extension']);
    }
    public function testMapsMidnightStartAndRoundTripsNativeEventPoints(): void
    {
        $schedule = $this->makeSchedule();
        $schedule['d'][1] = ['00:00', 60, 1, 'unknown-slot-field'];
        $rows = WorxScheduleCodec::toRows($schedule);
        $eventPoints = WorxScheduleCodec::toEventPoints($schedule);

        self::assertSame(2, $eventPoints[0][0]['Action']);
        self::assertSame(0, $eventPoints[0][1]['Action']);

        $groups = [];
        foreach ($eventPoints as $day => $points) {
            $groups[] = [
                'Days' => 1 << $day,
                'Points' => array_map(static function (array $point): array {
                    return [
                        'Start' => [
                            'Hour' => intdiv($point['Minute'], 60),
                            'Minute' => $point['Minute'] % 60,
                        ],
                        'ActionID' => $point['Action'],
                    ];
                }, $points),
            ];
        }

        $decodedRows = WorxScheduleCodec::rowsFromEvent(['EventType' => 2, 'ScheduleGroups' => $groups], $schedule);
        $expectedRows = array_map(static function (array $row): array {
            return [
                'Day' => $row['Day'],
                'Slot' => $row['Slot'],
                'Enabled' => $row['Enabled'],
                'Start' => $row['Start'],
                'Minutes' => $row['Minutes'],
                'Border' => $row['Border'],
            ];
        }, $rows);
        self::assertSame($expectedRows, $decodedRows);
    }

    public function testRejectsUnsupportedOrMalformedSchedule(): void
    {
        $schedule = $this->makeSchedule();
        self::assertNull(WorxScheduleCodec::scheduleFromDevice($this->makeDevice($schedule, 1)));

        $schedule['dd'] = [['08:00', 30, 0]];
        self::assertNull(WorxScheduleCodec::scheduleFromDevice($this->makeDevice($schedule)));
    }

    public function testRejectsInvalidStart(): void
    {
        $schedule = $this->makeSchedule();
        $rows = WorxScheduleCodec::toRows($schedule);
        $rows[0]['Start'] = '24:00';
        $this->expectException(InvalidArgumentException::class);
        WorxScheduleCodec::mergeRows($schedule, $rows);
    }

    public function testRejectsOutOfRangeDuration(): void
    {
        $schedule = $this->makeSchedule();
        $rows = WorxScheduleCodec::toRows($schedule);
        $rows[0]['Minutes'] = 1441;
        $this->expectException(InvalidArgumentException::class);
        WorxScheduleCodec::mergeRows($schedule, $rows);
    }

    public function testRejectsEnabledEntryWithoutDuration(): void
    {
        $schedule = $this->makeSchedule();
        $rows = WorxScheduleCodec::toRows($schedule);
        $rows[0]['Minutes'] = 0;
        $rows[0]['Enabled'] = true;
        $this->expectException(InvalidArgumentException::class);
        WorxScheduleCodec::mergeRows($schedule, $rows);
    }
}
