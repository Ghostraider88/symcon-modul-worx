# Änderungsprotokoll

## Unveröffentlicht

- Catomic-Module als Codebasis in das Worx-Zielrepository übernommen; eigenständige Fork-GUIDs erstellt, Worx-Funktionspräfixe und bestehende Variablen-Idents beibehalten.
- Repository auf Worx Landroid begrenzt; Kress-, Landxcape- und Ferrex-Endpunkte entfernt.
- Worx-Feature-Matrix, Schnittstellen, Updatepfad und WR105SI.1-App-Belege dokumentiert.
- Protokoll-0-Zeitplan-Codec validiert Symcon-Ereigniswerte und erhält unbekannte Gerätefelder.
- Natives Symcon-Wochenplanereignis als einziger Editor. Die manuelle Aktualisierung fragt Worx jetzt frisch per REST ab; Echo-Abgleich prüft die bearbeitbaren Slot-Felder und übernimmt abweichende Cloud-/App-Änderungen in dasselbe Ereignis. Docker-Abgleich bestätigt, dass der aus der Worx-App gemeldete Montagsslot (17:10, 110 Minuten) im nativen Symcon-Ereignis als 17:10–19:00 gespeichert ist; alle sieben Wochentage stimmen mit cfg.sc überein.
- Die live empfangene WR105SI.1-Regenverzögerung (`cfg.rd`, `rain_delay`) wird als read-only Minutenwert angezeigt; Steuerung folgt nach Bestätigung des Schreibwegs.
- Die live empfangene Zeiterweiterung (`cfg.sc.p`) wird bei passender Capability als read-only Prozentwert angezeigt; Steuerung folgt nach Beleg des WR105SI.1-Schreibwegs.
- Englische Lokalisierungen für die drei Modulkonfigurationsformulare ergänzt; Geräte-Capabilities und offene Steuerwege in der Feature-Matrix einzeln dokumentiert.
- PHPUnit-, Style- und JSON-Prüfungen ausgeführt; falsche Wochentag-Indizes in zwei Tests korrigiert und Formatierung an die Symcon-Style-Regeln angepasst.
