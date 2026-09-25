<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ForkIdentityTest extends TestCase
{
    public function testForkGuidsRemainStableAndIndependentFromCatomic(): void
    {
        $forkGuids = [
            'library.json' => '{D41E48F5-1BCC-4527-9C46-AB3113FCC1D7}',
            'WorxCloud/module.json' => '{2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}',
            'WorxConfigurator/module.json' => '{A8373ED9-7396-421E-A78E-4904A6AC4657}',
            'WorxMower/module.json' => '{39CA7807-D252-4375-8D05-1C5F918552C0}',
        ];
        $catomicGuids = [
            '{C078EFF0-18B3-4CC4-8027-CB6558E546A2}',
            '{09B3E1A6-0D6F-4452-B1A2-5D4C9794BF34}',
            '{A6ECA3F5-27C6-43CB-899D-34EFADB52089}',
            '{474B5876-AE7B-45B8-A774-A758AA741D49}',
        ];
        $observedGuids = [];
        $manifests = [];

        foreach ($forkGuids as $manifestPath => $expectedGuid) {
            $manifestFile = __DIR__ . '/../' . $manifestPath;
            $manifest = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
            $observedGuid = strtoupper((string) ($manifest['id'] ?? ''));

            self::assertSame($expectedGuid, $observedGuid, $manifestPath . ' GUID changed unexpectedly');
            self::assertNotContains($observedGuid, $catomicGuids, $manifestPath . ' reuses a Catomic GUID');
            $observedGuids[] = $observedGuid;
            if (str_ends_with($manifestPath, '/module.json')) {
                $manifests[basename(dirname($manifestPath))] = $manifest;
            }
        }

        self::assertCount(count($forkGuids), array_unique($observedGuids), 'Fork manifest GUIDs must be unique');

        $forkInterfaceGuids = [
            'WorxCloud' => ['{557B9D5F-D12D-4E44-87A7-05A5EC0F4F07}', '{F925090C-4AED-407D-8B80-F1730A55717E}'],
            'WorxConfigurator' => ['{F925090C-4AED-407D-8B80-F1730A55717E}'],
            'WorxMower' => ['{557B9D5F-D12D-4E44-87A7-05A5EC0F4F07}'],
        ];
        $catomicInterfaceGuids = [
            '{01E8C2C0-BB9C-4615-AD25-A78E12D842F0}',
            '{5279818E-0317-46AB-A451-0683BEBC6E90}',
        ];
        $observedInterfaces = [];

        foreach ($forkInterfaceGuids as $module => $expectedInterfaces) {
            $manifest = $manifests[$module];
            $interfaces = array_values(array_intersect(
                array_merge($manifest['parentRequirements'] ?? [], $manifest['childRequirements'] ?? []),
                $expectedInterfaces
            ));
            sort($interfaces);
            sort($expectedInterfaces);

            self::assertSame($expectedInterfaces, $interfaces, $module . ' interface GUIDs changed unexpectedly');
            self::assertEmpty(array_intersect($interfaces, $catomicInterfaceGuids), $module . ' reuses a Catomic interface GUID');
            $observedInterfaces = array_merge($observedInterfaces, $interfaces);
        }

        self::assertSame($forkInterfaceGuids['WorxCloud'], array_values(array_unique($observedInterfaces)));
    }
}