<?php

declare(strict_types=1);

/**
 * Worx Cloud — Worx-Anmeldung, REST-Abfrage und MQTT-Transport.
 *
 * Zwei Wege, bewusst kombiniert:
 *   REST  — Anmeldung, Token-Verwaltung, Bestandsabfrage (auch als Rückfallebene)
 *   MQTT  — Zustand in Echtzeit und Steuerbefehle
 *
 * Die MQTT-Seite spricht AWS IoT über WebSocket. Symcon kann das mit Bordmitteln:
 * ein "WS Client" als I/O, darauf der "MQTT Client" als Splitter, darauf diese
 * Instanz. Beide werden hier automatisch angelegt und gepflegt — insbesondere die
 * Auth-Header, die aus dem Zugangs-Token abgeleitet werden und stündlich ablaufen.
 *
 * Zwei Fallstricke, die es zu wissen lohnt:
 *   - AWS verlangt beim WebSocket-Handshake das Subprotokoll "mqtt". Fehlt der
 *     Header, antwortet es mit HTTP 426 und die Verbindung kommt nie zustande.
 *
 */
class WorxCloud extends IPSModule
{
    private const AUTH_URL = 'https://id.worx.com/oauth/token';
    private const CLIENT_ID = '150da4d2-bb44-433b-9429-3773adc70a2a';

