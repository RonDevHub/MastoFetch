# ⚓ MastoFetch

MastoFetch ist ein extrem ressourcensparendes, datenschutzkonformes und sicheres Vanilla-PHP Widget, um Feeds von verschiedenen Mastodon-Accounts datenbanklos aggregiert und chronologisch als Masonry-Grid (Kacheln) auf jeder beliebigen Website einzubinden.

## Features

*   **Multi-Account-fähig:** Kombiniert Beiträge mehrerer Accounts von unterschiedlichen Instanzen in einem Widget.
*   **Strikte Chronologie:** Beiträge fließen lückenlos von links nach rechts (Masonry) sortiert nach Datum.
*   **Traffic-basiertes Caching:** Kein System-Cronjob nötig. Läuft komplett autark über asynchrones JavaScript-Hintergrund-Polling.
*   **100% DSGVO-konform (Datenschutz-Schild):** Alle Avatare und Beitragsbilder werden vom Server lokal gecached und über einen internen Proxy ausgeliefert. Keine IP-Weitergabe der Endnutzer an Mastodon-Instanzen.
*   **Sicherheits-Architektur:** Volle Isolation über `public/` Webroot. Konfigurationsdateien und Cache liegen außerhalb des Webzugriffs.

## Installation (Klassisches Hosting)

1. Kopiere den gesamten Ordner auf deinen Server.
2. Setze das Webroot deines Webservers strikt auf das Verzeichnis `public/`.
3. Stelle sicher, dass das Verzeichnis `storage/` für den Webserver beschreibbar ist (`chmod 755`).
4. Kopiere die `.env.example` zu `.env` und trage deine Mastodon API-Tokens ein.
5. Konfiguriere deine Accounts und Widgets in `config/accounts.json`.

## Installation (Docker & Portainer)

Nutze die mitgelieferte `docker-compose.yml`:

```bash
docker-compose up -d
```

Das Dashboard ist anschließend über http://localhost:8080/index.php erreichbar.

