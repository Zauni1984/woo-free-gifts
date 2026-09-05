# Changelog

Alle nennenswerten Änderungen an Woo Free Gifts Premium. Versionen folgen [Semantic Versioning](https://semver.org/lang/de/):
neue Funktionen erhöhen die mittlere Zahl, Fehlerbehebungen die letzte.

## 1.2.0 – 2026-09-05

- Bestandsfeld für Custom-Geschenke (WooCommerce-Lagerverwaltung am versteckten Produkt, Abzug pro Bestellung, Rückbuchung bei Storno, Überspringen bei null).
- Budget pro Regel („Max. Bestellungen“), Regel schaltet sich bei Erreichen automatisch ab und entfernt ihr Geschenk aus dem Warenkorb.
- „Nur noch X Stück“-Zeile im Fortschrittsbalken und Popup (Schwelle einstellbar, Platzhalter `{left}`), Admin-Warnung bei knappem Bestand.
- Regeln mit ausverkauftem Geschenk werden nicht mehr als nächstes Ziel angeboten.
- Eingebaute Updates über GitHub-Releases (`Update URI`), manueller Update-Check, optionaler Token für private Repositories.

## 1.1.0 – 2026-09-04

- Glücksrad mit täglicher Drehung, Gutschein- und Geschenk-Gewinnen, 420/Kiffer- und klassischem Theme.
- Serverseitige Gewinnermittlung und Sperrzeit über Konto, Session, signiertes Cookie, gehashte IP und E-Mail.
- Glücksrad-Statistik und Spin-Log.

## 1.0.0 – 2026-09-04

- Erste Version: Geschenkregeln (Warenkorbwert, Pflichtprodukte, Kategorien, Bundles, Rollen, Zeitraum), Custom-Geschenke als versteckte Produkte, Geschenkauswahl, Fortschrittsbalken, Aktions-Popup, Statistik, deutsche Übersetzung.
