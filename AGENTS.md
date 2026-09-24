# Worx-Modul für IP-Symcon

## Projektregeln

- Catomic bleibt die Codebasis. Library-, Modul- und Worx-interne DataFlow-GUIDs sind für diesen Fork neu und eigenständig. Funktionspräfixe und veröffentlichte Variablen-Idents bleiben stabil.
- Dieses Repository unterstützt Worx Landroid. Kress, Landxcape und Ferrex gehören nicht zum Produktumfang.
- Das Repository bleibt unter MIT. GPL-Quellcode wird nicht übernommen; GPL-Projekte dürfen als Recherchequelle dienen.
- Gerätebedienung wird nur angezeigt, wenn Modell, Firmware oder empfangene Capability-/Statusdaten sie belegen.
- Gesendete Befehle und zurückgelesene Gerätezustände bleiben getrennt.
- Zugangsdaten, Token, Seriennummern, UUIDs, MAC-Adressen, Standorte und vollständige Cloud-Antworten dürfen nicht in veröffentlichte Dateien oder Logs.
- ApplyChanges() darf keinen Mäh- oder Zeitplanbefehl senden. Wiederholte Konfigurationsanwendung darf keine Objektduplikate erzeugen.
- Symcon-Kompatibilität, Modulstruktur, Lokalisierungen, Manifestdateien und Qualitätsprüfungen müssen konsistent bleiben.

## Modulidentitäten

- Library {D41E48F5-1BCC-4527-9C46-AB3113FCC1D7}
- Worx Cloud {2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}, Prefix WORX
- Worx Configurator {A8373ED9-7396-421E-A78E-4904A6AC4657}, Prefix WORXCONF
- Worx Mower {39CA7807-D252-4375-8D05-1C5F918552C0}, Prefix WORXMOWER

## Entwicklung und Abnahme

- Nur allgemeine, nicht markengebundene CI-/Style-Bausteine aus dem Symcon-Template übernehmen.
- Modulordnername, PHP-Klassenname, module.json und Funktionspräfixe konsistent halten.
- Vor dem Release JSON, PHP-Syntax, Installations-/Updatepfad, wiederholtes ApplyChanges und Geräteantworten prüfen.
- Tests am echten Mäher erst lesend beginnen. Ein konkreter Schreibtest muss als vorher sichtbare Änderung erfolgen und anschließend am zurückgemeldeten Zustand bestätigt werden.
- Keine echte Geräteaktion während statischer Entwicklung oder Syntaxprüfung ausführen.
