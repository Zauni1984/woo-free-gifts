# Woo Free Gifts Premium

Premium-Gratisgeschenk-Engine für WooCommerce. Geschenke werden automatisch in den Warenkorb gelegt, sobald eine Regel greift – und wieder entfernt, wenn sie nicht mehr greift.

## Funktionen

### Regelbedingungen (frei kombinierbar, alle müssen zutreffen)

| Bedingung | Beispiel |
|---|---|
| Mindest-/Höchst-Warenkorbwert | ab 50 € ein Free Seed, ab 100 € ein Buch |
| Pflichtprodukte (alle / eines davon, Mindestmenge) | „Kaufe B und C, erhalte X“ |
| Pflichtkategorien (inkl. Unterkategorien) | ein Produkt aus „Seeds“ im Warenkorb |
| Bundle-Produkt im Warenkorb | WooCommerce Product Bundles, WPC, YITH, Composite, Grouped |
| Mindestanzahl Artikel | ab 3 Artikeln |
| Kundenrollen, nur eingeloggt, einmal pro Kundenkonto | Stammkunden-Geschenk |
| Zeitraum | Aktion vom 1.–24.12. |

Produkte **innerhalb** von Bundles zählen für Produkt- und Kategoriebedingungen mit (abschaltbar).

### Geschenke

- Jedes Katalogprodukt oder jede Variante, Lagerbestand wird beachtet.
- **Custom-Geschenke**, die nicht im Shop gelistet sind (Name, Bild, Beschreibung, Gewicht, virtuell). Sie werden als versteckte Produkte gespeichert, sind aus Shop, Suche, Feeds, Sitemaps und „Ähnliche Produkte“ ausgeschlossen und lassen sich nicht einzeln kaufen.
- Mehrere Geschenke pro Regel: alle automatisch hinzufügen **oder** Kunde wählt eines im Warenkorb.
- Menge pro Geschenk.
- Stacking: alle greifenden Regeln liefern ihre Geschenke, oder nur die höchste Regel.
- **Bestand**: Custom-Geschenke bekommen ein Bestandsfeld (leer = unbegrenzt), das die WooCommerce-Lagerverwaltung am versteckten Produkt aktiviert. Abzug pro Bestellung, Rückbuchung bei Storno, bei null wird das Geschenk übersprungen. Katalog-Geschenke nutzen ihren normalen Lagerbestand.
- **Budget pro Regel**: „Max. Bestellungen“, danach schaltet sich die Regel automatisch ab. Aus Bestand und Budget entsteht die „Nur noch X Stück“-Zeile im Fortschrittsbalken und Popup (Schwelle einstellbar). Im Admin warnt eine Meldung, sobald ein Geschenk knapp wird.

### Kundenerlebnis

- Fortschrittsbalken in Warenkorb, Kasse und Mini-Cart („Noch 12,50 € bis …“).
- Hinweis auf Einzelproduktseiten.
- Geschenk-Badge und „Gratis“-Preis (Originalpreis durchgestrichen) in Warenkorb, Kasse, E-Mails und Bestellungen.
- Menge gesperrt, Gutscheine wirken nie auf Geschenke, optional „Kunde darf Geschenk entfernen“.
- **Popup**, das einmal erscheint (pro Session / alle X Tage / einmalig / immer) auf Einzelprodukt- und Archivseiten. Cache-freundlich, barrierearm, per ESC schließbar.
- Shortcodes `[wfg_progress]` und `[wfg_gift_list]`.
- Klassischer und Block-Warenkorb/-Kasse (Menge via Store API gesperrt).

### Glücksrad 🎡

- Popup-Glücksrad im **420/Kiffer-Style** (dunkles Grün, Neon-Glow, Hanfblatt in der Mitte, ziehender Rauch, lockere Sprüche) oder klassisch weiß, Akzentfarbe frei wählbar.
- **Eine Drehung pro Tag** (Sperrzeit in Stunden einstellbar). Die Sperre gilt serverseitig über Kundenkonto, WooCommerce-Session, signiertes Cookie, gehashte IP und gehashte E-Mail – der Browser animiert nur das Ergebnis, der Gewinn wird auf dem Server ausgewürfelt.
- 2–12 Segmente mit Farbe, Beschriftung und Gewicht (Wahrscheinlichkeit). Segment-Typen: **Gutschein** (Prozent oder fester Betrag, automatisch erzeugte einmalige Codes mit Präfix, Ablauf, Mindestbestellwert, optional an E-Mail gebunden, auf Wunsch sofort angewendet), **Geschenk** (Produkt oder Custom-Geschenk, landet mit der nächsten Bestellung gratis im Warenkorb) oder **Niete**.
- Optionale E-Mail-Erfassung mit Zustimmungs-Checkbox, Anzeige auf Produkt-, Archiv-, Warenkorb- und/oder allen anderen Seiten (nie an der Kasse).
- Statistik mit Drehungen, Gewinnen und Spin-Log unter WooCommerce → Free Gifts → Statistik.

### Sicherheit & Stabilität

- Versionsprüfung für PHP und WooCommerce, ohne WooCommerce bleibt das Plugin inaktiv (kein Fatal Error).
- Jede Warenkorb-Synchronisation läuft in `try/catch` und loggt Fehler nach WooCommerce → Status → Logs.
- Nonces und Capability-Checks für alle Admin-Aktionen und AJAX-Calls, alle Eingaben werden bereinigt, alle Ausgaben escaped.
- HPOS-kompatibel.
- Bestellpositionen tragen die Geschenkregel als Meta, Statistik pro Regel.
- Daten werden bei der Deinstallation nur gelöscht, wenn das in den Einstellungen aktiviert ist.

