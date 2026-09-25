<?php

declare(strict_types=1);

/**
 * Codec for received Worx protocol-0 weekly schedules.
 *
 * Only the known time, duration and border fields are editable. Unknown
 * schedule keys and tuple values beyond index 2 are copied through unchanged.
 */
final class WorxScheduleCodec
{
    public const DAYS = [
        'Sonntag',
        'Montag',
        'Dienstag',
        'Mittwoch',
        'Donnerstag',
        'Freitag',
        'Samstag',
    ];

    private const DISPLAY_DAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

    private const SLOT_KEYS = [
        0 => 'd',
        1 => 'dd',
    ];

    public static function deviceConfiguration(array $device): array
    {
        $payloadConfig = $device['last_status']['payload']['cfg'] ?? null;
        if (is_array($payloadConfig) && $payloadConfig !== []) {
            return $payloadConfig;
        }

        $configuration = $device['cfg'] ?? [];
        return is_array($configuration) ? $configuration : [];
    }

    /**
     * Return a schedule only if protocol 0 and all received slots are valid.
     */
    public static function scheduleFromDevice(array $device): ?array
    {
        if ((int) ($device['protocol'] ?? -1) !== 0) {
            return null;
        }

        $configuration = self::deviceConfiguration($device);
        $schedule = $configuration['sc'] ?? null;
        if (!is_array($schedule) || !self::hasValidSlots($schedule, 'd')) {
            return null;
        }
        if (array_key_exists('dd', $schedule) && !self::hasValidSlots($schedule, 'dd')) {
            return null;
        }

        return $schedule;
    }

    public static function supportsSchedule(array $device): bool
    {
        return self::scheduleFromDevice($device) !== null;
    }

    /** Read the app-scale percentage from the protocol value, including signed mower echoes. */
    public static function timeExtensionFromProtocol($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $raw = (float) $value;
        if ($raw < -100 || $raw > 100) {
            return null;
        }

        // The app maps -100..100 to non-negative protocol values 0..100.
        // Some observed mower echoes also carry the app value directly as a negative number.
        return (int) round($raw < 0 ? $raw : ($raw * 2) - 100);
    }

    /** Convert the app percentage (-100..100) to the non-negative protocol schedule value (0..100). */
    public static function timeExtensionToProtocol(int $percent)
    {
        if ($percent < -100 || $percent > 100) {
            throw new InvalidArgumentException('Tägliche Arbeitszeit muss zwischen -100 und 100 Prozent liegen.');
        }

        $raw = $percent + 100;
        return $raw % 2 === 0 ? intdiv($raw, 2) : $raw / 2;
    }

