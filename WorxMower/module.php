<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/WorxScheduleCodec.php';

/**
 * Worx Landroid instance. Existing variables and actions retain their idents.
 * Control is a command input; State is the confirmed mower state.
 */
class WorxMower extends IPSModule
{
    private const IF_CLOUD = '{557B9D5F-D12D-4E44-87A7-05A5EC0F4F07}';
    private const MAX_RAIN_DELAY_MINUTES = 300;

    private const STATES = [
        0  => 'Bereit', 1 => 'In der Ladestation', 2 => 'Startsequenz', 3 => 'Verlässt Ladestation',
        4  => 'Folgt Begrenzung', 5 => 'Sucht Ladestation', 6 => 'Sucht Begrenzung', 7 => 'Mäht',
        8  => 'Angehoben', 9 => 'Eingeklemmt', 10 => 'Messer blockiert', 11 => 'Debug',
        12 => 'Fernsteuerung', 30 => 'Fährt zur Ladestation', 31 => 'Zonentraining',
        32 => 'Kantenschnitt', 33 => 'Sucht Zone', 34 => 'Pausiert',
    ];

    private const ERRORS = [
        0  => 'Kein Fehler', 1 => 'Eingeklemmt', 2 => 'Angehoben', 3 => 'Begrenzungsdraht fehlt',
        4  => 'Außerhalb der Begrenzung', 5 => 'Regen', 6 => 'Klappe schließen zum Mähen',
        7  => 'Klappe schließen zur Rückkehr', 8 => 'Messermotor blockiert', 9 => 'Radmotor blockiert',
        10 => 'Eingeklemmt (Zeitüberschreitung)', 11 => 'Umgedreht', 12 => 'Batterie leer',
        13 => 'Begrenzungsdraht vertauscht', 14 => 'Ladefehler', 15 => 'Ladestation nicht gefunden',
        16 => 'Mäher gesperrt', 17 => 'Batterietemperatur zu hoch/niedrig', 18 => 'Fehler Lagesensor',
        19 => 'Radmotor blockiert', 20 => 'Ladestation belegt', 21 => 'Kamerafehler',
        22 => 'Fehler Antriebssystem', 23 => 'Fehler Höhenverstellung', 24 => 'Fehler RFID',
    ];

    private const COMMANDS = [1 => 'Start', 2 => 'Pause', 3 => 'Heimfahrt', 4 => 'Kantenschnitt'];

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Serial', '');
        $this->ConnectParent('{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}');

        $this->RegisterAttributeString('ReportedSchedule', '');
        $this->RegisterAttributeString('PendingSchedule', '');
        $this->RegisterAttributeString('FailedSchedule', '');
        $this->RegisterAttributeString('FailedScheduleSerial', '');
        $this->RegisterAttributeString('PendingCommand', '');
        $this->RegisterAttributeString('PendingCommandSerial', '');
        $this->RegisterAttributeString('PendingScheduleSerial', '');
        $this->RegisterAttributeString('PendingSchedulePurpose', 'schedule');
        $this->RegisterAttributeString('ReportedRainDelay', '');
        $this->RegisterAttributeString('PendingRainDelay', '');
        $this->RegisterAttributeString('PendingRainDelaySerial', '');
        $this->RegisterAttributeString('PendingLock', '');
        $this->RegisterAttributeString('PendingLockSerial', '');
        $this->RegisterAttributeString('PendingAutoSchedule', '');
        $this->RegisterAttributeString('PendingAutoScheduleSerial', '');
        $this->RegisterAttributeString('ScheduleEventSnapshot', '');
        $this->RegisterAttributeBoolean('ScheduleEventSyncing', false);
        $this->RegisterAttributeInteger('ScheduleEventListener', 0);
        $this->RegisterAttributeBoolean('ScheduleWritesSuppressed', false);
        $this->RegisterTimer('CommandConfirmationTimeout', 0, 'WORXMOWER_CommandConfirmationTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ScheduleConfirmationTimeout', 0, 'WORXMOWER_ScheduleConfirmationTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ScheduleEditDebounce', 0, 'WORXMOWER_ScheduleEditDebounce($_IPS[\'TARGET\']);');
        $this->RegisterTimer('RainDelayConfirmationTimeout', 0, 'WORXMOWER_RainDelayConfirmationTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('LockConfirmationTimeout', 0, 'WORXMOWER_LockConfirmationTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('AutoScheduleConfirmationTimeout', 0, 'WORXMOWER_AutoScheduleConfirmationTimeout($_IPS[\'TARGET\']);');

        $this->registerVariables();
        $this->EnableAction('Control');
        $this->EnableAction('TimeExtensionSet');
        $this->EnableAction('RainDelaySet');
        $this->EnableAction('LockCommand');
        $this->EnableAction('AutoScheduleSet');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Re-register presentations so an update replaces legacy profiles on existing variables.
        $this->registerVariables();
        $this->clearPendingForDifferentMower();
        foreach (['Schedule', 'SchedulePreview'] as $obsoleteIdent) {
            $obsoleteID = $this->GetIDForIdent($obsoleteIdent);
            if ($obsoleteID !== false && $obsoleteID !== 0 && IPS_VariableExists($obsoleteID)) {
                IPS_SetHidden($obsoleteID, true);
            }
        }
        if ($this->ReadPropertyString('Serial') === '') {
            $this->SetTimerInterval('CommandConfirmationTimeout', 0);
            $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
            $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 0);
            $this->SetStatus(104);
            return;
        }
        $this->SetStatus(102);
        $this->SetValueSafe('Control', 0);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->WriteAttributeBoolean('ScheduleWritesSuppressed', true);
            try {
                $this->Update();
            } finally {
                $this->WriteAttributeBoolean('ScheduleWritesSuppressed', false);
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'Control') {
            $command = (int) $Value;
            if (!isset(self::COMMANDS[$command])) {
                throw new InvalidArgumentException('Control expects Start (1), Pause (2) or Heimfahrt (3).');
            }
            $this->Command($command);
            $this->SetValueSafe('Control', 0);
            return;
        }