## Installation

1. Ordner `woo-free-gifts` nach `/wp-content/plugins/` laden oder das ZIP über Plugins → Installieren hochladen.
2. Plugin aktivieren (WooCommerce muss aktiv sein).
3. WooCommerce → Free Gifts → erste Regel anlegen.

## Beispiel-Setup

1. **Regel „Free Seed ab 50 €“**: Mindest-Warenkorbwert 50, Geschenk = Custom-Geschenk „Free Seed“ mit Bild.
2. **Regel „Buch ab 100 €“**: Mindest-Warenkorbwert 100, Geschenk = Katalogprodukt „Grow-Buch“.
3. Stacking auf „alle“ lassen → ein 120 €-Warenkorb bekommt Seed **und** Buch. Auf „höchste“ stellen → nur das Buch.
4. **Regel „B + C = X“**: Pflichtprodukte B und C (alle), Geschenk X.
5. Popup aktivieren, Häufigkeit „einmal pro Session“, Angebote werden automatisch aufgelistet.

## Versionen & Updates

- Die Versionsnummer steht im Plugin-Header von `woo-free-gifts.php` (`Version:`) und in `readme.txt` (`Stable tag`). Änderungen stehen in `CHANGELOG.md`. Es gilt Semantic Versioning: neue Funktionen erhöhen die mittlere Zahl, Fehlerbehebungen die letzte.
- **Automatische Updates**: Das Plugin trägt `Update URI: https://github.com/Zauni1984/woo-free-gifts` und prüft die GitHub-Releases dieses Repos. Ein neueres Release erscheint in WordPress unter Plugins als normales Update mit „Details anzeigen“ (Changelog aus den Release-Notes) und lässt sich per Klick installieren. Unter WooCommerce → Free Gifts → Einstellungen → Updates gibt es „Jetzt nach Updates suchen“.
- **Privates Repository**: Dann braucht jeder Shop ein GitHub-Token (Fine-grained, Lesezugriff auf Contents), eingetragen in den Einstellungen oder als `WFG_GITHUB_TOKEN` in der `wp-config.php`. Bei einem öffentlichen Repo ist kein Token nötig.
- **Release veröffentlichen** (nach dem Merge in `main`):

  ```bash
  # 1. Version in woo-free-gifts.php, readme.txt und CHANGELOG.md erhöhen, committen
  # 2. Tag setzen und pushen
  git tag v1.2.0
  git push origin v1.2.0
  ```

  Der Workflow `.github/workflows/release.yml` prüft, dass Tag und Header-Version übereinstimmen, lässt Lint und Tests laufen, baut `woo-free-gifts-1.2.0.zip` und legt ein GitHub-Release mit dem Changelog-Abschnitt an. Danach sehen alle Shops das Update.
- **ZIP manuell bauen**: `bin/build-zip.sh` erzeugt `woo-free-gifts-<version>.zip`. Im ZIP heißt der Ordner immer `woo-free-gifts`, damit WordPress das Plugin an Ort und Stelle aktualisiert.

## Entwickler

### Hooks

| Hook | Typ | Zweck |
|---|---|---|
| `wfg_active_rules` | Filter | Aktive Regeln manipulieren |
| `wfg_basis_total` | Filter | Warenkorbwert für Schwellen ändern |
| `wfg_evaluate_rules` | Filter | Auswertungsergebnisse ändern |
| `wfg_rule_conditions_match` | Filter | Eigene Bedingungen ergänzen |
| `wfg_winning_rules` | Filter | Gewinnerregeln nach Stacking ändern |
| `wfg_available_gifts` | Filter | Verfügbare Geschenke einer Regel ändern |
| `wfg_gift_added` | Action | Nach dem Hinzufügen eines Geschenks |
| `wfg_order_recorded` | Action | Nach dem Erfassen der Geschenke einer Bestellung |
| `wfg_popup_should_render` | Filter | Popup-Anzeige steuern |
| `wfg_notify_on_unlock` | Filter | Erfolgsmeldung beim Freischalten unterdrücken |
| `wfg_wheel_should_render` | Filter | Anzeige des Glücksrads steuern |
| `wfg_wheel_next_allowed_spin` | Filter | Sperrzeit-Logik des Glücksrads erweitern |
| `wfg_wheel_spun` | Action | Nach einer Glücksrad-Drehung (z. B. Newsletter-Anbindung) |

### Templates

Alle Frontend-Templates liegen in `templates/` und können im Theme unter `woo-free-gifts/<name>.php` überschrieben werden.

### Code-Qualität

```bash
composer global require squizlabs/php_codesniffer wp-coding-standards/wpcs
phpcs --standard=phpcs.xml.dist
```

## Lizenz

GPL-2.0-or-later.

### Tests

Die Kernlogik (Regel-Engine, Warenkorb-Sync, Templates, Admin-Views) lässt sich ohne WordPress-Installation gegen Stubs testen:

```bash
php tests/run.php      # 95 Funktionstests (Schwellen, Stacking, B+C, Bundles, Auswahl, Entfernen, Lager, Rollen, Bestand/Budget, Glücksrad …)
php tests/render.php   # rendert alle Templates und Admin-Views und meldet PHP-Notices
```
