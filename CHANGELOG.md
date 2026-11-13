# Changelog

## 1.1.0 – 2026-11-13

- Contao 6 wird unterstützt: `composer.json` lässt `contao/core-bundle` und `contao/calendar-bundle` in `^5.3 || ^6.0` zu, Symfony zusätzlich in `^8.0`. Ein Codestand deckt Contao 5.3, 5.7 und 6.0 ab; PHP-Mindestversion bleibt 8.2 (Contao 6 selbst verlangt 8.4).
- Termin-Vorlagen liegen jetzt als Twig-Dateien (`.html.twig`) statt als `.html5` vor, weil Contao 6 keine `.html5`-Vorlagen mehr lädt. Contao 5.3 und 5.7 rendern dieselben Dateien; Markup, CSS-Klassen und CSS-Variablen bleiben unverändert. **Achtung bei eigenen Kopien:** Wer eine Jetro-Vorlage nach `templates/` kopiert und angepasst hat, muss die Kopie als `.html.twig` neu anlegen — eine kopierte `.html5`-Datei bindet `jetro_event_media.html5` ein, und die Datei gibt es nicht mehr.
- Texte aus Jetro: Contao 6 speichert Eingaben roh und kodiert erst bei der Ausgabe. Das Paket folgt dem und legt Titel, Ort und Adresse dort roh ab, statt sie doppelt kodiert anzuzeigen; `{{…}}` wird als `[{]…[}]` abgelegt und damit nie ausgeführt — genau so schreibt Contaos eigene Migration bisher kodierte Werte um. Bis Contao 5.7 bleibt es beim Kodieren wie bisher. Der Text im Feld „Teaser“ wird in beiden Fällen kodiert.
- Backend unter Contao 6: Beschriftungen der Listen kommen als `RecordLabel`, damit ihr HTML HTML bleibt; die Unterliste der Kalender-Zuordnungen nutzt `label_callback` statt des entfallenen `child_record_callback`.
- Integrationstest prüft zusätzlich die vier Darstellungen auf einer echten Seite (`frontend.php`) und das Backend „Jetro“ samt Token erzeugen (`backend.php`); die CI deckt Contao 5.3, 5.7 und 6.0 auf PHP 8.2 bis 8.5 ab.

## 1.0.2 – 2026-11-01

- Token erzeugen und widerrufen funktioniert unter Contao 5.7 (Backend-Formulare über Turbo): Nach dem Absenden leitet das Paket weiter (Post/Redirect/Get), das neue Token erscheint danach genau einmal. Bisher speicherte Contao 5.7 das Token, zeigte es aber nie an.
- Überschrift der Token-Seite zeigt den Namen der Aktion statt eines einzelnen Buchstabens

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