    private const GUID_WS = '{D68FD31F-0E90-7019-F16C-1949BD3079EF}'; // WS Client (I/O)
    private const GUID_MQTT = '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}'; // MQTT Client (Splitter)
    private const IF_MQTT_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}'; // an den MQTT Client senden
    private const IF_MQTT_RX = '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}'; // vom MQTT Client empfangen
    private const IF_MOWER = '{557B9D5F-D12D-4E44-87A7-05A5EC0F4F07}'; // zu den Mäher-Instanzen
    private const IF_CONF = '{F925090C-4AED-407D-8B80-F1730A55717E}'; // zum Konfigurator

    private const CLOUD_API = 'api.worxlandroid.com';
    private const CLOUD_PREFIX = 'WX';
    private const MAX_RAIN_DELAY_MINUTES = 300;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Cloud', 'worx');
        $this->RegisterPropertyInteger('Interval', 300);
        $this->RegisterPropertyBoolean('UseMQTT', true);

        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeString('RefreshToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);
        $this->RegisterAttributeString('Devices', '[]');

        $this->RegisterTimer('WorxPoll', 0, 'WORX_Poll($_IPS[\'TARGET\']);');
        // Der Token lebt eine Stunde; die Header der WS-Verbindung müssen vorher neu
        // gesetzt werden, sonst fliegt die MQTT-Verbindung beim nächsten Reconnect raus.
        $this->RegisterTimer('WorxAuth', 0, 'WORX_RefreshTransport($_IPS[\'TARGET\']);');

        // ConnectParent() würde sofort den normalen MQTT-Client mit Broker-Abfrage anlegen.
        // Der Worx-Transport wird erst nach der Kontokonfiguration in ApplyChanges() aufgebaut.
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if ($this->ReadPropertyString('Cloud') !== 'worx') {
            $this->SetTimerInterval('WorxPoll', 0);
            $this->SetTimerInterval('WorxAuth', 0);
            $this->SetStatus(203);
            return false;
        }
        if ($this->ReadPropertyString('Email') === '' || $this->ReadPropertyString('Password') === '') {
            $this->SetTimerInterval('WorxPoll', 0);
            $this->SetTimerInterval('WorxAuth', 0);
            $this->SetStatus(104);
            return;
        }

        // Zugangsdaten könnten sich geändert haben → Token verwerfen
        $this->WriteAttributeString('AccessToken', '');
        $this->WriteAttributeInteger('TokenExpires', 0);

        $this->SetTimerInterval('WorxPoll', max(30, $this->ReadPropertyInteger('Interval')) * 1000);
        $this->SetTimerInterval('WorxAuth', $this->ReadPropertyBoolean('UseMQTT') ? 45 * 60 * 1000 : 0);

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean('UseMQTT')) {
            $this->ensureParent();
        }
        if ($this->Poll() && $this->ReadPropertyBoolean('UseMQTT')) {
            $this->RefreshTransport();
        }
    }
    // ── REST ──────────────────────────────────────────────────────────────────

    /** Holt die Mäherliste und verteilt sie an die Kind-Instanzen. */
    public function Poll(): bool
    {
        if ($this->ReadPropertyString('Cloud') !== 'worx') {
            $this->SetTimerInterval('WorxPoll', 0);
            $this->SetTimerInterval('WorxAuth', 0);
            $this->SetStatus(203);
            return false;
        }
        if ($this->ReadPropertyString('Email') === '' || $this->ReadPropertyString('Password') === '') {
            $this->SetStatus(104);
            return false;
        }
        $token = $this->getToken();
        if ($token === '') {
            $this->SetStatus(201);
            return false;
        }
        $devices = $this->request('GET', '/api/v2/product-items?status=1', null, $token);
        if (!is_array($devices)) {
            $this->SetStatus(202);
            return false;
        }

        $this->WriteAttributeString('Devices', json_encode($devices));
        $this->SetStatus(102);

        $names = [];
        foreach ($devices as $device) {
            if (!isset($device['serial_number'])) {
                continue;
            }
            $names[] = $device['name'] ?? $device['serial_number'];
            $this->SendDataToChildren(json_encode([
                'DataID' => self::IF_MOWER,
                'Serial' => $device['serial_number'],
                'Device' => $device,
            ]));
        }
        $this->SetSummary(implode(', ', $names));
        return true;
    }

    // ── MQTT-Transport ────────────────────────────────────────────────────────

    /**
     * Richtet die WS-/MQTT-Kette ein und erneuert die Auth-Header.
     *
     * Läuft auch turnusmäßig per Timer, weil die Header aus dem Token abgeleitet
     * sind und mit ihm ablaufen.
     */
    public function RefreshTransport(): bool
    {
        if ($this->ReadPropertyString('Cloud') !== 'worx') {
            $this->SetStatus(203);
            return false;
        }
        if (!$this->ReadPropertyBoolean('UseMQTT')) {
            return false;
        }
        $token = $this->getToken();
        if ($token === '') {
            return false;
        }
        $this->ensureParent();
        $mqtt = $this->getParent();
        if ($mqtt === 0) {
            $this->SendDebug('MQTT', 'Keine MQTT-Client-Instanz als Übergeordnete gefunden', 0);
            return false;
        }
        $ws = IPS_GetInstance($mqtt)['ConnectionID'];
        if ($ws === 0) {
            $ws = IPS_CreateInstance(self::GUID_WS);
            IPS_SetName($ws, 'Worx WebSocket');
            IPS_ConnectInstance($mqtt, $ws);
        }

        $devices = json_decode($this->ReadAttributeString('Devices'), true) ?: [];
        if (!$devices) {
            return false;
        }
        $endpoint = $devices[0]['mqtt_endpoint'] ?? 'iot.eu-west-1.worxlandroid.com';
        $userId = $devices[0]['user_id'] ?? 0;
        $uuid = $devices[0]['uuid'] ?? '';
        $prefix = self::CLOUD_PREFIX;

        // Der Zugangs-Token ist ein JWT in base64url. AWS erwartet ihn zerlegt:
        // Signatur separat, Kopf und Nutzlast zusammen im Header "jwt".
        $parts = explode('.', str_replace(['_', '-'], ['/', '+'], $token));
        if (count($parts) !== 3) {
            return false;
        }
        $headers = [
            // Ohne dieses Subprotokoll antwortet AWS mit HTTP 426 Upgrade Required
            ['Name' => 'Sec-WebSocket-Protocol',           'Value' => 'mqtt'],
            ['Name' => 'x-amz-customauthorizer-name',      'Value' => 'com-worxlandroid-customer'],
            ['Name' => 'x-amz-customauthorizer-signature', 'Value' => $parts[2]],
            ['Name' => 'jwt',                              'Value' => $parts[0] . '.' . $parts[1]],
        ];

        $this->setIfChanged($ws, [
            'URL'               => 'wss://' . $endpoint . '/mqtt',
            'Type'              => 1,        // binäre Frames — MQTT ist kein Text
            'VerifyCertificate' => true,
            'Headers'           => json_encode($headers),
            'Active'            => true,
        ]);

        $subs = [];
        foreach ($devices as $d) {
            if (isset($d['mqtt_topics']['command_out'])) {
                // QoS 0: Symcons MQTT Client unterstützt (Stand 9.0) nichts anderes —
                // ein Abonnement mit QoS 1 wird kommentarlos nicht bedient.
                $subs[] = ['Topic' => $d['mqtt_topics']['command_out'], 'QoS' => 0];
            }
        }
        $this->setIfChanged($mqtt, [
            'ClientID'      => sprintf('%s/USER/%s/IPSymconWorx/%s', $prefix, $userId, $uuid),
            'UserName'      => 'IPSymconWorx',
            'Subscriptions' => json_encode($subs),
        ]);

        // Stupst den Mäher an, damit er seinen Zustand meldet — sonst bleibt es
        // still, bis er von sich aus etwas sendet.
        foreach ($devices as $d) {
            if (isset($d['mqtt_topics']['command_in'])) {
                $this->publish($d['mqtt_topics']['command_in'], '{}');
            }
        }
        return true;
    }

    /**
     * Sendet einen Befehl an einen Mäher. Wird von der Mäher-Instanz gerufen.
     *
     * @param string $serial  Seriennummer
     * @param string $payload JSON, z.B. {"cmd":1} für Start
     */
    public function SendCommand(string $serial, string $payload): bool
    {
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $d) {
            if (($d['serial_number'] ?? '') !== $serial) {
                continue;
            }
            $topic = $d['mqtt_topics']['command_in'] ?? '';
            if ($topic === '') {
                return false;
            }
            return $this->publish($topic, $payload);
        }
        return false;
    }

    /**
     * Patch the protocol-0 schedule over the mower's commandIn topic.
     * The mower's next commandOut payload remains the confirmation source.
     */
    public function SetSchedule(string $serial, string $scheduleJson): bool
    {
        $schedule = json_decode($scheduleJson, true);
        if (!is_array($schedule) || !isset($schedule['d']) || !is_array($schedule['d']) || count($schedule['d']) !== 7) {
            $this->SendDebug('Schedule', 'Zeitplan verworfen: Protokoll-0-Feld d muss sieben Tageswerte enthalten.', 0);
            return false;
        }
        if (isset($schedule['dd']) && (!is_array($schedule['dd']) || count($schedule['dd']) !== 7)) {
            $this->SendDebug('Schedule', 'Zeitplan verworfen: Protokoll-0-Feld dd muss sieben Tageswerte enthalten.', 0);
            return false;
        }
        foreach ($schedule['d'] as $entry) {
            if (!is_array($entry) || count($entry) < 3 || !is_string($entry[0] ?? null) || !is_numeric($entry[1] ?? null) || !is_numeric($entry[2] ?? null)) {
                $this->SendDebug('Schedule', 'Zeitplan verworfen: Ein Tageswert hat nicht das erwartete Format.', 0);
                return false;
            }
        }
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $device) {
            if (($device['serial_number'] ?? '') !== $serial) {
                continue;
            }
            if ((int) ($device['protocol'] ?? -1) !== 0 || !isset($device['mqtt_topics']['command_in'])) {
                $this->SendDebug('Schedule', 'Zeitplan verworfen: Gerät oder MQTT-Thema unterstützt Protokoll 0 nicht.', 0);
                return false;
            }
            $payload = json_encode(['sc' => $schedule], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $this->publish((string) $device['mqtt_topics']['command_in'], $payload);
        }
        return false;
    }
    /** Set the protocol-0 rain delay in minutes, after validating model capability. */
    public function SetRainDelay(string $serial, int $minutes): bool
    {
        if ($minutes < 0 || $minutes > self::MAX_RAIN_DELAY_MINUTES) {
            return false;
        }
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $device) {
            if (($device['serial_number'] ?? '') !== $serial) {
                continue;
            }
            if ((int) ($device['protocol'] ?? -1) !== 0
                || !in_array('rain_delay', $device['capabilities'] ?? [], true)
                || empty($device['online'])
                || empty($device['mqtt_topics']['command_in'])) {
                return false;
            }
            return $this->publish(
                (string) $device['mqtt_topics']['command_in'],
                json_encode(['rd' => $minutes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            );
        }
        return false;
    }
    /** Update the Worx Cloud automatic-schedule flag and refresh the reported device state. */
    public function SetAutoSchedule(string $serial, bool $enabled): bool
    {
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $device) {
            if (($device['serial_number'] ?? '') !== $serial) {
                continue;
            }
            if (!array_key_exists('auto_schedule', $device) || !is_bool($device['auto_schedule']) || empty($device['online'])) {
                return false;
            }
            $token = $this->getToken();
            if ($token === '') {
                return false;
            }
            $response = $this->request(
                'PUT',
                '/api/v2/product-items/' . rawurlencode($serial),
                ['auto_schedule' => $enabled],
                $token
            );
            if (!is_array($response)) {
                return false;
            }
            // Fetch the authoritative cloud value and let the Mower confirm it.
            $this->Poll();
            return true;
        }
        return false;
    }

    /** Update the cloud auto-upgrade preference; this does not start a firmware upgrade. */
    public function SetFirmwareAutoUpgrade(string $serial, bool $enabled): bool
    {
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $device) {
            if (($device['serial_number'] ?? '') !== $serial) {
                continue;
            }
            if (!in_array('ota_upgrade', $device['capabilities'] ?? [], true)
                || !array_key_exists('firmware_auto_upgrade', $device)
                || !is_bool($device['firmware_auto_upgrade'])
                || empty($device['online'])) {
                return false;
            }
            $token = $this->getToken();
            if ($token === '') {
                return false;
            }
            $response = $this->request(
                'PUT',
                '/api/v2/product-items/' . rawurlencode($serial),
                ['firmware_auto_upgrade' => $enabled],
                $token
            );
            if (!is_array($response)) {
                return false;
            }
            // The cloud value received after Poll, not the successful PUT, is the confirmation.
            $this->Poll();
            return true;
        }
        return false;
    }

    /** Lock or unlock a supported protocol-0 mower. */
    public function SetLock(string $serial, bool $locked): bool
    {
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $device) {
            if (($device['serial_number'] ?? '') !== $serial) {
                continue;
            }
            if ((int) ($device['protocol'] ?? -1) !== 0
                || !in_array('lock', $device['capabilities'] ?? [], true)
                || empty($device['online'])
                || empty($device['mqtt_topics']['command_in'])) {
                return false;
            }
            return $this->publish(
                (string) $device['mqtt_topics']['command_in'],
                json_encode(['cmd' => $locked ? 5 : 6], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            );
        }
        return false;
    }
    /** Nachrichten vom MQTT Client — der Mäher meldet seinen Zustand. */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $topic = $data['Topic'] ?? '';
        $payload = $data['Payload'] ?? '';
        if ($topic === '' || $payload === '') {
            return '';
        }
        $this->SendDebug('Empfangen', 'MQTT-Status empfangen; Topic und Nutzdaten werden nicht protokolliert.', 0);

        $status = json_decode($payload, true);
        if (!is_array($status)) {
            return '';
        }

        // Zugehörigen Mäher über das Topic finden und den Zwischenstand nachziehen,
        // damit REST- und MQTT-Weg dieselbe Wahrheit liefern.
        $devices = json_decode($this->ReadAttributeString('Devices'), true) ?: [];
        foreach ($devices as $i => $d) {
            if (($d['mqtt_topics']['command_out'] ?? '') !== $topic) {
                continue;
            }
            $devices[$i]['last_status']['payload'] = $status;
            $this->WriteAttributeString('Devices', json_encode($devices));
            $this->SendDataToChildren(json_encode([
                'DataID' => self::IF_MOWER,
                'Serial' => $d['serial_number'],
                'Device' => $devices[$i],
            ]));
            break;
        }
        return '';
    }

    /** Anfragen der Kind-Instanzen (Konfigurator und Mäher). */
    public function ForwardData($JSONString)
    {
        $data = json_decode($JSONString, true);

        switch ($data['Command'] ?? '') {
            case 'ListDevices':
                $token = $this->getToken();
                if ($token !== '') {
                    $devices = $this->request('GET', '/api/v2/product-items?status=1', null, $token);
                    if (is_array($devices)) {
                        $this->WriteAttributeString('Devices', json_encode($devices));
                    }
                }
                return $this->ReadAttributeString('Devices');

            case 'GetDevice':
                foreach (json_decode($this->ReadAttributeString('Devices'), true) ?: [] as $device) {
                    if (($device['serial_number'] ?? '') === ($data['Serial'] ?? '')) {
                        return json_encode($device);
                    }
                }
                return json_encode(null);

            case 'Command':
                return json_encode($this->SendCommand((string) ($data['Serial'] ?? ''), (string) ($data['Payload'] ?? '')));

            case 'SetSchedule':
                return json_encode($this->SetSchedule(
                    (string) ($data['Serial'] ?? ''),
                    (string) ($data['Schedule'] ?? '')
                ));

            case 'SetRainDelay':
                return json_encode($this->SetRainDelay(
                    (string) ($data['Serial'] ?? ''),
                    (int) ($data['Minutes'] ?? -1)
                ));
            case 'SetAutoSchedule':
                return json_encode($this->SetAutoSchedule(
                    (string) ($data['Serial'] ?? ''),
                    (bool) ($data['Enabled'] ?? false)
                ));

            case 'SetFirmwareAutoUpgrade':
                return json_encode($this->SetFirmwareAutoUpgrade(
                    (string) ($data['Serial'] ?? ''),
                    (bool) ($data['Enabled'] ?? false)
                ));

            case 'SetLock':
                return json_encode($this->SetLock(
                    (string) ($data['Serial'] ?? ''),
                    (bool) ($data['Locked'] ?? false)
                ));

            case 'Poll':
                return json_encode($this->Poll());
        }
        return json_encode(false);
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'Select', 'name' => 'Cloud', 'caption' => 'Cloud', 'options' => [
                    ['caption' => 'Worx Landroid', 'value' => 'worx'],
                ]],
                ['type' => 'ValidationTextBox', 'name' => 'Email', 'caption' => 'E-Mail'],
                ['type' => 'PasswordTextBox', 'name' => 'Password', 'caption' => 'Passwort'],
                ['type' => 'CheckBox', 'name' => 'UseMQTT', 'caption' => 'Echtzeit und Steuerung über MQTT'],
                ['type' => 'NumberSpinner', 'name' => 'Interval', 'caption' => 'Abfrageintervall (Sekunden)', 'minimum' => 30],
                ['type' => 'Label', 'caption' => 'Mit MQTT genügt ein großes Abfrageintervall — der Zustand kommt dann von selbst. Worx begrenzt auf 2880 Abfragen pro Tag.'],
            ],
            'actions' => [
                ['type' => 'Button', 'caption' => 'Jetzt abfragen', 'onClick' => 'WORX_Poll($id);'],
                ['type' => 'Button', 'caption' => 'Verbindung erneuern', 'onClick' => 'WORX_RefreshTransport($id);'],
            ],
            'status' => [
                ['code' => 102, 'icon' => 'active',   'caption' => 'Verbunden'],
                ['code' => 104, 'icon' => 'inactive', 'caption' => 'Zugangsdaten fehlen'],
                ['code' => 201, 'icon' => 'error',    'caption' => 'Anmeldung fehlgeschlagen — E-Mail/Passwort prüfen'],
                ['code' => 202, 'icon' => 'error',    'caption' => 'Cloud nicht erreichbar'],
                ['code' => 203, 'icon' => 'error',    'caption' => 'Nicht unterstützter Cloud-Eintrag aus der Altinstallation — Worx auswählen'],
            ],
        ]);
    }

    /**
     * Stellt sicher, dass WorxCloud über einen MQTT Client und einen WebSocket
     * verbunden ist. Symcon kann beim Anlegen zunächst einen Client Socket
     * erzeugen; dieser wird hier durch den Worx-WebSocket ersetzt.
     */
    private function ensureParent(): void
    {
        $mqtt = $this->getParent();
        if ($mqtt === 0) {
            $mqtt = IPS_CreateInstance(self::GUID_MQTT);
            IPS_SetName($mqtt, 'Worx MQTT');
            IPS_ConnectInstance($this->InstanceID, $mqtt);
        }

        $connection = (int) (IPS_GetInstance($mqtt)['ConnectionID'] ?? 0);
        if ($connection !== 0) {
            $moduleID = IPS_GetInstance($connection)['ModuleInfo']['ModuleID'] ?? '';
            if ($moduleID === self::GUID_WS) {
                return;
            }
            IPS_DisconnectInstance($mqtt);
        }

        $ws = IPS_CreateInstance(self::GUID_WS);
        IPS_SetName($ws, 'Worx WebSocket');
        IPS_ConnectInstance($mqtt, $ws);
        $this->SendDebug('MQTT', "Worx-Transportkette angelegt: WS $ws → MQTT $mqtt", 0);
    }

    private function publish(string $topic, string $payload): bool
    {
        $parent = $this->getParent();
        // Nicht nur "verknüpft", sondern auch "verbunden" prüfen — sonst quittiert
        // Symcon jeden Sendeversuch während des Verbindungsaufbaus mit einer Warnung.
        if ($parent === 0 || IPS_GetInstance($parent)['InstanceStatus'] !== IS_ACTIVE) {
            $this->SendDebug('Publish', 'MQTT-Verbindung nicht bereit — verworfen', 0);
            return false;
        }
        $this->SendDebug('Publish', 'MQTT-Nachricht gesendet; Topic und Nutzdaten werden nicht protokolliert.', 0);
        $this->SendDataToParent(json_encode([
            'DataID'           => self::IF_MQTT_TX,
            'PacketType'       => 3,   // PUBLISH
            'QualityOfService' => 0,
            'Retain'           => false,
            'Topic'            => $topic,
            'Payload'          => $payload,
        ]));
        return true;
    }

    // ── Hilfsmittel ───────────────────────────────────────────────────────────

    private function getParent(): int
    {
        return (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
    }

    /** Schreibt Eigenschaften nur bei echter Abweichung — ein ApplyChanges auf
     *  den WS Client kappt die Verbindung, das soll nicht bei jedem Lauf passieren. */
    private function setIfChanged(int $instance, array $props): void
    {
        $changed = false;
        foreach ($props as $key => $value) {
            if (IPS_GetProperty($instance, $key) !== $value) {
                IPS_SetProperty($instance, $key, $value);
                $changed = true;
            }
        }
        if ($changed) {
            IPS_ApplyChanges($instance);
        }
    }

    protected function getToken(): string
    {
        $token = $this->ReadAttributeString('AccessToken');
        if ($token !== '' && $this->ReadAttributeInteger('TokenExpires') > time() + 60) {
            return $token;
        }

        $refresh = $this->ReadAttributeString('RefreshToken');
        if ($refresh !== '') {
            $result = $this->authRequest([
                'client_id'     => self::CLIENT_ID,
                'refresh_token' => $refresh,
                'scope'         => '*',
                'grant_type'    => 'refresh_token',
            ]);
            if ($this->storeToken($result)) {
                return $this->ReadAttributeString('AccessToken');
            }
            $this->SendDebug('Auth', 'Erneuerung fehlgeschlagen — melde neu an', 0);
        }

        $result = $this->authRequest([
            'client_id'  => self::CLIENT_ID,
            'username'   => $this->ReadPropertyString('Email'),
            'password'   => $this->ReadPropertyString('Password'),
            'scope'      => '*',
            'grant_type' => 'password',
        ]);
        return $this->storeToken($result) ? $this->ReadAttributeString('AccessToken') : '';
    }

    private function storeToken($result): bool
    {
        if (!is_array($result) || !isset($result['access_token'])) {
            return false;
        }
        $this->WriteAttributeString('AccessToken', $result['access_token']);
        $this->WriteAttributeInteger('TokenExpires', time() + (int) ($result['expires_in'] ?? 3600));
        if (isset($result['refresh_token'])) {
            $this->WriteAttributeString('RefreshToken', $result['refresh_token']);
        }
        return true;
    }

    private function authRequest(array $body)
    {
        $ch = curl_init(self::AUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            // Passwort niemals mitloggen — nur der Grund ist interessant
            $this->SendDebug('Auth', sprintf('Anmeldung fehlgeschlagen (HTTP %d).', $code), 0);
            return null;
        }
        return json_decode((string) $response, true);
    }

    protected function request(string $method, string $path, $body, string $token)
    {
        if ($this->ReadPropertyString('Cloud') !== 'worx') {
            $this->SendDebug('API', 'Anfrage abgelehnt: nur Worx wird unterstützt.', 0);
            return null;
        }
        $ch = curl_init('https://' . self::CLOUD_API . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_merge($this->headers(), ['authorization: Bearer ' . $token]),
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('API', sprintf('%s %s → HTTP %d', $method, $this->sanitizeApiPathForDebug($path), $code), 0);
        if ($code === 401) {
            $this->WriteAttributeInteger('TokenExpires', 0);
            return null;
        }
        if ($code < 200 || $code >= 300) {
            return null;
        }
        if ($body !== null && $response === '') {
            return [];
        }
        return json_decode((string) $response, true);
    }

    /** Mask device-specific path segments before writing request paths to debug logs. */
    private function sanitizeApiPathForDebug(string $path): string
    {
        $sanitized = preg_replace('~/api/v2/product-items/[^/?]+~', '/api/v2/product-items/{serial}', $path);
        return $sanitized ?? '[path omitted]';
    }

    private function headers(): array
    {
        return [
            'accept: application/json',
            'content-type: application/json',
            'user-agent: IPSymconWorx/2.0',
            'accept-language: de-de',
        ];
    }
}
