# Changelog

## 1.0.1 – 2026-10-29

- Homepage, Autor und Support-Links in composer.json (Anzeige auf Packagist)

## 1.0.0 – 2026-10-29

- Schnittstelle api 1: `GET /`, `PUT|PATCH|DELETE /events/{id}`, `PUT /media/{id}` (Vertrag V1–V7)
- Backend „Jetro“: Verbindungen, Token erzeugen und widerrufen, Standard-Kalender, Kalender-Zuordnung, Protokoll
- Hinweis „Abgesagt“ in Terminliste und Leseansicht
- Termin-Vorlagen für die Kern-Terminliste: Slider, große Bildkarten, kompakte Karten, Liste mit Vorschaubild; Bild aus Jetro oder Kalenderblatt, Farben über CSS-Variablen
- Feld „Darstellung“ an der Terminliste stellt die passenden Vorlagen und die Bildgröße ein
- Ganztägige Termine enden wie im Kern um 23:59:59 des letzten Tages
- Längen nach dem Kodieren geprüft (422 statt 500), Alias auf 255 Zeichen begrenzt
- Bilder bis 6000 × 6000 Pixel, Protokoll 50 Einträge je Verbindung und 10 abgewiesene
- Token-Felder nicht versioniert, Contao-Framework vor jeder Anfrage initialisiert
- Tests gegen die Vertrags-Fixtures aus Jetro und Integrationstest mit echtem Contao und MySQL
- Bilder gehören keiner Verbindung; neue Verbindungen nutzen vorhandene Bilder weiter
- Kodierung über Contaos Input::encodeInput (encodeAll), auch für ##Simple-Tokens##
- Kalenderauswahl im Backend nur mit Kalenderrechten des Benutzers
- Zeile „Termine aus Jetro“ unter Terminlisten mit Jetro-Terminen, je Verbindung abschaltbar
