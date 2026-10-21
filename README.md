# Just Paste It

Entwicklungsumgebung für Symfony 8.1, PHP 8.4 und SQLite. Sie baut auf dem
vorhandenen Service `just-paste-it` und dem Dockerfile in `docker/` auf.
PHP und Composer müssen auf dem Host nicht installiert sein. Benötigt werden
Docker mit laufendem Daemon und Docker Compose ab Version 2.24.

## Start

```sh
docker compose build
mkdir -p var
docker compose run --rm just-paste-it php bin/console doctrine:migrations:migrate --no-interaction
docker compose up -d
docker compose exec just-paste-it php bin/console app:create-user
```

Danach unter http://localhost:8080/login mit dem angelegten Benutzer anmelden.
Der Benutzername und das Passwort werden interaktiv abgefragt.

Quellcode und Composer-Abhängigkeiten liegen im Image. Beim Build installiert
Composer die Versionen aus `composer.lock`, einschließlich der Entwicklungswerkzeuge.
`vendor/` ist weder in Git noch im Docker-Build-Kontext enthalten.
Composer-Skripte werden beim Build übersprungen, damit keine lokale Konfiguration
oder Datenbank benötigt wird. Den Symfony-Cache erstellt die Anwendung zur Laufzeit.
SQLite liegt unter `var/data.db` auf dem Host und bleibt beim Entfernen des
Containers erhalten. Nur `var/` wird als beschreibbarer Ordner eingebunden.
Der PHP-Entwicklungsserver ist ausschließlich für die lokale Entwicklung gedacht.

Die `.env` enthält lokale Standardwerte und wird ins Image kopiert; dort keine
Geheimnisse hinterlegen. Eigene Symfony-Einstellungen (z. B. `APP_SECRET` und
`DATABASE_URL`) gehören in die ignorierte `.env.local`. Compose übergibt diese
optional als Umgebungsvariablen; die Datei wird nicht ins Image kopiert.
Für ein Deployment einen eigenen zufälligen `APP_SECRET` setzen.

Compose verwendet standardmäßig UID/GID 1000 und Port 8080. Falls nötig vor den
Compose-Befehlen in der Shell setzen:

```sh
export LOCAL_UID="$(id -u)" LOCAL_GID="$(id -g)" HTTP_PORT=8081
```

## Entwicklung und Tests

Nach Änderungen am Quellcode oder an `composer.lock` das Image neu bauen und
den Container ersetzen:

```sh
docker compose up -d --build
```

Docker kann unveränderte Build-Schritte aus dem Cache verwenden. Für eine erneute
Installation auch ohne Dateiänderungen `docker compose build --no-cache` ausführen.

```sh
docker compose exec just-paste-it php bin/console cache:clear
docker compose exec just-paste-it php bin/console app:clean-pastes
docker compose run --rm just-paste-it php bin/phpunit --cache-directory var/phpunit
docker compose run --rm just-paste-it php bin/console lint:container
docker compose run --rm just-paste-it php bin/console lint:yaml config
docker compose run --rm just-paste-it php bin/console lint:twig templates
docker compose logs -f
docker compose down
```

Die Funktionstests migrieren jeweils eine eigene temporäre SQLite-Datenbank.
Sie prüfen Login/CSRF, das Speichern und Lesen verschlüsselter Notizen und Anhänge,
den Passwortwechsel und das Löschen. Entwicklungsdaten werden dabei nicht benutzt.

## Upgrade einer vorhandenen Installation

Vor dem Upgrade die bestehende SQLite-Datei sichern und den bisherigen
`DATABASE_URL` sowie `APP_SECRET` in `.env.local` übernehmen. Dann das Image bauen und
die Migrationen wie oben ausführen. Composer läuft bereits beim Build. Das Format der
verschlüsselten Daten bleibt erhalten. Die Migration von Symfony 6.1 auf 8.1
umfasst Doctrine ORM 3 / DBAL 4 und ersetzt das Sensio-Bundle durch Symfony-Attribute.

Die historische SQLite-Migration wird unverändert weiterverwendet. Ihr Schema
weicht bereits bei Fremdschlüsseln und der Nullbarkeit von `paste.content` vom
ORM-Mapping ab; DBAL 4 erkennt zusätzlich alte Typkommentare als Abweichung.
`doctrine:schema:validate` meldet deshalb einen Schema-Unterschied. Kein
`doctrine:schema:update --force` auf vorhandene Daten anwenden; eine gewünschte
Schema-Bereinigung sollte separat mit Datenprüfung und Migration erfolgen.
