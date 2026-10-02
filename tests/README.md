# Tests des Worx-Moduls

Die GitHub-Actions führen bei Push und Pull Request beide Prüfungen aus:

- **Check Style** prüft PHP-, JSON- und Repository-Formatierung.
- **Run Tests** initialisiert `tests/stubs` aus `SymconStubs` und führt die PHPUnit-Suite aus.

`ForkIdentityTest` sichert die stabilen, eigenständigen Library- und Modul-GUIDs gegenüber dem Catomic-Basiscommit ab. `WorxScheduleCodecTest` prüft den Worx-Protokoll-0-Zeitplan, die Abbildung ins native Symcon-Wochenplanereignis, Wertegrenzen und den Erhalt unbekannter Cloud-Felder. `ValidationTest` prüft Library- und Modulmanifestdateien. `WorxCloudSecurityTest` prüft unter anderem maskierte API-Debugpfade, simulierte Authentifizierungsfehler, blockierte MQTT-Sendungen bei fehlendem oder inaktivem Parent sowie ein leeres Geräteinventar. Diese Tests verwenden lokale Testdoubles und ersetzen keine Prüfung gegen die Worx Cloud oder einen echten MQTT-Broker. `WorxMowerProfileTest` prüft unter anderem die read-only Berechnung des nächsten Planstarts über Tages- und Wochenwechsel.

Die Testsuite ersetzt keine Prüfung in einer Symcon-Laufzeit und keinen Geräteechotest. Die dafür nötigen Schritte und der aktuelle Abnahmestand stehen in [`docs/RELEASE_CHECKLIST.md`](../docs/RELEASE_CHECKLIST.md).

Bei eingerichtetem lokalen Entwicklungssetup kann PHPUnit mit `vendor/bin/phpunit` ausgeführt werden.