    /**
     * Return only the mower details needed for feature research, excluding device
     * and account identifiers from the cached cloud object.
     */
    public static function sanitizedDeviceRecord(array $device): array
    {
        $configuration = self::deviceConfiguration($device);
        $safeConfiguration = [];
        foreach (['sc', 'rd', 'mz', 'mzv', 'mzk', 'al', 'modules'] as $key) {
            if (array_key_exists($key, $configuration)) {
                $value = $configuration[$key];
                $safeConfiguration[$key] = is_array($value) ? self::removeSensitiveKeys($value) : $value;
            }
        }

        $statusPayload = $device['last_status']['payload'] ?? [];
        $reportedDat = is_array($statusPayload) && is_array($statusPayload['dat'] ?? null) ? $statusPayload['dat'] : [];
        $safeDat = [];
        foreach (['fw', 'fwb', 'ls', 'le', 'tq', 'lz', 'rain', 'modules'] as $key) {
            if (array_key_exists($key, $reportedDat)) {
                $value = $reportedDat[$key];
                $safeDat[$key] = is_array($value) ? self::removeSensitiveKeys($value) : $value;
            }
        }

        $capabilities = $device['capabilities'] ?? [];
        $available = $device['capabilities_available'] ?? [];
        $features = $device['features'] ?? [];
        $safeFeatureKeys = ['auto_lock', 'lock', 'multi_zone', 'multi_zone_percentage', 'multi_zone_zones', 'one_time_scheduler', 'ota_upgrade', 'rain_delay', 'rain_delay_start', 'scheduler_two_slots', 'unrestricted_mowing_time'];
        $safeFeatures = [];
        if (is_array($features)) {
            foreach ($safeFeatureKeys as $key) {
                if (array_key_exists($key, $features) && (is_scalar($features[$key]) || $features[$key] === null)) {
                    $safeFeatures[$key] = $features[$key];
                }
            }
        }

        return [
            'firmware_version'      => isset($device['firmware_version']) ? (string) $device['firmware_version'] : null,
            'firmware_auto_upgrade' => isset($device['firmware_auto_upgrade']) ? (bool) $device['firmware_auto_upgrade'] : null,
            'protocol'              => isset($device['protocol']) && is_numeric($device['protocol']) ? (int) $device['protocol'] : null,
            'capabilities'          => is_array($capabilities)
                ? array_values(array_filter($capabilities, static function ($value): bool
                {
                    return is_string($value);
                }))
                : [],
            'capabilities_available' => is_array($available)
                ? array_values(array_filter($available, static function ($value): bool
                {
                    return is_string($value);
                }))
                : [],
            'features'      => $safeFeatures,
            'auto_schedule' => isset($device['auto_schedule']) ? (bool) $device['auto_schedule'] : null,
            'locked'        => isset($device['locked']) ? (bool) $device['locked'] : null,
            'cfg'           => $safeConfiguration,
            'dat'           => $safeDat,
        ];
    }

    public static function hasSecondarySchedule(array $schedule): bool
    {
        return self::hasValidSlots($schedule, 'dd');
    }

    /**
     * Convert received Sunday-to-Saturday slot arrays to editable List rows.
     *
     * @throws InvalidArgumentException
     */
    public static function toRows(array $schedule): array
    {
        if (!self::hasValidSlots($schedule, 'd')) {
            throw new InvalidArgumentException('The confirmed primary schedule must contain seven valid protocol-0 entries.');
        }
        if (array_key_exists('dd', $schedule) && !self::hasValidSlots($schedule, 'dd')) {
            throw new InvalidArgumentException('The received secondary schedule is incomplete or unsupported.');
        }

        $rows = [];
        $keys = self::hasSecondarySchedule($schedule) ? self::SLOT_KEYS : [0 => 'd'];
        foreach (self::DISPLAY_DAY_ORDER as $day) {
            foreach ($keys as $slot => $key) {
                $tuple = $schedule[$key][$day];
                $rows[] = [
                    'Day'      => (int) $day,
                    'DayName'  => self::DAYS[$day],
                    'Slot'     => (int) $slot,
                    'SlotName' => 'Einsatz ' . ((int) $slot + 1),
                    'Enabled'  => (int) $tuple[1] > 0,
                    'Start'    => (string) $tuple[0],
                    'Minutes'  => (int) $tuple[1],
                    'Border'   => (int) $tuple[2] > 0,
                ];
            }
        }

        return $rows;
    }

    /**
     * Validate editable rows and merge their known values into the source.
     *
     * @throws InvalidArgumentException
     */
    public static function mergeRows(array $source, array $rows): array
    {
        if (!self::hasValidSlots($source, 'd')) {
            throw new InvalidArgumentException('The confirmed primary schedule must contain seven protocol-0 entries.');
        }
        if (array_key_exists('dd', $source) && !self::hasValidSlots($source, 'dd')) {
            throw new InvalidArgumentException('The received secondary schedule is incomplete or unsupported.');
        }

        $hasSecondary = self::hasSecondarySchedule($source);
        $expectedRows = $hasSecondary ? 14 : 7;
        if (count($rows) !== $expectedRows) {
            throw new InvalidArgumentException(sprintf('Expected %d schedule rows, received %d.', $expectedRows, count($rows)));
        }

        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Each schedule row must be an object.');
            }

