<?php

declare(strict_types=1);

/**
 * Worx Configurator — listet die Mäher des Kontos auf und legt sie per Klick an.
 */
class WorxConfigurator extends IPSModule
{
    private const MOWER_GUID = '{39CA7807-D252-4375-8D05-1C5F918552C0}';

    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $values = [];
        foreach ($this->getDevices() as $device) {
            $serial = $device['serial_number'] ?? '';
            if ($serial === '') {
                continue;
            }
            $values[] = [
                'instanceID'  => $this->findInstance($serial),
                'name'        => $device['name'] ?? $serial,
                'serial'      => $serial,
                'model'       => $device['name'] ?? '',
                'firmware'    => (string) ($device['firmware_version'] ?? ''),
                'online'      => ($device['online'] ?? false) ? 'ja' : 'nein',
                'create'      => [
                    'moduleID'      => self::MOWER_GUID,
                    'name'          => $device['name'] ?? $serial,
                    'configuration' => ['Serial' => $serial],
                ],
            ];
        }

        return json_encode([
            'actions' => [
                [
                    'type'    => 'Configurator',
                    'name'    => 'Mowers',
                    'caption' => 'Mähroboter',
                    'rowCount' => 10,
                    'add'     => false,
                    'delete'  => true,
                    'columns' => [
                        ['caption' => 'Name',         'name' => 'name',     'width' => 'auto'],
                        ['caption' => 'Seriennummer', 'name' => 'serial',   'width' => '220px'],
                        ['caption' => 'Firmware',     'name' => 'firmware', 'width' => '120px'],
                        ['caption' => 'Online',       'name' => 'online',   'width' => '80px'],
                    ],
                    'values' => $values,
                ],
            ],
            'status' => [
                ['code' => 102, 'icon' => 'active', 'caption' => 'Aktiv'],
            ],
        ]);
    }

    private function getDevices(): array
    {
        $result = $this->SendDataToParent(json_encode([
            'DataID'  => '{F925090C-4AED-407D-8B80-F1730A55717E}',
            'Command' => 'ListDevices',
        ]));
        $devices = json_decode((string) $result, true);
        return is_array($devices) ? $devices : [];
    }

    /** Bereits angelegte Instanz zu einer Seriennummer, sonst 0. */
    private function findInstance(string $serial): int
    {
        foreach (IPS_GetInstanceListByModuleID(self::MOWER_GUID) as $id) {
            if (IPS_GetProperty($id, 'Serial') === $serial) {
                return $id;
            }
        }
        return 0;
    }
}
