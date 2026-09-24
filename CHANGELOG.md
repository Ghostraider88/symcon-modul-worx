# Änderungsprotokoll

## Unveröffentlicht

- Catomic-Module als Ausgangsbasis in das Worx-Zielrepository übernommen; vorhandene GUIDs, Präfixe und Variablen-Idents bleiben erhalten.
- Repository auf Worx Landroid begrenzt; Kress-, Landxcape- und Ferrex-Endpunkte entfernt.
- Worx-Feature-Matrix, Schnittstellen, Updatepfad und WR105SI.1-App-Belege dokumentiert.
- Protokoll-0-Zeitplan-Codec validiert Symcon-Ereigniswerte und erhält unbekannte Gerätefelder.
- Natives Symcon-Wochenplanereignis als einziger Editor; Ereignisänderungen werden direkt per MQTT gesendet und per Mäher-Rückmeldung geprüft. Cloud-Änderungen aktualisieren dasselbe Ereignis. Live-Rundlauf am WR105SI.1 steht noch aus.
- PHP-Unit-Tests für Zeitplanabbildung und Validierung ergänzt; lokale Ausführung wartet auf verfügbare PHP-Laufzeit.
