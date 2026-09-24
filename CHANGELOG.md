# Änderungsprotokoll

## Unveröffentlicht

- Catomic-Module als Codebasis in das Worx-Zielrepository übernommen; eigenständige Fork-GUIDs erstellt, Worx-Funktionspräfixe und bestehende Variablen-Idents beibehalten.
- Repository auf Worx Landroid begrenzt; Kress-, Landxcape- und Ferrex-Endpunkte entfernt.
- Worx-Feature-Matrix, Schnittstellen, Updatepfad und WR105SI.1-App-Belege dokumentiert.
- Protokoll-0-Zeitplan-Codec validiert Symcon-Ereigniswerte und erhält unbekannte Gerätefelder.
- Natives Symcon-Wochenplanereignis als einziger Editor; Ereignisänderungen werden direkt per MQTT gesendet und per Mäher-Rückmeldung geprüft. Cloud-Änderungen aktualisieren dasselbe Ereignis. Live-Rundlauf am WR105SI.1 steht noch aus.
- Englische Lokalisierungen für die drei Modulkonfigurationsformulare ergänzt; Geräte-Capabilities und offene Steuerwege in der Feature-Matrix einzeln dokumentiert.
- PHPUnit-, Style- und JSON-Prüfungen ausgeführt; falsche Wochentag-Indizes in zwei Tests korrigiert und Formatierung an die Symcon-Style-Regeln angepasst.
