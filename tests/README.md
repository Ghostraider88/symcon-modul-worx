# Tests des Worx-Moduls

Die GitHub-Actions führen bei Push und Pull Request beide Prüfungen aus:

- **Check Style** prüft PHP-, JSON- und Repository-Formatierung.
- **Run Tests** initialisiert `tests/stubs` aus `SymconStubs` und führt die PHPUnit-Suite aus.

`WorxScheduleCodecTest` prüft den Worx-Protokoll-0-Zeitplan, die Abbildung ins native Symcon-Wochenplanereignis, Wertegrenzen und den Erhalt unbekannter Cloud-Felder. `ValidationTest` prüft Library- und Modulmanifestdateien.

Die Testsuite ersetzt keine Prüfung in einer Symcon-Laufzeit und keinen Geräteechotest. Die dafür nötigen Schritte und der aktuelle Abnahmestand stehen in [`docs/RELEASE_CHECKLIST.md`](../docs/RELEASE_CHECKLIST.md).

Bei eingerichtetem lokalen Entwicklungssetup kann PHPUnit mit `vendor/bin/phpunit` ausgeführt werden.