        if ($Ident === 'AutoScheduleSet') {
            if (!is_bool($Value)) {
                throw new InvalidArgumentException('AutoScheduleSet erwartet true oder false.');
            }
            $this->SetValueSafe('AutoScheduleSet', $Value);
            $this->SetAutoSchedule($Value);
            return;
        }
        if ($Ident === 'LockCommand') {
            if (!is_bool($Value)) {
                throw new InvalidArgumentException('LockCommand erwartet true für sperren oder false für entsperren.');
            }
            $this->SetValueSafe('LockCommand', $Value);
            $this->SetLock($Value);
            return;
        }
        if ($Ident === 'RainDelaySet') {
            $minutes = $this->validatedInteger($Value, 0, self::MAX_RAIN_DELAY_MINUTES, 'Regenverzögerung');
            $this->SetValueSafe('RainDelaySet', $minutes);
            $this->SetRainDelay($minutes);
            return;
        }
        if ($Ident === 'TimeExtensionSet') {
            $percent = $this->validatedInteger($Value, -100, 100, 'Tägliche Arbeitszeit');
            $this->SetValueSafe('TimeExtensionSet', $percent);
            $this->SetTimeExtension($percent);
            return;
        }
        throw new InvalidArgumentException('Invalid Ident: ' . $Ident);
    }

    /** true means MQTT accepted the publish; device confirmation is separate. */
    public function Command(int $Command): bool
    {
        if (!isset(self::COMMANDS[$Command])) {
            return false;
        }
        if ($Command === 4) {
            $device = $this->getDevice();
            if ($device === null || (int) ($device['protocol'] ?? -1) !== 0
                || !in_array('follow_border', $device['capabilities'] ?? [], true)) {
                $this->SetValueSafe('CommandStatus', 'Nicht gesendet: Manueller Kantenschnitt wird für dieses Gerät nicht unterstützt.');
                return false;
            }
            if (empty($device['online'])) {
                $this->SetValueSafe('CommandStatus', 'Nicht gesendet: Der Mäher ist offline.');
                return false;
            }
        }
        if ($this->ReadAttributeString('PendingCommand') !== '') {
            $this->SetValueSafe('CommandStatus', 'Ein anderer Befehl wartet noch auf Rückmeldung.');
            return false;
        }
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $this->SetValueSafe('LastCommand', self::COMMANDS[$Command]);
        if ($parent === 0) {
            $this->SetValueSafe('CommandStatus', 'Nicht gesendet: keine Worx-Cloud-Verbindung.');
            return false;
        }
        $payload = json_encode(['cmd' => $Command]);
        $serial = $this->ReadPropertyString('Serial');
        $this->WriteAttributeString('PendingCommand', (string) $Command);
        $this->WriteAttributeString('PendingCommandSerial', $serial);
        $this->SetValueSafe('CommandStatus', 'MQTT-Publish gesendet; Bestätigung des Mähers steht aus.');
        $this->SetTimerInterval('CommandConfirmationTimeout', 90000);
        if (!WORX_SendCommand($parent, $serial, (string) $payload)) {
            if ($this->ReadAttributeString('PendingCommand') === (string) $Command
                && $this->ReadAttributeString('PendingCommandSerial') === $serial) {
                $this->WriteAttributeString('PendingCommand', '');
                $this->WriteAttributeString('PendingCommandSerial', '');
                $this->SetTimerInterval('CommandConfirmationTimeout', 0);
                $this->SetValueSafe('CommandStatus', 'Nicht gesendet: MQTT-Verbindung nicht bereit.');
            }
            return false;
        }
        return true;
    }

    /** Update the cloud automatic-schedule switch and wait for its reported state. */
    public function SetAutoSchedule(bool $enabled): bool
    {
        if ($this->ReadAttributeString('PendingAutoSchedule') !== '' || $this->ReadAttributeString('PendingLock') !== ''
            || $this->ReadAttributeString('PendingRainDelay') !== '' || $this->ReadAttributeString('PendingSchedule') !== '') {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Eine andere Geräteeinstellung wartet noch auf Rückmeldung.');
            return false;
        }
        $device = $this->getDevice();
        if ($device === null || !array_key_exists('auto_schedule', $device) || !is_bool($device['auto_schedule'])) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Automatischer Zeitplan ist für dieses Gerät nicht belegt.');
            return false;
        }
        if (empty($device['online'])) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Der Mäher ist offline.');
            return false;
        }
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Worx-Cloud-Verbindung fehlt.');
            return false;
        }
        $reportedID = $this->GetIDForIdent('AutoSchedule');
        $previous = $reportedID === false || $reportedID === 0 ? (bool) $device['auto_schedule'] : (bool) GetValue($reportedID);
        $this->WriteAttributeString('PendingAutoSchedule', json_encode(['desired' => $enabled, 'previous' => $previous]));
        $this->WriteAttributeString('PendingAutoScheduleSerial', $this->ReadPropertyString('Serial'));
        $this->SetValueSafe('SettingStatus', 'Automatischer Zeitplan gesendet; Rückmeldung aus der Worx-Cloud steht aus.');
        $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 120000);
        $response = $this->SendDataToParent(json_encode([
            'DataID'  => self::IF_CLOUD,
            'Command' => 'SetAutoSchedule',
            'Serial'  => $this->ReadPropertyString('Serial'),
            'Enabled' => $enabled,
        ]));
        if (!filter_var(json_decode((string) $response, true), FILTER_VALIDATE_BOOLEAN)) {
            $this->WriteAttributeString('PendingAutoSchedule', '');
            $this->WriteAttributeString('PendingAutoScheduleSerial', '');
            $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Worx-Cloud hat die Änderung abgelehnt oder ist nicht erreichbar.');
            return false;
        }
        return true;
    }

    public function AutoScheduleConfirmationTimeout(): void
    {
        if ($this->ReadAttributeString('PendingAutoSchedule') === '') {
            return;
        }
        $this->WriteAttributeString('PendingAutoSchedule', '');
        $this->WriteAttributeString('PendingAutoScheduleSerial', '');
        $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 0);
        $this->SetValueSafe('SettingStatus', 'Keine Bestätigung des automatischen Zeitplans innerhalb von 120 Sekunden.');
        $this->Update();
    }

    /** Send a lock state and keep the reported mower state separate. */
    public function SetLock(bool $locked): bool
    {
        if ($this->ReadAttributeString('PendingAutoSchedule') !== '' || $this->ReadAttributeString('PendingLock') !== '' || $this->ReadAttributeString('PendingRainDelay') !== '' || $this->ReadAttributeString('PendingSchedule') !== '') {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Eine andere Geräteeinstellung wartet noch auf Rückmeldung.');
            return false;
        }
        $device = $this->getDevice();
        if ($device === null || (int) ($device['protocol'] ?? -1) !== 0
            || !in_array('lock', $device['capabilities'] ?? [], true)) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Sperren wird für dieses Gerät nicht unterstützt.');
            return false;
        }
        if (empty($device['online'])) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Der Mäher ist offline.');
            return false;
        }
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Worx-Cloud-Verbindung fehlt.');
            return false;
        }
        $currentID = $this->GetIDForIdent('Locked');
        $current = $currentID === false || $currentID === 0 ? false : (bool) GetValue($currentID);
        $serial = $this->ReadPropertyString('Serial');
        $this->WriteAttributeString('PendingLock', json_encode(['desired' => $locked, 'previous' => $current]));
        $this->WriteAttributeString('PendingLockSerial', $serial);
        $this->SetValueSafe('SettingStatus', $locked
            ? 'Sperrbefehl gesendet; Rückmeldung des Mähers steht aus.'
            : 'Entsperrbefehl gesendet; Rückmeldung des Mähers steht aus.');
        $this->SetTimerInterval('LockConfirmationTimeout', 120000);
        $response = $this->SendDataToParent(json_encode([
            'DataID'  => self::IF_CLOUD,
            'Command' => 'SetLock',
            'Serial'  => $serial,
            'Locked'  => $locked,
        ]));
        if (!filter_var(json_decode((string) $response, true), FILTER_VALIDATE_BOOLEAN)) {
            if ($this->ReadAttributeString('PendingLock') !== '') {
                $this->WriteAttributeString('PendingLock', '');
                $this->WriteAttributeString('PendingLockSerial', '');
                $this->SetTimerInterval('LockConfirmationTimeout', 0);
                $this->SetValueSafe('SettingStatus', 'Nicht gesendet: MQTT-Verbindung nicht bereit oder Befehl abgelehnt.');
            }
            return false;
        }
        return true;
    }
    /** Send the requested rain delay and keep the reported value as confirmed state. */
    public function SetRainDelay(int $minutes): bool
    {
        if ($minutes < 0 || $minutes > self::MAX_RAIN_DELAY_MINUTES) {
            throw new InvalidArgumentException('Regenverzögerung muss zwischen 0 und ' . self::MAX_RAIN_DELAY_MINUTES . ' Minuten liegen.');
        }
        $device = $this->getDevice();
        if ($device === null || (int) ($device['protocol'] ?? -1) !== 0
            || !in_array('rain_delay', $device['capabilities'] ?? [], true)) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Regenverzögerung wird für dieses Gerät nicht unterstützt.');
            return false;
        }
        if (empty($device['online'])) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Der Mäher ist offline.');
            return false;
        }
        if ($this->ReadAttributeString('PendingSchedule') !== '' || $this->ReadAttributeString('PendingRainDelay') !== '' || $this->ReadAttributeString('PendingLock') !== '' || $this->ReadAttributeString('PendingAutoSchedule') !== '') {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Eine andere Geräteeinstellung wartet noch auf Rückmeldung.');
            return false;
        }
        $current = $this->GetValueForIdent('RainDelay');
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0) {
            $this->SetValueSafe('SettingStatus', 'Nicht gesendet: Worx-Cloud-Verbindung fehlt.');
            return false;
        }
        $serial = $this->ReadPropertyString('Serial');
        $this->WriteAttributeString('PendingRainDelay', json_encode(['desired' => $minutes, 'previous' => $current]));
        $this->WriteAttributeString('PendingRainDelaySerial', $serial);
        $this->SetValueSafe('SettingStatus', 'Regenverzögerung gesendet; Rückmeldung des Mähers steht aus.');
        $this->SetTimerInterval('RainDelayConfirmationTimeout', 120000);
        $response = $this->SendDataToParent(json_encode([
            'DataID'  => self::IF_CLOUD,
            'Command' => 'SetRainDelay',
            'Serial'  => $serial,
            'Minutes' => $minutes,
        ]));
        if (!filter_var(json_decode((string) $response, true), FILTER_VALIDATE_BOOLEAN)) {
            if ($this->ReadAttributeString('PendingRainDelay') !== '') {
                $this->WriteAttributeString('PendingRainDelay', '');
                $this->WriteAttributeString('PendingRainDelaySerial', '');
                $this->SetTimerInterval('RainDelayConfirmationTimeout', 0);
                $this->SetValueSafe('SettingStatus', 'Nicht gesendet: MQTT-Verbindung nicht bereit oder Befehl abgelehnt.');
            }
            return false;
        }
        return true;
    }

    /** Send a protocol-0 schedule patch with the requested time-extension field. */
    public function SetTimeExtension(int $percent): bool
    {
        if ($percent < -100 || $percent > 100) {
            throw new InvalidArgumentException('Tägliche Arbeitszeit muss zwischen -100 und 100 Prozent liegen.');
        }
        if ($this->ReadAttributeString('PendingSchedule') !== '' || $this->ReadAttributeString('PendingRainDelay') !== '' || $this->ReadAttributeString('PendingLock') !== '' || $this->ReadAttributeString('PendingAutoSchedule') !== '') {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Eine andere Geräteeinstellung wartet noch auf Rückmeldung.');
            return false;
        }
        $device = $this->getDevice();
        $schedule = $device === null ? null : WorxScheduleCodec::scheduleFromDevice($device);
        if ($device === null || (int) ($device['protocol'] ?? -1) !== 0
            || !in_array('unrestricted_mowing_time', $device['capabilities'] ?? [], true) || $schedule === null) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Einstellung der täglichen Arbeitszeit wird für dieses Gerät nicht unterstützt.');
            return false;
        }
        if (empty($device['online'])) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Der Mäher ist offline.');
            return false;
        }
        $schedule['p'] = WorxScheduleCodec::timeExtensionToProtocol($percent);
        $desiredJson = WorxScheduleCodec::canonicalJson($schedule);
        $serial = $this->ReadPropertyString('Serial');
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Tägliche Arbeitszeitänderung nicht gesendet: Worx-Cloud-Verbindung fehlt.');
            return false;
        }
        $this->WriteAttributeString('PendingSchedule', $desiredJson);
        $this->WriteAttributeString('PendingScheduleSerial', $serial);
        $this->WriteAttributeString('PendingSchedulePurpose', 'time_extension');
        $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($this->scheduleEventID()));
        $this->SetValueSafe('ScheduleSyncStatus', 'Tägliche Arbeitszeitänderung gesendet; Bestätigung durch Zurücklesen des Mähers steht aus.');
        $this->SetTimerInterval('ScheduleConfirmationTimeout', 120000);
        if (!WORX_SetSchedule($parent, $serial, $desiredJson)) {
            if ($this->ReadAttributeString('PendingSchedule') === $desiredJson
                && $this->ReadAttributeString('PendingScheduleSerial') === $serial) {
                $this->WriteAttributeString('PendingSchedule', '');
                $this->WriteAttributeString('PendingScheduleSerial', '');
                $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
                $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
                $this->SetValueSafe('ScheduleSyncStatus', 'Tägliche Arbeitszeitänderung nicht gesendet: MQTT-Verbindung nicht bereit oder Befehl abgelehnt.');
            }
            return false;
        }
        return true;
    }
    public function Start(): bool
    {
        return $this->Command(1);
    }
    public function Pause(): bool
    {
        return $this->Command(2);
    }
    public function Home(): bool
    {
        return $this->Command(3);
    }

    /** Start a manual border-following cut, when the mower reports that capability. */
    public function FollowBorder(): bool
    {
        return $this->Command(4);
    }

    /** Refresh the Worx inventory and receive the latest device state. */
    public function Update(): bool
    {
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Aktualisierung nicht möglich: Worx-Cloud-Verbindung fehlt.');
            return false;
        }

        // Poll distributes the newly fetched device record to this instance.
        $updated = (bool) WORX_Poll($parent);
        if (!$updated) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Aktualisierung fehlgeschlagen; der zuletzt empfangene Zeitplan bleibt angezeigt.');
        }

        return $updated;
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data) || ($data['Serial'] ?? '') !== $this->ReadPropertyString('Serial')) {
            return '';
        }
        if (isset($data['Device']) && is_array($data['Device'])) {
            $this->applyDevice($data['Device']);
        }
        return '';
    }

    /** Send the current native Symcon weekly event to Worx. */
    public function SendSchedule(): bool
    {
        $eventID = $this->scheduleEventID();
        $sourceJson = $this->ReadAttributeString('ReportedSchedule');
        $source = $sourceJson === '' ? null : json_decode($sourceJson, true);
        if ($eventID === 0 || !is_array($source)) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Mähzeitplan-Ereignis oder bestätigter Worx-Plan fehlt.');
            return false;
        }
        if ($this->ReadAttributeString('PendingSchedule') !== '') {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Eine Zeitplanübertragung wartet noch auf Rückmeldung.');
            return false;
        }
        if ($this->ReadAttributeString('PendingRainDelay') !== '' || $this->ReadAttributeString('PendingLock') !== '' || $this->ReadAttributeString('PendingAutoSchedule') !== '') {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Eine andere Geräteeinstellung wartet noch auf Rückmeldung.');
            return false;
        }
        try {
            $event = IPS_GetEvent($eventID);
            $rows = WorxScheduleCodec::rowsFromEvent($event, $source);
            $desired = WorxScheduleCodec::mergeRows($source, $rows);
        } catch (Throwable $exception) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Ereignisänderung nicht übertragen: ' . $exception->getMessage());
            return false;
        }
        $desiredJson = WorxScheduleCodec::canonicalJson($desired);
        if ($desiredJson === WorxScheduleCodec::canonicalJson($source)) {
            $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($eventID));
            return true;
        }
        if ($this->ReadAttributeString('FailedSchedule') === $desiredJson) {
            $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($eventID));
            return false;
        }
        $this->WriteAttributeString('FailedSchedule', '');
        $serial = $this->ReadPropertyString('Serial');
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht übertragen: Worx-Cloud-Verbindung fehlt.');
            return false;
        }
        $this->WriteAttributeString('PendingSchedule', $desiredJson);
        $this->WriteAttributeString('PendingScheduleSerial', $serial);
        $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
        $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($eventID));
        $this->SetValueSafe('ScheduleSyncStatus', 'Zeitplan gesendet; Bestätigung durch Zurücklesen des Mähers steht aus.');
        $this->SetTimerInterval('ScheduleConfirmationTimeout', 120000);
        if (!WORX_SetSchedule($parent, $serial, $desiredJson)) {
            if ($this->ReadAttributeString('PendingSchedule') === $desiredJson
                && $this->ReadAttributeString('PendingScheduleSerial') === $serial) {
                $this->WriteAttributeString('PendingSchedule', '');
                $this->WriteAttributeString('PendingScheduleSerial', '');
                $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
                $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
                $this->SetValueSafe('ScheduleSyncStatus', 'Nicht übertragen: Worx-Cloud/MQTT ist nicht bereit oder hat den Befehl abgelehnt.');
            }
            return false;
        }
        return true;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $eventID = $this->scheduleEventID();
        $scheduleMessages = [10803, 10818, 10819, 10820, 10821, 10822, 10823];
        if ((int) $SenderID !== $eventID || !in_array((int) $Message, $scheduleMessages, true) || $this->ReadAttributeBoolean('ScheduleEventSyncing') || $this->ReadAttributeBoolean('ScheduleWritesSuppressed')) {
            return;
        }
        // Symcon emits several messages while rebuilding a week plan. Wait until
        // the UI has completed the edit so only the final full schedule is sent.
        $this->SetTimerInterval('ScheduleEditDebounce', 750);
    }

    public function ScheduleEditDebounce(): void
    {
        $this->SetTimerInterval('ScheduleEditDebounce', 0);
        $eventID = $this->scheduleEventID();
        if ($eventID === 0 || $this->ReadAttributeBoolean('ScheduleEventSyncing') || $this->ReadAttributeBoolean('ScheduleWritesSuppressed')) {
            return;
        }
        if ($this->scheduleEventFingerprint($eventID) === $this->ReadAttributeString('ScheduleEventSnapshot')) {
            return;
        }
        $this->SendSchedule();
    }
    public function CommandConfirmationTimeout(): void
    {
        if ($this->ReadAttributeString('PendingCommand') === '') {
            return;
        }
        $this->WriteAttributeString('PendingCommand', '');
        $this->WriteAttributeString('PendingCommandSerial', '');
        $this->SetTimerInterval('CommandConfirmationTimeout', 0);
        $this->SetValueSafe('CommandStatus', 'Keine Bestätigung des Mähers innerhalb von 90 Sekunden.');
    }

    public function RainDelayConfirmationTimeout(): void
    {
        if ($this->ReadAttributeString('PendingRainDelay') === '') {
            return;
        }
        $this->WriteAttributeString('PendingRainDelay', '');
        $this->WriteAttributeString('PendingRainDelaySerial', '');
        $this->SetTimerInterval('RainDelayConfirmationTimeout', 0);
        $this->SetValueSafe('SettingStatus', 'Keine Mäher-Bestätigung der Regenverzögerung innerhalb von 120 Sekunden.');
        $this->Update();
    }
    public function LockConfirmationTimeout(): void
    {
        if ($this->ReadAttributeString('PendingLock') === '') {
            return;
        }
        $this->WriteAttributeString('PendingLock', '');
        $this->WriteAttributeString('PendingLockSerial', '');
        $this->SetTimerInterval('LockConfirmationTimeout', 0);
        $this->SetValueSafe('SettingStatus', 'Keine Mäher-Bestätigung der Sperrung innerhalb von 120 Sekunden.');
        $this->Update();
    }
    public function ScheduleConfirmationTimeout(): void
    {
        if ($this->ReadAttributeString('PendingSchedule') === '') {
            return;
        }
        $this->WriteAttributeString('FailedSchedule', $this->ReadAttributeString('PendingSchedule'));
        $this->WriteAttributeString('FailedScheduleSerial', $this->ReadAttributeString('PendingScheduleSerial'));
        $purpose = $this->ReadAttributeString('PendingSchedulePurpose');
        $this->WriteAttributeString('PendingSchedule', '');
        $this->WriteAttributeString('PendingScheduleSerial', '');
        $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
        $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
        $timeoutStatus = $purpose === 'time_extension'
            ? 'Keine Bestätigung der täglichen Arbeitszeitänderung innerhalb von 120 Sekunden.'
            : 'Keine passende Mäher-Rückmeldung innerhalb von 120 Sekunden; prüfe den zuletzt empfangenen Stand.';
        $this->SetValueSafe('ScheduleSyncStatus', $timeoutStatus);
        $this->Update();
    }

    public function GetConfigurationForm()
    {
        $device = $this->getDevice();
        $schedule = $device === null ? null : WorxScheduleCodec::scheduleFromDevice($device);
        $elements = [
            ['type' => 'ValidationTextBox', 'name' => 'Serial', 'caption' => 'Seriennummer'],
            ['type' => 'Label', 'caption' => 'Der redigierte Gerätebeleg steht in der Mower-Variable „Gerätenachweis (redigiert)“.'],
        ];
        $configuration = $device === null ? [] : WorxScheduleCodec::deviceConfiguration($device);
        $reportedSchedule = $configuration['sc'] ?? [];
        $scheduleMode = is_array($reportedSchedule) ? ($reportedSchedule['m'] ?? null) : null;
        if (is_numeric($scheduleMode)) {
            $elements[] = ['type' => 'Label', 'caption' => 'Zeitplanmodus (m, Rohwert): ' . (int) $scheduleMode . ' (Bedeutung noch nicht belegt)'];
        }
        $timeExtension = is_array($reportedSchedule) ? WorxScheduleCodec::timeExtensionFromProtocol($reportedSchedule['p'] ?? null) : null;
        if ($timeExtension !== null) {
            $extensionText = rtrim(rtrim(sprintf('%.1f', (float) $timeExtension), '0'), '.');
            $elements[] = ['type' => 'Label', 'caption' => 'Vom Mäher gemeldete tägliche Arbeitszeitänderung: ' . str_replace('.', ',', $extensionText) . ' %'];
        }
        $actions = [
            ['type' => 'RowLayout', 'items' => [
                ['type' => 'Button', 'caption' => 'Start', 'onClick' => 'WORXMOWER_Start($id);'],
                ['type' => 'Button', 'caption' => 'Pause', 'onClick' => 'WORXMOWER_Pause($id);'],
                ['type' => 'Button', 'caption' => 'Ladestation', 'onClick' => 'WORXMOWER_Home($id);'],
            ]],
            ['type' => 'Button', 'caption' => 'Jetzt aktualisieren', 'onClick' => 'WORXMOWER_Update($id);'],
        ];
        if ($device !== null && (int) ($device['protocol'] ?? -1) === 0
            && in_array('follow_border', $device['capabilities'] ?? [], true)) {
            $actions[] = ['type' => 'Button', 'caption' => 'Kantenschnitt starten', 'onClick' => 'WORXMOWER_FollowBorder($id);'];
        }

        if ($schedule === null) {
            $elements[] = ['type' => 'Label', 'caption' => 'Kein unterstützter Zeitplan empfangen. Der Editor benötigt Protokoll 0 und sieben empfangene Tagesfelder.'];
        } else {
            $elements[] = ['type' => 'Label', 'caption' => 'Der bestätigte Mäherplan wird als natives, deaktiviertes Symcon-Wochenplan-Ereignis „Mähzeitplan“ angezeigt. Änderungen am Ereignis werden an Worx übertragen; der zurückgemeldete Mäherplan bestätigt die Übertragung.'];
            $elements[] = ['type' => 'Label', 'caption' => 'Die Worx-App zeigt außerdem „Ganzer Tag“. Die Cloud-Zuordnung dieses Schalters ist noch offen.'];
        }

        $elements[] = ['type' => 'Label', 'caption' => 'Befehlsstatus und bestätigter Mäherzustand sind getrennt. MQTT-Publish ist keine Gerätebestätigung.'];
        return json_encode([
            'elements' => $elements,
            'actions'  => $actions,
            'status'   => [
                ['code' => 102, 'icon' => 'active', 'caption' => 'Aktiv'],
                ['code' => 104, 'icon' => 'inactive', 'caption' => 'Seriennummer fehlt'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validatedInteger($value, int $minimum, int $maximum, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
            throw new InvalidArgumentException($label . ' muss eine ganze Zahl sein.');
        }
        $integer = (int) $value;
        if ($integer < $minimum || $integer > $maximum) {
            throw new InvalidArgumentException(sprintf('%s muss zwischen %d und %d liegen.', $label, $minimum, $maximum));
        }
        return $integer;
    }

    private function GetValueForIdent(string $ident): int
    {
        $id = $this->GetIDForIdent($ident);
        return $id === false || $id === 0 ? 0 : (int) GetValue($id);
    }
    private function scheduleEventID(): int
    {
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if ((IPS_GetObject($childID)['ObjectIdent'] ?? '') === 'WorxWeeklySchedule') return (int) $childID;
        }
        return 0;
    }

    private function scheduleEventFingerprint(int $eventID): string
    {
        if ($eventID === 0 || !IPS_EventExists($eventID)) return '';
        $event = IPS_GetEvent($eventID);
        return WorxScheduleCodec::canonicalJson(['Type' => $event['EventType'] ?? null, 'Actions' => $event['ScheduleActions'] ?? [], 'Groups' => $event['ScheduleGroups'] ?? []]);
    }

    private function scheduleEventMatches(int $eventID, array $schedule): bool
    {
        if ($eventID === 0 || !IPS_EventExists($eventID)) return false;
        try {
            $expected = WorxScheduleCodec::toRows($schedule);
            $actual = WorxScheduleCodec::rowsFromEvent(IPS_GetEvent($eventID), $schedule);
            $normalize = static function (array $rows): array
            {
                $normalized = [];
                foreach ($rows as $row) {
                    $key = (int) $row['Day'] . ':' . (int) $row['Slot'];
                    $normalized[$key] = [
                        'Enabled' => (bool) $row['Enabled'],
                        'Start'   => (string) $row['Start'],
                        'Minutes' => (int) $row['Minutes'],
                        'Border'  => (bool) $row['Border'],
                    ];
                }
                ksort($normalized);
                return $normalized;
            };
            return $normalize($expected) === $normalize($actual);
        } catch (Throwable $exception) {
            return false;
        }
    }

    /** Keep the single native weekly event aligned with the confirmed mower plan. */
    private function syncScheduleEvent(array $schedule, bool $force = false): void
    {
        $eventID = $this->scheduleEventID();
        if ($eventID !== 0 && IPS_GetEvent($eventID)['EventType'] !== 2) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Das Kindobjekt WorxWeeklySchedule ist kein Wochenplan-Ereignis.');
            return;
        }
        if ($eventID === 0) {
            $eventID = IPS_CreateEvent(2);
            IPS_SetParent($eventID, $this->InstanceID);
            IPS_SetIdent($eventID, 'WorxWeeklySchedule');
            IPS_SetName($eventID, 'Mähzeitplan');
        }
        $registeredEventID = $this->ReadAttributeInteger('ScheduleEventListener');
        if ($registeredEventID !== $eventID) {
            if ($registeredEventID > 0) {
                foreach ([10803, 10818, 10819, 10820, 10821, 10822, 10823] as $message) {
                    $this->UnregisterMessage($registeredEventID, $message);
                }
            }
            $this->WriteAttributeInteger('ScheduleEventListener', $eventID);
        }
        foreach ([10803, 10818, 10819, 10820, 10821, 10822, 10823] as $message) {
            $this->RegisterMessage($eventID, $message);
        }
        $baseline = $this->ReadAttributeString('ScheduleEventSnapshot');
        if (!$force && $baseline !== '' && $this->scheduleEventFingerprint($eventID) !== $baseline) {
            return;
        }
        try {
            $pointsByDay = WorxScheduleCodec::toEventPoints($schedule);
        } catch (InvalidArgumentException $exception) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Mäherplan im Wochenplan nicht darstellbar: ' . $exception->getMessage());
            return;
        }
        $noop = '// Das Wochenplan-Ereignis dient als Zeitplaneditor; es startet keine Mähaktion.';
        $actions = [0 => [$this->Translate('Kein Mähfenster'), 0xB0B0B0], 1 => [$this->Translate('Mähen'), 0x66AA33], 2 => [$this->Translate('Mähen mit Kantenschnitt'), 0xE87922], 3 => [$this->Translate('Einsatz 2'), 0x6688CC], 4 => [$this->Translate('Einsatz 2 mit Kantenschnitt'), 0x9966CC]];
        $this->WriteAttributeBoolean('ScheduleEventSyncing', true);
        try {
            foreach ($actions as $id => [$name, $color]) {
                IPS_SetEventScheduleAction($eventID, $id, $name, $color, $noop);
            }
            // Remove all old groups first. A user may have regrouped weekdays
            // or created groups with IDs outside the canonical 0..6 range.
            foreach ((IPS_GetEvent($eventID)['ScheduleGroups'] ?? []) as $group) {
                if (isset($group['ID']) && !IPS_SetEventScheduleGroup($eventID, (int) $group['ID'], 0)) {
                    throw new RuntimeException('Vorhandene Symcon-Wochenplangruppe konnte nicht entfernt werden.');
                }
            }
            foreach ($pointsByDay as $day => $points) {
                if (!IPS_SetEventScheduleGroup($eventID, (int) $day, 1 << (int) $day)) {
                    throw new RuntimeException('Symcon-Wochenplangruppe konnte nicht angelegt werden.');
                }
                foreach ($points as $pointID => $point) {
                    if (!IPS_SetEventScheduleGroupPoint($eventID, (int) $day, (int) $pointID, intdiv($point['Minute'], 60), $point['Minute'] % 60, 0, $point['Action'])) {
                        throw new RuntimeException('Symcon-Wochenplan-Schaltpunkt konnte nicht übernommen werden.');
                    }
                }
            }
            if (!IPS_SetEventActive($eventID, false)) {
                throw new RuntimeException('Symcon-Wochenplan konnte nicht deaktiviert bleiben.');
            }
        } catch (Throwable $exception) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Symcon-Wochenplan konnte nicht aktualisiert werden: ' . $exception->getMessage());
            return;
        } finally {
            $this->WriteAttributeBoolean('ScheduleEventSyncing', false);
        }
        if (!$this->scheduleEventMatches($eventID, $schedule)) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Mäherplan empfangen, aber das Symcon-Wochenplan-Ereignis stimmt nach dem Schreiben nicht überein.');
            return;
        }
        $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($eventID));
        $statusID = $this->GetIDForIdent('ScheduleSyncStatus');
        if ($statusID !== false && $statusID > 0) {
            $status = GetValueString($statusID);
            if (strpos($status, 'Symcon-Wochenplan geprüft') === false) {
                $this->SetValueSafe('ScheduleSyncStatus', rtrim($status, '.') . '; Symcon-Wochenplan geprüft.');
            }
        }
    }
    private function clearPendingForDifferentMower(): void
    {
        $serial = $this->ReadPropertyString('Serial');
        if ($this->ReadAttributeString('PendingCommand') !== '' && $this->ReadAttributeString('PendingCommandSerial') !== $serial) {
            $this->WriteAttributeString('PendingCommand', '');
            $this->WriteAttributeString('PendingCommandSerial', '');
            $this->SetTimerInterval('CommandConfirmationTimeout', 0);
            $this->SetValueSafe('CommandStatus', 'Rückmeldung verworfen: Seriennummer der Instanz wurde geändert.');
        }
        if ($this->ReadAttributeString('PendingSchedule') !== '' && $this->ReadAttributeString('PendingScheduleSerial') !== $serial) {
            $this->WriteAttributeString('PendingSchedule', '');
            $this->WriteAttributeString('PendingScheduleSerial', '');
            $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
            $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('ScheduleSyncStatus', 'Rückmeldung verworfen: Seriennummer der Instanz wurde geändert.');
        }
        if ($this->ReadAttributeString('PendingRainDelay') !== '' && $this->ReadAttributeString('PendingRainDelaySerial') !== $serial) {
            $this->WriteAttributeString('PendingRainDelay', '');
            $this->WriteAttributeString('PendingRainDelaySerial', '');
            $this->SetTimerInterval('RainDelayConfirmationTimeout', 0);
            $this->SetValueSafe('SettingStatus', 'Rückmeldung verworfen: Seriennummer der Instanz wurde geändert.');
        }
        if ($this->ReadAttributeString('PendingAutoSchedule') !== '' && $this->ReadAttributeString('PendingAutoScheduleSerial') !== $serial) {
            $this->WriteAttributeString('PendingAutoSchedule', '');
            $this->WriteAttributeString('PendingAutoScheduleSerial', '');
            $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('SettingStatus', 'Rückmeldung verworfen: Seriennummer der Instanz wurde geändert.');
        }
        if ($this->ReadAttributeString('PendingLock') !== '' && $this->ReadAttributeString('PendingLockSerial') !== $serial) {
            $this->WriteAttributeString('PendingLock', '');
            $this->WriteAttributeString('PendingLockSerial', '');
            $this->SetTimerInterval('LockConfirmationTimeout', 0);
            $this->SetValueSafe('SettingStatus', 'Rückmeldung verworfen: Seriennummer der Instanz wurde geändert.');
        }
        if ($this->ReadAttributeString('FailedSchedule') !== '' && $this->ReadAttributeString('FailedScheduleSerial') !== $serial) {
            $this->WriteAttributeString('FailedSchedule', '');
            $this->WriteAttributeString('FailedScheduleSerial', '');
        }
    }

    private function applyDevice(array $device): void
    {
        $this->SetValueSafe('Online', (bool) ($device['online'] ?? false));
        $this->SetValueSafe('DeviceDiagnostics', WorxScheduleCodec::canonicalJson(WorxScheduleCodec::sanitizedDeviceRecord($device)));
        if (isset($device['firmware_version'])) {
            $this->SetValueSafe('Firmware', (string) $device['firmware_version']);
        }
        $payload = $device['last_status']['payload'] ?? [];
        $dat = is_array($payload) ? ($payload['dat'] ?? null) : null;
        if (!is_array($dat)) {
            $this->SetSummary($device['name'] ?? '');
            return;
        }

        $state = (int) ($dat['ls'] ?? 0);
        $error = (int) ($dat['le'] ?? 0);
        $this->SetValueSafe('State', $state);
        $this->SetValueSafe('StateText', self::STATES[$state] ?? ('Unbekannter Status (' . $state . ')'));
        $this->SetValueSafe('Error', $error);
        $this->SetValueSafe('ErrorText', self::ERRORS[$error] ?? ('Unbekannter Fehler (' . $error . ')'));
        $this->confirmCommand($state, $error);

        if (isset($dat['bt']) && is_array($dat['bt'])) {
            $battery = $dat['bt'];
            $this->SetValueSafe('Battery', (int) ($battery['p'] ?? 0));
            $this->SetValueSafe('BatteryTemp', (float) ($battery['t'] ?? 0));
            $this->SetValueSafe('BatteryVoltage', (float) ($battery['v'] ?? 0));
            $this->SetValueSafe('ChargeCycles', (int) ($battery['nr'] ?? 0));
            $this->SetValueSafe('Charging', ((int) ($battery['c'] ?? 0)) > 0);
        }
        if (isset($dat['st']) && is_array($dat['st'])) {
            $stats = $dat['st'];
            $this->SetValueSafe('Distance', round(((float) ($stats['d'] ?? 0)) / 1000, 1));
            $this->SetValueSafe('WorkTime', round(((float) ($stats['wt'] ?? 0)) / 60, 1));
            $this->SetValueSafe('BladeTime', round(((float) ($stats['b'] ?? 0)) / 60, 1));
        }
        if (isset($dat['rsi'])) $this->SetValueSafe('WifiSignal', (int) $dat['rsi']);
        if (isset($dat['rain']['s'])) $this->SetValueSafe('Rain', ((int) $dat['rain']['s']) > 0);
        $configuration = WorxScheduleCodec::deviceConfiguration($device);
        $reportedSchedule = $configuration['sc'] ?? null;
        $supportsTimeExtension = in_array('unrestricted_mowing_time', $device['capabilities'] ?? [], true)
            && is_array($reportedSchedule) && isset($reportedSchedule['p'])
            && WorxScheduleCodec::timeExtensionFromProtocol($reportedSchedule['p']) !== null;
        $timeExtensionID = $this->GetIDForIdent('TimeExtension');
        if ($timeExtensionID !== false && $timeExtensionID > 0) {
            IPS_SetHidden($timeExtensionID, !$supportsTimeExtension);
            if ($supportsTimeExtension) $this->SetValueSafe('TimeExtension', WorxScheduleCodec::timeExtensionFromProtocol($reportedSchedule['p']));
        }
        $timeExtensionSetID = $this->GetIDForIdent('TimeExtensionSet');
        if ($timeExtensionSetID !== false && $timeExtensionSetID > 0) IPS_SetHidden($timeExtensionSetID, !$supportsTimeExtension);
        $supportsRainDelay = in_array('rain_delay', $device['capabilities'] ?? [], true)
            && isset($configuration['rd']) && is_numeric($configuration['rd'])
            && (int) $configuration['rd'] >= 0;
        $rainDelayID = $this->GetIDForIdent('RainDelay');
        if ($rainDelayID !== false && $rainDelayID > 0) {
            IPS_SetHidden($rainDelayID, !$supportsRainDelay);
            if ($supportsRainDelay) {
                $this->SetValueSafe('RainDelay', (int) $configuration['rd']);
                $this->confirmRainDelay((int) $configuration['rd']);
            }
        }
        $rainDelaySetID = $this->GetIDForIdent('RainDelaySet');
        if ($rainDelaySetID !== false && $rainDelaySetID > 0) IPS_SetHidden($rainDelaySetID, !$supportsRainDelay);
        $autoScheduleSupported = array_key_exists('auto_schedule', $device) && is_bool($device['auto_schedule']);
        $autoScheduleID = $this->GetIDForIdent('AutoSchedule');
        $autoScheduleSetID = $this->GetIDForIdent('AutoScheduleSet');
        if ($autoScheduleID !== false && $autoScheduleID > 0) {
            IPS_SetHidden($autoScheduleID, !$autoScheduleSupported);
            if ($autoScheduleSupported) {
                $this->SetValueSafe('AutoSchedule', $device['auto_schedule']);
                $this->confirmAutoSchedule($device['auto_schedule']);
                if ($this->ReadAttributeString('PendingAutoSchedule') === '') {
                    $this->SetValueSafe('AutoScheduleSet', $device['auto_schedule']);
                }
            }
        }
        if ($autoScheduleSetID !== false && $autoScheduleSetID > 0) IPS_SetHidden($autoScheduleSetID, !$autoScheduleSupported);
        $lockCapability = in_array('lock', $device['capabilities'] ?? [], true) && (int) ($device['protocol'] ?? -1) === 0;
        $lockCommandID = $this->GetIDForIdent('LockCommand');
        if ($lockCommandID !== false && $lockCommandID > 0) IPS_SetHidden($lockCommandID, !$lockCapability);
        if (isset($dat['lk'])) {
            $locked = ((int) $dat['lk']) > 0;
            $this->SetValueSafe('Locked', $locked);
            if ($lockCapability) $this->confirmLock($locked);
        }
        if (isset($dat['cut']['z'])) $this->SetValueSafe('Zone', (int) $dat['cut']['z']);
        if (isset($dat['tm'])) {
            $timestamp = strtotime((string) $dat['tm']);
            if ($timestamp !== false) $this->SetValueSafe('LastUpdate', $timestamp);
        }

        $schedule = WorxScheduleCodec::scheduleFromDevice($device);
        if ($schedule !== null) {
            $this->updateSchedule($schedule);
        } else {
            $this->SetValueSafe('ScheduleSyncStatus', 'Zeitplanformat des Geräts wird nicht unterstützt oder ist unvollständig.');
        }

        $summary = sprintf('%s · %d%%', self::STATES[$state] ?? 'Unbekannt', (int) ($dat['bt']['p'] ?? 0));
        if ($error > 0) $summary .= ' · ' . (self::ERRORS[$error] ?? ('Fehler ' . $error));
        $this->SetSummary($summary);
    }

    private function confirmCommand(int $state, int $error): void
    {
        $command = (int) $this->ReadAttributeString('PendingCommand');
        if ($command === 0) return;
        if ($error > 0) {
            $this->WriteAttributeString('PendingCommand', '');
            $this->WriteAttributeString('PendingCommandSerial', '');
            $this->SetTimerInterval('CommandConfirmationTimeout', 0);
            $this->SetValueSafe('CommandStatus', 'Mäher meldet einen Fehler: ' . (self::ERRORS[$error] ?? ('Fehler ' . $error)));
            return;
        }
        $confirmed = ($command === 1 && in_array($state, [2, 3, 4, 6, 7, 31, 32, 33], true))
            || ($command === 2 && $state === 34)
            || ($command === 3 && in_array($state, [1, 30], true))
            || ($command === 4 && $state === 32);
        if ($confirmed) {
            $this->WriteAttributeString('PendingCommand', '');
            $this->WriteAttributeString('PendingCommandSerial', '');
            $this->SetTimerInterval('CommandConfirmationTimeout', 0);
            $this->SetValueSafe('CommandStatus', 'Vom Mäher bestätigt: ' . self::COMMANDS[$command] . '.');
        }
    }

    private function confirmAutoSchedule(bool $reported): void
    {
        $pending = $this->ReadAttributeString('PendingAutoSchedule');
        if ($pending === '' || $this->ReadAttributeString('PendingAutoScheduleSerial') !== $this->ReadPropertyString('Serial')) {
            return;
        }
        $command = json_decode($pending, true);
        if (!is_array($command) || !isset($command['desired'], $command['previous'])) {
            return;
        }
        if ((bool) $command['desired'] === $reported) {
            $this->WriteAttributeString('PendingAutoSchedule', '');
            $this->WriteAttributeString('PendingAutoScheduleSerial', '');
            $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('SettingStatus', 'Automatischer Zeitplan aus der Worx-Cloud zurückgelesen und bestätigt.');
        } elseif ((bool) $command['previous'] !== $reported) {
            $this->WriteAttributeString('PendingAutoSchedule', '');
            $this->WriteAttributeString('PendingAutoScheduleSerial', '');
            $this->SetTimerInterval('AutoScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('SettingStatus', 'Abweichenden Automatikstatus aus der Worx-Cloud übernommen.');
        }
    }

    private function confirmLock(bool $reported): void
    {
        $pending = $this->ReadAttributeString('PendingLock');
        if ($pending !== '' && $this->ReadAttributeString('PendingLockSerial') === $this->ReadPropertyString('Serial')) {
            $command = json_decode($pending, true);
            if (is_array($command) && isset($command['desired'], $command['previous']) && (bool) $command['desired'] === $reported) {
                $this->WriteAttributeString('PendingLock', '');
                $this->WriteAttributeString('PendingLockSerial', '');
                $this->SetTimerInterval('LockConfirmationTimeout', 0);
                $this->SetValueSafe('SettingStatus', $reported
                    ? 'Sperre vom Mäher zurückgelesen und bestätigt.'
                    : 'Entsperren vom Mäher zurückgelesen und bestätigt.');
            } elseif (is_array($command) && isset($command['previous']) && (bool) $command['previous'] !== $reported) {
                $this->WriteAttributeString('PendingLock', '');
                $this->WriteAttributeString('PendingLockSerial', '');
                $this->SetTimerInterval('LockConfirmationTimeout', 0);
                $this->SetValueSafe('SettingStatus', 'Abweichenden Sperrstatus vom Mäher übernommen.');
            }
        }
    }
    private function confirmRainDelay(int $reported): void
    {
        $previous = $this->ReadAttributeString('ReportedRainDelay');
        $pending = $this->ReadAttributeString('PendingRainDelay');
        if ($pending !== '' && $this->ReadAttributeString('PendingRainDelaySerial') === $this->ReadPropertyString('Serial')) {
            $command = json_decode($pending, true);
            if (is_array($command) && isset($command['desired'], $command['previous']) && (int) $command['desired'] === $reported) {
                $this->WriteAttributeString('PendingRainDelay', '');
                $this->WriteAttributeString('PendingRainDelaySerial', '');
                $this->SetTimerInterval('RainDelayConfirmationTimeout', 0);
                $this->SetValueSafe('SettingStatus', 'Regenverzögerung vom Mäher zurückgelesen und bestätigt.');
            } elseif (is_array($command) && isset($command['previous']) && (int) $command['previous'] !== $reported) {
                $this->WriteAttributeString('PendingRainDelay', '');
                $this->WriteAttributeString('PendingRainDelaySerial', '');
                $this->SetTimerInterval('RainDelayConfirmationTimeout', 0);
                $this->SetValueSafe('SettingStatus', 'Abweichender Regenverzögerungswert vom Mäher übernommen: ' . $reported . ' Minuten.');
            }
        } elseif ($previous !== '' && (int) $previous !== $reported) {
            $this->SetValueSafe('SettingStatus', 'Regenverzögerung aus Worx übernommen: ' . $reported . ' Minuten.');
        }
        $this->WriteAttributeString('ReportedRainDelay', (string) $reported);
    }
    private function updateSchedule(array $schedule): void
    {
        $current = WorxScheduleCodec::canonicalJson($schedule);
        $previous = $this->ReadAttributeString('ReportedSchedule');
        $pending = $this->ReadAttributeString('PendingSchedule');
        $forceEventUpdate = false;

        $pendingSerial = $this->ReadAttributeString('PendingScheduleSerial');
        if ($pending !== '' && $pendingSerial === $this->ReadPropertyString('Serial')) {
            $pendingSchedule = json_decode($pending, true);
            if (is_array($pendingSchedule) && WorxScheduleCodec::matchesEditableSlots($pendingSchedule, $schedule)) {
                $purpose = $this->ReadAttributeString('PendingSchedulePurpose');
                $this->WriteAttributeString('PendingSchedule', '');
                $this->WriteAttributeString('PendingScheduleSerial', '');
                $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
                $this->WriteAttributeString('FailedSchedule', '');
                $this->WriteAttributeString('FailedScheduleSerial', '');
                $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
                $confirmedStatus = $purpose === 'time_extension'
                    ? 'Tägliche Arbeitszeit vom Mäher zurückgelesen und bestätigt.'
                    : 'Vom Mäher zurückgelesen und bestätigt.';
                $this->SetValueSafe('ScheduleSyncStatus', $confirmedStatus);
            } elseif ($previous === $current) {
                $this->SetValueSafe('ScheduleSyncStatus', 'Zeitplan gesendet; warte auf die passende Rückmeldung des Mähers.');
                return;
            } else {
                // A newer cloud schedule that differs from the last confirmed plan
                // is authoritative, including a change made in the Worx app.
                $this->WriteAttributeString('PendingSchedule', '');
                $this->WriteAttributeString('PendingScheduleSerial', '');
                $this->WriteAttributeString('PendingSchedulePurpose', 'schedule');
                $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
                $this->WriteAttributeString('FailedSchedule', '');
                $this->WriteAttributeString('FailedScheduleSerial', '');
                $forceEventUpdate = true;
                $this->SetValueSafe('ScheduleSyncStatus', 'Abweichende Änderung aus der Worx-Cloud übernommen.');
            }
        } elseif ($this->ReadAttributeString('FailedSchedule') !== '') {
            $failedSchedule = json_decode($this->ReadAttributeString('FailedSchedule'), true);
            if ($this->ReadAttributeString('FailedScheduleSerial') === $this->ReadPropertyString('Serial')
                && is_array($failedSchedule)
                && WorxScheduleCodec::matchesEditableSlots($failedSchedule, $schedule)) {
                $this->WriteAttributeString('FailedSchedule', '');
                $this->WriteAttributeString('FailedScheduleSerial', '');
                $this->SetValueSafe('ScheduleSyncStatus', 'Zeitplan nach Verzögerung vom Mäher zurückgelesen und bestätigt.');
            } elseif ($this->ReadAttributeString('FailedScheduleSerial') === $this->ReadPropertyString('Serial')
                && $previous === $current) {
                $this->SetValueSafe('ScheduleSyncStatus', 'Keine passende Mäher-Rückmeldung; die bearbeitete Symcon-Zeit bleibt erhalten.');
                return;
            } elseif ($previous !== '' && $previous !== $current) {
                $this->WriteAttributeString('FailedSchedule', '');
                $this->WriteAttributeString('FailedScheduleSerial', '');
                $forceEventUpdate = true;
                $this->SetValueSafe('ScheduleSyncStatus', 'Änderung aus der Worx-App übernommen.');
            }
        } elseif ($previous !== '' && $previous !== $current) {
            $this->WriteAttributeString('FailedSchedule', '');
            $this->WriteAttributeString('FailedScheduleSerial', '');
            $forceEventUpdate = true;
            $this->SetValueSafe('ScheduleSyncStatus', 'Änderung aus der Worx-App übernommen.');
        } elseif ($previous === '') {
            $this->SetValueSafe('ScheduleSyncStatus', 'Bestätigter Worx-Zeitplan empfangen.');
        }

        $this->WriteAttributeString('ReportedSchedule', $current);
        $this->syncScheduleEvent($schedule, $forceEventUpdate);

        $eventID = $this->scheduleEventID();
        if (!$forceEventUpdate && !$this->ReadAttributeBoolean('ScheduleWritesSuppressed') && $eventID !== 0 && $this->ReadAttributeString('ScheduleEventSnapshot') !== ''
            && $this->scheduleEventFingerprint($eventID) !== $this->ReadAttributeString('ScheduleEventSnapshot')) {
            $this->SendSchedule();
        }
    }
    private function currentSchedule(): ?array
    {
        $device = $this->getDevice();
        return $device === null ? null : WorxScheduleCodec::scheduleFromDevice($device);
    }

    private function getDevice(): ?array
    {
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0 || $this->ReadPropertyString('Serial') === '') return null;
        $result = $this->SendDataToParent(json_encode([
            'DataID'  => self::IF_CLOUD,
            'Command' => 'GetDevice',
            'Serial'  => $this->ReadPropertyString('Serial'),
        ]));
        $device = json_decode((string) $result, true);
        return is_array($device) ? $device : null;
    }

    /**
     * Build a modern Symcon value presentation for a variable that is display-only.
     */
    private function registerVariables(): void
    {
        $p = 0;
        $this->RegisterVariableInteger('Control', 'Steuerung', $this->enumerationPresentation([0 => 'Befehl wählen'] + self::COMMANDS), $p++);
        $this->RegisterVariableInteger('State', 'Status', $this->enumerationPresentation(self::STATES), $p++);
        $this->RegisterVariableInteger('Error', 'Fehler', $this->enumerationPresentation(self::ERRORS), $p++);
        $this->RegisterVariableBoolean('Online', 'Online', $this->booleanValuePresentation('Offline', 'Online'), $p++);
        $this->RegisterVariableInteger('Battery', 'Akku', $this->valuePresentation(' %'), $p++);
        $this->RegisterVariableBoolean('Charging', 'Lädt', $this->booleanValuePresentation('Nein', 'Ja'), $p++);
        $this->RegisterVariableFloat('BatteryTemp', 'Akkutemperatur', $this->valuePresentation(' °C'), $p++);
        $this->RegisterVariableFloat('BatteryVoltage', 'Akkuspannung', $this->valuePresentation(' V'), $p++);
        $this->RegisterVariableInteger('ChargeCycles', 'Ladezyklen', '', $p++);
        $this->RegisterVariableInteger('WifiSignal', 'WLAN-Signal', $this->valuePresentation(' dBm'), $p++);
        $this->RegisterVariableFloat('Distance', 'Gesamtstrecke', $this->valuePresentation(' km'), $p++);
        $this->RegisterVariableFloat('WorkTime', 'Mähzeit gesamt', $this->valuePresentation(' h'), $p++);
        $this->RegisterVariableFloat('BladeTime', 'Messerlaufzeit', $this->valuePresentation(' h'), $p++);
        $this->RegisterVariableBoolean('Rain', 'Regen erkannt', $this->booleanValuePresentation('Nein', 'Ja'), $p++);
        $this->RegisterVariableInteger('TimeExtension', 'Tägliche Arbeitszeit (bestätigt)', $this->valuePresentation(' %'), $p++);
        $this->RegisterVariableInteger('TimeExtensionSet', 'Tägliche Arbeitszeit setzen', $this->sliderPresentation(-100, 100, ' %'), $p++);
        $this->RegisterVariableInteger('RainDelay', 'Regenverzögerung (bestätigt)', $this->valuePresentation(' min'), $p++);
        $this->RegisterVariableInteger('RainDelaySet', 'Regenverzögerung setzen', $this->sliderPresentation(0, self::MAX_RAIN_DELAY_MINUTES, ' min'), $p++);
        $this->RegisterVariableString('SettingStatus', 'Einstellungsrückmeldung', '', $p++);
        $this->RegisterVariableBoolean('Locked', 'Gesperrt (bestätigt)', $this->booleanValuePresentation('Entsperrt', 'Gesperrt'), $p++);
        $this->RegisterVariableBoolean('LockCommand', 'Sperre setzen', ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], $p++);
        $this->RegisterVariableBoolean('AutoSchedule', 'Automatischer Zeitplan (bestätigt)', $this->booleanValuePresentation('Aus', 'Ein'), $p++);
        $this->RegisterVariableBoolean('AutoScheduleSet', 'Automatischen Zeitplan setzen', ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], $p++);
        $this->RegisterVariableInteger('Zone', 'Aktuelle Zone', '', $p++);
        $this->RegisterVariableString('Firmware', 'Firmware', '', $p++);
        $this->RegisterVariableInteger('LastUpdate', 'Letzte Meldung', ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME, 'DATE' => 0, 'TIME' => 1], $p++);
        $this->RegisterVariableString('LastCommand', 'Letzter Befehl', '', $p++);
        $this->RegisterVariableString('CommandStatus', 'Befehlsrückmeldung', '', $p++);
        $this->RegisterVariableString('ScheduleSyncStatus', 'Zeitplanrückmeldung', '', $p++);
        $this->RegisterVariableString('DeviceDiagnostics', 'Gerätenachweis (redigiert)', '', $p++);
        $this->RegisterVariableString('StateText', 'Status (Text)', '', $p++);
        $this->RegisterVariableString('ErrorText', 'Fehler (Text)', '', $p++);
    }

    private function valuePresentation(string $suffix): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => $suffix,
        ];
    }

    /**
     * Build a modern value presentation that gives Boolean status values readable labels.
     */
    private function booleanValuePresentation(string $falseCaption, string $trueCaption): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => json_encode([
                $this->presentationOption(false, $falseCaption),
                $this->presentationOption(true, $trueCaption),
            ], JSON_THROW_ON_ERROR),
        ];
    }

    /** Provide all option keys expected by current Symcon presentation editors. */
    private function presentationOption(bool $value, string $caption): array
    {
        return [
            'Value'              => $value,
            'Caption'            => $caption,
            'IconActive'         => false,
            'IconValue'          => '',
            'ColorActive'        => false,
            'ColorValue'         => -1,
            'ContentColorActive' => false,
            'ContentColorValue'  => -1,
            'Color'              => -1,
            'ContentColor'       => -1,
        ];
    }

    /**
     * Build a modern Symcon enumeration from an integer-to-caption map.
     */
    private function enumerationPresentation(array $values): array
    {
        $options = [];
        foreach ($values as $value => $caption) {
            $options[] = [
                'Value'              => (int) $value,
                'Caption'            => $caption,
                'IconActive'         => false,
                'IconValue'          => '',
                'ColorActive'        => false,
                'ColorValue'         => -1,
                'ContentColorActive' => false,
                'ContentColorValue'  => -1,
                'Color'              => -1,
                'ContentColor'       => -1,
            ];
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS' => json_encode($options, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Build a modern, bounded slider presentation for an actionable integer setting.
     */
    private function sliderPresentation(int $minimum, int $maximum, string $suffix): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'         => $minimum,
            'MAX'         => $maximum,
            'STEP_SIZE'   => 1,
            'SUFFIX'      => $suffix,
            'PERCENTAGE'  => false,
        ];
    }

    private function SetValueSafe(string $ident, $value): void
    {
        $id = $this->GetIDForIdent($ident);
        if ($id === false || $id === 0) return;
        if (GetValue($id) !== $value) SetValue($id, $value);
    }
}
