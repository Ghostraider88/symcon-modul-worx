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

    // Enable only after checking the actual WR105SI.1 wire format and readback.
    private const SCHEDULE_WRITE_VALIDATED = false;

    private const STATES = [
        0 => 'Bereit', 1 => 'In der Ladestation', 2 => 'Startsequenz', 3 => 'Verlässt Ladestation',
        4 => 'Folgt Begrenzung', 5 => 'Sucht Ladestation', 6 => 'Sucht Begrenzung', 7 => 'Mäht',
        8 => 'Angehoben', 9 => 'Eingeklemmt', 10 => 'Messer blockiert', 11 => 'Debug',
        12 => 'Fernsteuerung', 30 => 'Fährt zur Ladestation', 31 => 'Zonentraining',
        32 => 'Kantenschnitt', 33 => 'Sucht Zone', 34 => 'Pausiert',
    ];

    private const ERRORS = [
        0 => 'Kein Fehler', 1 => 'Eingeklemmt', 2 => 'Angehoben', 3 => 'Begrenzungsdraht fehlt',
        4 => 'Außerhalb der Begrenzung', 5 => 'Regen', 6 => 'Klappe schließen zum Mähen',
        7 => 'Klappe schließen zur Rückkehr', 8 => 'Messermotor blockiert', 9 => 'Radmotor blockiert',
        10 => 'Eingeklemmt (Zeitüberschreitung)', 11 => 'Umgedreht', 12 => 'Batterie leer',
        13 => 'Begrenzungsdraht vertauscht', 14 => 'Ladefehler', 15 => 'Ladestation nicht gefunden',
        16 => 'Mäher gesperrt', 17 => 'Batterietemperatur zu hoch/niedrig', 18 => 'Fehler Lagesensor',
        19 => 'Radmotor blockiert', 20 => 'Ladestation belegt', 21 => 'Kamerafehler',
        22 => 'Fehler Antriebssystem', 23 => 'Fehler Höhenverstellung', 24 => 'Fehler RFID',
    ];

    private const COMMANDS = [1 => 'Start', 2 => 'Pause', 3 => 'Heimfahrt'];

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Serial', '');
        $this->RegisterPropertyString('ScheduleDraft', '');
        $this->ConnectParent('{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}');

        $this->RegisterAttributeString('ReportedSchedule', '');
        $this->RegisterAttributeString('PendingSchedule', '');
        $this->RegisterAttributeString('PendingCommand', '');
        $this->RegisterAttributeString('PendingCommandSerial', '');
        $this->RegisterAttributeString('PendingScheduleSerial', '');
        $this->RegisterAttributeBoolean('ScheduleConflict', false);
        $this->RegisterAttributeString('ScheduleEventSnapshot', '');
        $this->RegisterTimer('CommandConfirmationTimeout', 0, 'WORXMOWER_CommandConfirmationTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ScheduleConfirmationTimeout', 0, 'WORXMOWER_ScheduleConfirmationTimeout($_IPS[\'TARGET\']);');

        $this->registerProfiles();

        $p = 0;
        $this->RegisterVariableInteger('Control', 'Steuerung', 'WORX.Control', $p++);
        $this->RegisterVariableInteger('State', 'Status', 'WORX.State', $p++);
        $this->RegisterVariableInteger('Error', 'Fehler', 'WORX.Error', $p++);
        $this->RegisterVariableBoolean('Online', 'Online', '~Alert.Reversed', $p++);
        $this->RegisterVariableInteger('Battery', 'Akku', '~Battery.100', $p++);
        $this->RegisterVariableBoolean('Charging', 'Lädt', '~Switch', $p++);
        $this->RegisterVariableFloat('BatteryTemp', 'Akkutemperatur', '~Temperature', $p++);
        $this->RegisterVariableFloat('BatteryVoltage', 'Akkuspannung', '~Volt', $p++);
        $this->RegisterVariableInteger('ChargeCycles', 'Ladezyklen', '', $p++);
        $this->RegisterVariableInteger('WifiSignal', 'WLAN-Signal', 'WORX.dBm', $p++);
        $this->RegisterVariableFloat('Distance', 'Gesamtstrecke', 'WORX.km', $p++);
        $this->RegisterVariableFloat('WorkTime', 'Mähzeit gesamt', 'WORX.Hours', $p++);
        $this->RegisterVariableFloat('BladeTime', 'Messerlaufzeit', 'WORX.Hours', $p++);
        $this->RegisterVariableBoolean('Rain', 'Regen erkannt', '~Alert', $p++);
        $this->RegisterVariableBoolean('Locked', 'Gesperrt', '~Lock', $p++);
        $this->RegisterVariableInteger('Zone', 'Aktuelle Zone', '', $p++);
        $this->RegisterVariableString('Firmware', 'Firmware', '', $p++);
        $this->RegisterVariableInteger('LastUpdate', 'Letzte Meldung', '~UnixTimestamp', $p++);
        $this->RegisterVariableString('LastCommand', 'Letzter Befehl', '', $p++);
        $this->RegisterVariableString('CommandStatus', 'Befehlsrückmeldung', '', $p++);
        $this->RegisterVariableString('Schedule', 'Wochenplan (Mäher)', '', $p++);
        $this->RegisterVariableString('SchedulePreview', 'Geplanter Wochenplan', '', $p++);
        $this->RegisterVariableString('ScheduleSyncStatus', 'Zeitplanrückmeldung', '', $p++);
        $this->RegisterVariableString('DeviceDiagnostics', 'Gerätenachweis (redigiert)', '', $p++);
        $this->EnableAction('Control');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->clearPendingForDifferentMower();
        if ($this->ReadPropertyString('Serial') === '') {
            $this->SetTimerInterval('CommandConfirmationTimeout', 0);
            $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
            $this->SetStatus(104);
            return;
        }
        $this->SetStatus(102);
        $this->SetValueSafe('Control', 0);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->Update();
        }
        $schedule = $this->currentSchedule();
        if ($schedule !== null) {
            $this->refreshPreview($schedule);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident !== 'Control') {
            throw new InvalidArgumentException('Invalid Ident: ' . $Ident);
        }
        $command = (int) $Value;
        if (!isset(self::COMMANDS[$command])) {
            throw new InvalidArgumentException('Control expects Start (1), Pause (2) or Heimfahrt (3).');
        }
        $this->Command($command);
        $this->SetValueSafe('Control', 0);
    }

    /** true means MQTT accepted the publish; device confirmation is separate. */
    public function Command(int $Command): bool
    {
        if (!isset(self::COMMANDS[$Command])) {
            return false;
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
        if (!WORX_SendCommand($parent, $this->ReadPropertyString('Serial'), (string) $payload)) {
            $this->SetValueSafe('CommandStatus', 'Nicht gesendet: MQTT-Verbindung nicht bereit.');
            return false;
        }
        $this->WriteAttributeString('PendingCommand', (string) $Command);
        $this->WriteAttributeString('PendingCommandSerial', $this->ReadPropertyString('Serial'));
        $this->SetValueSafe('CommandStatus', 'MQTT-Publish gesendet; Bestätigung des Mähers steht aus.');
        $this->SetTimerInterval('CommandConfirmationTimeout', 90000);
        return true;
    }

    public function Start(): bool { return $this->Command(1); }
    public function Pause(): bool { return $this->Command(2); }
    public function Home(): bool { return $this->Command(3); }

    /** Read cached device state from WorxCloud. */
    public function Update(): bool
    {
        $device = $this->getDevice();
        if ($device === null) {
            return false;
        }
        $this->applyDevice($device);
        return true;
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

    /** Explicit schedule transfer. It is locked pending real WR105SI.1 verification. */
    public function SendSchedule(): bool
    {
        if (!self::SCHEDULE_WRITE_VALIDATED) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Gesperrt: Wire-Felder und Rückmeldung des WR105SI.1 sind noch nicht geprüft.');
            return false;
        }
        $device = $this->getDevice();
        $schedule = $device === null ? null : WorxScheduleCodec::scheduleFromDevice($device);
        if ($schedule === null || $this->ReadAttributeBoolean('ScheduleConflict')) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Zeitplan fehlt, ist unvollständig oder es besteht ein Konflikt.');
            return false;
        }
        try {
            $desired = WorxScheduleCodec::mergeRows($schedule, $this->draftRows($schedule));
        } catch (InvalidArgumentException $exception) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Ungültiger Entwurf: ' . $exception->getMessage());
            return false;
        }
        if (WorxScheduleCodec::canonicalJson($desired) === WorxScheduleCodec::canonicalJson($schedule)) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Keine Änderung zum bestätigten Gerätezeitplan.');
            return false;
        }
        $parent = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parent === 0 || !WORX_SetSchedule($parent, $this->ReadPropertyString('Serial'), WorxScheduleCodec::canonicalJson($desired))) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Nicht gesendet: Worx-Cloud oder MQTT ist nicht bereit.');
            return false;
        }
        $this->WriteAttributeString('PendingSchedule', WorxScheduleCodec::canonicalJson($desired));
        $this->WriteAttributeString('PendingScheduleSerial', $this->ReadPropertyString('Serial'));
        $this->SetValueSafe('ScheduleSyncStatus', 'MQTT-Publish gesendet; Rückmeldung des Mähers steht aus.');
        $this->SetTimerInterval('ScheduleConfirmationTimeout', 120000);
        return true;
    }

    /** Copy the editable native event into the explicit, unsent draft. */
    public function ImportScheduleEvent(): bool
    {
        $schedule = $this->currentSchedule();
        $eventID = $this->scheduleEventID();
        if ($schedule === null || $eventID === 0) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Kein bestätigter Zeitplan oder Wochenplan-Ereignis verfügbar.');
            return false;
        }
        try {
            $rows = WorxScheduleCodec::rowsFromEvent(IPS_GetEvent($eventID), $schedule);
            IPS_SetProperty($this->InstanceID, 'ScheduleDraft', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $this->ReloadForm();
            $this->refreshPreview($schedule);
            return true;
        } catch (Throwable $exception) {
            $this->SetValueSafe('SchedulePreview', 'Wochenplan kann nicht übernommen werden: ' . $exception->getMessage());
            return false;
        }
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

    /** Maintain a disabled native event with no-op actions as an editing surface. */
    private function syncScheduleEvent(array $schedule): void
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
        $baseline = $this->ReadAttributeString('ScheduleEventSnapshot');
        if ($baseline !== '' && $this->scheduleEventFingerprint($eventID) !== $baseline) return;
        try { $pointsByDay = WorxScheduleCodec::toEventPoints($schedule); }
        catch (InvalidArgumentException $exception) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Mäherplan im Wochenplan nicht darstellbar: ' . $exception->getMessage());
            return;
        }
        $noop = '// Anzeige- und Entwurfsereignis; absichtlich keine Mäheraktion.';
        $actions = [0 => ['Kein Mähfenster', 0xB0B0B0], 1 => ['Mähen', 0x66AA33], 2 => ['Mähen mit Kantenschnitt', 0xE87922], 3 => ['Einsatz 2', 0x6688CC], 4 => ['Einsatz 2 mit Kantenschnitt', 0x9966CC]];
        foreach ($actions as $id => [$name, $color]) IPS_SetEventScheduleAction($eventID, $id, $name, $color, $noop);
        $existingGroups = [];
        foreach ((IPS_GetEvent($eventID)['ScheduleGroups'] ?? []) as $group) {
            if (isset($group['ID'])) $existingGroups[(int) $group['ID']] = true;
        }
        foreach ($pointsByDay as $day => $points) {
            if (isset($existingGroups[(int) $day])) IPS_SetEventScheduleGroup($eventID, (int) $day, 0);
            IPS_SetEventScheduleGroup($eventID, (int) $day, 1 << (int) $day);
            foreach ($points as $pointID => $point) IPS_SetEventScheduleGroupPoint($eventID, (int) $day, (int) $pointID, intdiv($point['Minute'], 60), $point['Minute'] % 60, 0, $point['Action']);
        }
        IPS_SetEventActive($eventID, false);
        $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($eventID));
    }
    /** Discard only the local draft and conflict flag; the device schedule is untouched. */
    public function DiscardSchedule(): bool
    {
        $this->clearDraft();
        $schedule = $this->currentSchedule();
        if ($schedule !== null) {
            $eventID = $this->scheduleEventID();
            if ($eventID !== 0) $this->WriteAttributeString('ScheduleEventSnapshot', $this->scheduleEventFingerprint($eventID));
            $this->syncScheduleEvent($schedule);
            $this->SetValueSafe('Schedule', WorxScheduleCodec::describe($schedule));
        }
        $this->SetValueSafe('SchedulePreview', 'Keine geplante Änderung.');
        $this->SetValueSafe('ScheduleSyncStatus', 'Lokaler Entwurf verworfen; der bestätigte Mäherplan bleibt unverändert.');
        return true;
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

    public function ScheduleConfirmationTimeout(): void
    {
        if ($this->ReadAttributeString('PendingSchedule') === '') {
            return;
        }
        $this->WriteAttributeString('PendingSchedule', '');
        $this->WriteAttributeString('PendingScheduleSerial', '');
        $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
        $this->SetValueSafe('ScheduleSyncStatus', 'Keine passende Zeitplanrückmeldung innerhalb von 120 Sekunden.');
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
            $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('ScheduleSyncStatus', 'Rückmeldung verworfen: Seriennummer der Instanz wurde geändert.');
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
        $this->SetValueSafe('Error', $error);
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
        if (isset($dat['lk'])) $this->SetValueSafe('Locked', ((int) $dat['lk']) > 0);
        if (isset($dat['cut']['z'])) $this->SetValueSafe('Zone', (int) $dat['cut']['z']);
        if (isset($dat['tm'])) {
            $timestamp = strtotime((string) $dat['tm']);
            if ($timestamp !== false) $this->SetValueSafe('LastUpdate', $timestamp);
        }

        $schedule = WorxScheduleCodec::scheduleFromDevice($device);
        if ($schedule !== null) {
            $this->updateSchedule($schedule);
        } else {
            $this->SetValueSafe('Schedule', 'Kein unterstützter bestätigter Zeitplan empfangen.');
            $this->SetValueSafe('SchedulePreview', 'Zeitplanformat nicht verfügbar.');
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
            || ($command === 3 && in_array($state, [1, 30], true));
        if ($confirmed) {
            $this->WriteAttributeString('PendingCommand', '');
            $this->WriteAttributeString('PendingCommandSerial', '');
            $this->SetTimerInterval('CommandConfirmationTimeout', 0);
            $this->SetValueSafe('CommandStatus', 'Vom Mäher bestätigt: ' . self::COMMANDS[$command] . '.');
        }
    }

    private function updateSchedule(array $schedule): void
    {
        $current = WorxScheduleCodec::canonicalJson($schedule);
        $previous = $this->ReadAttributeString('ReportedSchedule');
        $pending = $this->ReadAttributeString('PendingSchedule');

        if ($pending !== '' && $this->ReadAttributeString('PendingScheduleSerial') === $this->ReadPropertyString('Serial') && $pending === $current) {
            $this->WriteAttributeString('PendingSchedule', '');
            $this->WriteAttributeString('PendingScheduleSerial', '');
            $this->SetTimerInterval('ScheduleConfirmationTimeout', 0);
            $this->SetValueSafe('ScheduleSyncStatus', 'Vom Mäher zurückgelesen und bestätigt.');
            $this->clearDraft();
        } elseif ($pending === '' && $previous !== '' && $previous !== $current) {
            $old = json_decode($previous, true);
            if (is_array($old) && $this->draftDiffersFrom($old)) {
                $this->WriteAttributeBoolean('ScheduleConflict', true);
                $this->SetValueSafe('ScheduleSyncStatus', 'Der Mäherplan hat sich geändert. Bitte Entwurf prüfen.');
            } else {
                $this->clearDraft();
                $this->SetValueSafe('ScheduleSyncStatus', 'Änderung aus der Worx-App übernommen.');
            }
        } elseif ($previous === '') {
            $this->SetValueSafe('ScheduleSyncStatus', 'Zeitplan gelesen; Schreiben wartet auf Geräteprüfung.');
        }

        $this->WriteAttributeString('ReportedSchedule', $current);
        $this->SetValueSafe('Schedule', WorxScheduleCodec::describe($schedule));
        $this->syncScheduleEvent($schedule);
        $this->refreshPreview($schedule);
    }

    private function refreshPreview(array $schedule): void
    {
        if ($this->ReadPropertyString('ScheduleDraft') === '') {
            $this->SetValueSafe('SchedulePreview', 'Keine geplante Änderung.');
            return;
        }
        try {
            $desired = WorxScheduleCodec::mergeRows($schedule, $this->draftRows($schedule));
        } catch (InvalidArgumentException $exception) {
            $this->SetValueSafe('SchedulePreview', 'Entwurf prüfen: ' . $exception->getMessage());
            return;
        }
        if (WorxScheduleCodec::canonicalJson($desired) === WorxScheduleCodec::canonicalJson($schedule)) {
            $this->SetValueSafe('SchedulePreview', 'Keine geplante Änderung.');
            return;
        }
        $this->SetValueSafe('SchedulePreview', "Entwurf (noch nicht gesendet):\n" . WorxScheduleCodec::describe($desired));
        if (!$this->ReadAttributeBoolean('ScheduleConflict')) {
            $this->SetValueSafe('ScheduleSyncStatus', 'Entwurf geändert; noch nicht an den Mäher gesendet.');
        }
    }

    private function draftDiffersFrom(array $schedule): bool
    {
        if ($this->ReadPropertyString('ScheduleDraft') === '') {
            $eventID = $this->scheduleEventID();
            $snapshot = $this->ReadAttributeString('ScheduleEventSnapshot');
            return $eventID !== 0 && $snapshot !== '' && $this->scheduleEventFingerprint($eventID) !== $snapshot;
        }
        try {
            $desired = WorxScheduleCodec::mergeRows($schedule, $this->draftRows($schedule));
            return WorxScheduleCodec::canonicalJson($desired) !== WorxScheduleCodec::canonicalJson($schedule);
        } catch (InvalidArgumentException $exception) {
            return true;
        }
    }

    private function draftRows(array $schedule): array
    {
        $baseline = WorxScheduleCodec::toRows($schedule);
        $raw = $this->ReadPropertyString('ScheduleDraft');
        if ($raw === '') return $baseline;

        $saved = json_decode($raw, true);
        if (!is_array($saved) || count($saved) !== count($baseline)) {
            throw new InvalidArgumentException('Der Entwurf enthält nicht dieselben Tageseinsätze wie der bestätigte Geräteplan.');
        }
        $rows = [];
        foreach ($baseline as $index => $baseRow) {
            if (!isset($saved[$index]) || !is_array($saved[$index])) {
                throw new InvalidArgumentException('ScheduleDraft enthält eine ungültige Zeile.');
            }
            $rows[] = array_merge($baseRow, $saved[$index]);
        }
        return $rows;
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
            'DataID' => self::IF_CLOUD,
            'Command' => 'GetDevice',
            'Serial' => $this->ReadPropertyString('Serial'),
        ]));
        $device = json_decode((string) $result, true);
        return is_array($device) ? $device : null;
    }

    private function clearDraft(): void
    {
        if ($this->ReadPropertyString('ScheduleDraft') !== '') {
            IPS_SetProperty($this->InstanceID, 'ScheduleDraft', '');
            $this->ReloadForm();
        }
        $this->WriteAttributeBoolean('ScheduleConflict', false);
    }

    private function registerProfiles(): void
    {
        if (!IPS_VariableProfileExists('WORX.Control')) {
            IPS_CreateVariableProfile('WORX.Control', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileIcon('WORX.Control', 'Motor');
        }
        IPS_SetVariableProfileAssociation('WORX.Control', 0, 'Befehl wählen', '', -1);
        IPS_SetVariableProfileAssociation('WORX.Control', 1, 'Start', 'Play', 0x00AA00);
        IPS_SetVariableProfileAssociation('WORX.Control', 2, 'Pause', 'Pause', 0xCC8800);
        IPS_SetVariableProfileAssociation('WORX.Control', 3, 'Ladestation', 'Home', 0x0066CC);

        if (!IPS_VariableProfileExists('WORX.State')) {
            IPS_CreateVariableProfile('WORX.State', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileIcon('WORX.State', 'Motor');
            foreach (self::STATES as $code => $name) IPS_SetVariableProfileAssociation('WORX.State', $code, $name, '', -1);
        }
        if (!IPS_VariableProfileExists('WORX.Error')) {
            IPS_CreateVariableProfile('WORX.Error', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileIcon('WORX.Error', 'Warning');
            foreach (self::ERRORS as $code => $name) IPS_SetVariableProfileAssociation('WORX.Error', $code, $name, '', $code === 0 ? 0x00FF00 : 0xFF0000);
        }
        if (!IPS_VariableProfileExists('WORX.km')) {
            IPS_CreateVariableProfile('WORX.km', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('WORX.km', '', ' km');
            IPS_SetVariableProfileDigits('WORX.km', 1);
            IPS_SetVariableProfileIcon('WORX.km', 'Distance');
        }
        if (!IPS_VariableProfileExists('WORX.Hours')) {
            IPS_CreateVariableProfile('WORX.Hours', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('WORX.Hours', '', ' h');
            IPS_SetVariableProfileDigits('WORX.Hours', 1);
            IPS_SetVariableProfileIcon('WORX.Hours', 'Clock');
        }
        if (!IPS_VariableProfileExists('WORX.dBm')) {
            IPS_CreateVariableProfile('WORX.dBm', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileText('WORX.dBm', '', ' dBm');
            IPS_SetVariableProfileValues('WORX.dBm', -100, 0, 1);
            IPS_SetVariableProfileIcon('WORX.dBm', 'Network');
        }
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
        $timeExtension = is_array($reportedSchedule) ? ($reportedSchedule['p'] ?? null) : null;
        if (is_numeric($timeExtension) && (float) $timeExtension >= 0 && (float) $timeExtension <= 100) {
            $extensionText = rtrim(rtrim(sprintf('%.1f', (float) $timeExtension), '0'), '.');
            $elements[] = ['type' => 'Label', 'caption' => 'Vom Mäher gemeldete Zeiterweiterung: ' . str_replace('.', ',', $extensionText) . ' %'];
        }
        $actions = [
            ['type' => 'RowLayout', 'items' => [
                ['type' => 'Button', 'caption' => 'Start', 'onClick' => 'WORXMOWER_Start($id);'],
                ['type' => 'Button', 'caption' => 'Pause', 'onClick' => 'WORXMOWER_Pause($id);'],
                ['type' => 'Button', 'caption' => 'Ladestation', 'onClick' => 'WORXMOWER_Home($id);'],
            ]],
            ['type' => 'Button', 'caption' => 'Jetzt aktualisieren', 'onClick' => 'WORXMOWER_Update($id);'],
        ];

        if ($schedule === null) {
            $elements[] = ['type' => 'Label', 'caption' => 'Kein unterstützter Zeitplan empfangen. Der Editor benötigt Protokoll 0 und sieben empfangene Tagesfelder.'];
        } else {
            $elements[] = ['type' => 'Label', 'caption' => 'Der bestätigte Mäherplan wird als natives, deaktiviertes Symcon-Wochenplan-Ereignis unter dieser Instanz angezeigt. Dort lässt er sich in der Teilübersicht bearbeiten. Änderungen senden keinen Mähbefehl.'];
            $actions[] = ['type' => 'Button', 'caption' => 'Entwurf aus Wochenplan übernehmen', 'onClick' => 'WORXMOWER_ImportScheduleEvent($id);'];
            $actions[] = ['type' => 'Button', 'caption' => 'Entwurf verwerfen', 'onClick' => 'WORXMOWER_DiscardSchedule($id);'];
            $elements[] = ['type' => 'Label', 'caption' => 'Die Worx-App zeigt außerdem „Ganzer Tag“. Die Cloud-Zuordnung dieses Schalters ist noch offen.'];
            if (self::SCHEDULE_WRITE_VALIDATED) {
                $actions[] = ['type' => 'Button', 'caption' => 'Zeitplan übertragen', 'onClick' => 'WORXMOWER_SendSchedule($id);'];
            } else {
                $elements[] = ['type' => 'Label', 'caption' => 'Übertragung gesperrt, bis WR105SI.1-Felder und Mäher-Rückmeldung geprüft wurden.'];
            }
        }

        $elements[] = ['type' => 'Label', 'caption' => 'Befehlsstatus und bestätigter Mäherzustand sind getrennt. MQTT-Publish ist keine Gerätebestätigung.'];
        return json_encode([
            'elements' => $elements,
            'actions' => $actions,
            'status' => [
                ['code' => 102, 'icon' => 'active', 'caption' => 'Aktiv'],
                ['code' => 104, 'icon' => 'inactive', 'caption' => 'Seriennummer fehlt'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function SetValueSafe(string $ident, $value): void
    {
        $id = $this->GetIDForIdent($ident);
        if ($id === false || $id === 0) return;
        if (GetValue($id) !== $value) SetValue($id, $value);
    }
}