            $day = self::integerInRange($row['Day'] ?? null, 'Day', 0, 6);
            $slot = self::integerInRange($row['Slot'] ?? null, 'Slot', 0, $hasSecondary ? 1 : 0);
            $seenKey = $slot . ':' . $day;
            if (isset($seen[$seenKey])) {
                throw new InvalidArgumentException('The schedule contains a duplicate day/slot row.');
            }
            $seen[$seenKey] = true;

            $start = self::validateTime((string) ($row['Start'] ?? ''));
            $minutes = self::integerInRange($row['Minutes'] ?? null, 'Minutes', 0, 1440);
            $enabled = self::toBoolean($row['Enabled'] ?? false);
            $border = self::toBoolean($row['Border'] ?? false);
            if ($enabled && $minutes === 0) {
                throw new InvalidArgumentException(sprintf('%s, Einsatz %d: an active entry needs a duration greater than zero.', self::DAYS[$day], $slot + 1));
            }

            $scheduleKey = self::SLOT_KEYS[$slot];
            $tuple = $source[$scheduleKey][$day];
            $tuple[0] = $start;
            $tuple[1] = $enabled ? $minutes : 0;
            $tuple[2] = $border ? 1 : 0;
            $source[$scheduleKey][$day] = $tuple;
        }

        if (count($seen) !== $expectedRows) {
            throw new InvalidArgumentException('The schedule must contain exactly one row for each supported day/slot.');
        }

        return $source;
    }

    public static function describe(array $schedule): string
    {
        $byDay = [];
        foreach (self::toRows($schedule) as $row) {
            $day = $row['Day'];
            if (!isset($byDay[$day])) {
                $byDay[$day] = [];
            }
            if (!$row['Enabled']) {
                continue;
            }

            $end = self::addMinutes($row['Start'], $row['Minutes']);
            $description = sprintf('%s–%s (%d min)', $row['Start'], $end, $row['Minutes']);
            if ($row['Border']) {
                $description .= ', Kantenschnitt';
            }
            $byDay[$day][] = $description;
        }

        $parts = [];
        foreach (self::DISPLAY_DAY_ORDER as $day) {
            $entries = $byDay[$day] ?? [];
            $parts[] = self::DAYS[$day] . ': ' . ($entries === [] ? 'kein Einsatz' : implode('; ', $entries));
        }

        return implode("\n", $parts);
    }

    public static function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Compare the schedule fields exposed for editing in the Symcon event. */
    public static function matchesEditableSlots(array $expected, array $reported): bool
    {
        if (!self::hasValidSlots($expected, 'd') || !self::hasValidSlots($reported, 'd')) {
            return false;
        }

        if (array_key_exists('p', $expected) || array_key_exists('p', $reported)) {
            if (!array_key_exists('p', $expected) || !array_key_exists('p', $reported)
                || self::timeExtensionFromProtocol($expected['p']) === null
                || self::timeExtensionFromProtocol($reported['p']) === null
                || self::timeExtensionFromProtocol($expected['p']) !== self::timeExtensionFromProtocol($reported['p'])) {
                return false;
            }
        }

        foreach (['d', 'dd'] as $key) {
            $expectedHasSlots = self::hasValidSlots($expected, $key);
            $reportedHasSlots = self::hasValidSlots($reported, $key);
            if ($expectedHasSlots !== $reportedHasSlots) {
                return false;
            }
            if (!$expectedHasSlots) {
                continue;
            }

            for ($day = 0; $day < 7; $day++) {
                $expectedSlot = $expected[$key][$day];
                $reportedSlot = $reported[$key][$day];
                if ((string) $expectedSlot[0] !== (string) $reportedSlot[0]
                    || (int) $expectedSlot[1] !== (int) $reportedSlot[1]
                    || (int) $expectedSlot[2] !== (int) $reportedSlot[2]) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Convert the supported daily mowing slots to Symcon weekly-event points. */
    public static function toEventPoints(array $schedule): array
    {
        $pointsByDay = [];
        foreach (self::toRows($schedule) as $row) {
            $eventDay = ($row['Day'] + 6) % 7; // Worx Sunday-first to Symcon Monday-first
            if (!isset($pointsByDay[$eventDay])) $pointsByDay[$eventDay] = [];
            if (!$row['Enabled']) continue;
            $start = self::timeToMinutes($row['Start']);
            $end = $start + $row['Minutes'];
            if ($end >= 1440) throw new InvalidArgumentException('Mähfenster über Mitternacht kann der Symcon-Wochenplan nicht verlustfrei darstellen.');
            $action = 1 + ($row['Slot'] * 2) + ($row['Border'] ? 1 : 0);
            if (isset($pointsByDay[$eventDay][$start]) || isset($pointsByDay[$eventDay][$end])) throw new InvalidArgumentException('Doppelte Schaltzeit im Wochenplan.');
            $pointsByDay[$eventDay][$start] = $action;
            $pointsByDay[$eventDay][$end] = 0;
        }
        ksort($pointsByDay, SORT_NUMERIC);
        foreach ($pointsByDay as &$dayPoints) {
            if (!isset($dayPoints[0])) $dayPoints[0] = 0;
            ksort($dayPoints, SORT_NUMERIC);
            $normalized = [];
            foreach ($dayPoints as $minute => $action) $normalized[] = ['Minute' => $minute, 'Action' => $action];
            $dayPoints = $normalized;
        }
        unset($dayPoints);
        return $pointsByDay;
    }

    /** Read Symcon's event representation back into the Worx schedule rows. */
    public static function rowsFromEvent(array $event, array $source): array
    {
        if (($event['EventType'] ?? null) !== 2 || !isset($event['ScheduleGroups']) || !is_array($event['ScheduleGroups'])) throw new InvalidArgumentException('Das Wochenplan-Ereignis ist nicht lesbar.');
        $slotCount = self::hasSecondarySchedule($source) ? 2 : 1;
        $days = array_fill(0, 7, []);
        foreach ($event['ScheduleGroups'] as $group) {
            if (!isset($group['Days'], $group['Points']) || !is_array($group['Points'])) throw new InvalidArgumentException('Eine Ereignisgruppe ist unvollständig.');
            for ($day = 0; $day < 7; $day++) {
                if (((int) $group['Days'] & (1 << $day)) === 0) continue;
                foreach ($group['Points'] as $point) {
                    if (!isset($point['Start']['Hour'], $point['Start']['Minute'], $point['ActionID'])) throw new InvalidArgumentException('Ein Schaltpunkt ist unvollständig.');
                    $minute = (int) $point['Start']['Hour'] * 60 + (int) $point['Start']['Minute'];
                    $action = (int) $point['ActionID'];
                    if ($action < 0 || $action > 4 || ($action > 0 && intdiv($action - 1, 2) >= $slotCount)) throw new InvalidArgumentException('Dieser Wochenplan-Zustand wird vom Mäherformat nicht unterstützt.');
                    if (isset($days[$day][$minute])) throw new InvalidArgumentException('Doppelte Schaltzeit im Wochenplan.');
                    $days[$day][$minute] = $action;
                }
            }
        }
        $active = [];
        foreach (self::DISPLAY_DAY_ORDER as $wireDay) {
            $eventDay = ($wireDay + 6) % 7;
            if ($days[$eventDay] === []) throw new InvalidArgumentException('Der Wochenplan enthält nicht alle sieben Tage.');
            ksort($days[$eventDay], SORT_NUMERIC);
            $previous = 0;
            foreach ($days[$eventDay] as $minute => $action) {
                if ($action === $previous) continue;
                if ($action > 0) {
                    $slot = intdiv($action - 1, 2);
                    $active[$slot . ':' . $wireDay] = ['Day' => $wireDay, 'Slot' => $slot, 'Enabled' => true, 'Start' => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60), 'Minutes' => 0, 'Border' => (($action - 1) % 2) === 1];
                } elseif ($previous > 0) {
                    $slot = intdiv($previous - 1, 2);
                    $key = $slot . ':' . $wireDay;
                    if (!isset($active[$key]) || $active[$key]['Minutes'] !== 0) throw new InvalidArgumentException('Mehrere Einsätze desselben Typs pro Tag sind nicht unterstützt.');
                    $active[$key]['Minutes'] = $minute - self::timeToMinutes($active[$key]['Start']);
                    if ($active[$key]['Minutes'] < 1) throw new InvalidArgumentException('Ein Mähfenster hat keine positive Dauer.');
                    $previous = 0;
                    continue;
                }
                $previous = $action;
            }
            if ($previous > 0) throw new InvalidArgumentException('Mähfenster über Mitternacht sind nicht unterstützt.');
        }
        $rows = [];
        foreach (self::DISPLAY_DAY_ORDER as $day) for ($slot = 0; $slot < $slotCount; $slot++) {
            $key = $slot . ':' . $day;
            $rows[] = $active[$key] ?? ['Day' => $day, 'Slot' => $slot, 'Enabled' => false, 'Start' => '00:00', 'Minutes' => 0, 'Border' => false];
        }
        return $rows;
    }

    private static function removeSensitiveKeys(array $value): array
    {
        $result = [];
        $sensitiveKeys = [
            'serial', 'serialnumber', 'sn', 'uuid', 'mac', 'macaddress', 'userid',
            'token', 'accesstoken', 'refreshtoken', 'authorization', 'mqttendpoint',
            'mqtttopics', 'latitude', 'longitude', 'location', 'setuplocation', 'city',
        ];

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                continue;
            }
            if (is_array($item)) {
                $item = self::removeSensitiveKeys($item);
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private static function timeToMinutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
    private static function hasValidSlots(array $schedule, string $key): bool
    {
        if (!isset($schedule[$key]) || !is_array($schedule[$key]) || array_keys($schedule[$key]) !== range(0, 6)) {
            return false;
        }

        foreach ($schedule[$key] as $tuple) {
            if (!is_array($tuple) || !array_key_exists(0, $tuple) || !array_key_exists(1, $tuple) || !array_key_exists(2, $tuple)) {
                return false;
            }
            if (!is_string($tuple[0]) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $tuple[0]) !== 1) {
                return false;
            }
            if (!self::isIntegerLike($tuple[1]) || (int) $tuple[1] < 0 || (int) $tuple[1] > 1440) {
                return false;
            }
            if (!is_numeric($tuple[2])) {
                return false;
            }
        }

        return true;
    }

    private static function isIntegerLike($value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
    }

    private static function integerInRange($value, string $field, int $minimum, int $maximum): int
    {
        if (!self::isIntegerLike($value)) {
            throw new InvalidArgumentException($field . ' must be an integer.');
        }

        $integer = (int) $value;
        if ($integer < $minimum || $integer > $maximum) {
            throw new InvalidArgumentException(sprintf('%s must be between %d and %d.', $field, $minimum, $maximum));
        }

        return $integer;
    }

    private static function validateTime(string $time): string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw new InvalidArgumentException('Start must use the 24-hour format HH:MM.');
        }

        return $time;
    }

    private static function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [1, '1', 'true', 'on'], true)) {
            return true;
        }
        if (in_array($value, [0, '0', 'false', 'off'], true)) {
            return false;
        }

        throw new InvalidArgumentException('Boolean fields must be true/false or 1/0.');
    }

    private static function addMinutes(string $time, int $minutes): string
    {
        $total = ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2) + $minutes;
        $total %= 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }
}
