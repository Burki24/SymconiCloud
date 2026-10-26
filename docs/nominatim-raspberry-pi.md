# Nominatim auf dem Raspberry Pi ohne Docker

Diese Anleitung beschreibt das regionale Setup für **Berlin und Brandenburg**,
das am 22.10.2026 auf einem Raspberry Pi mit bereits laufendem Symcon erfolgreich
eingerichtet wurde. Nominatim löst GPS-Koordinaten in Adressen auf. Es ersetzt
nicht die OpenFreeMap-Karte und benötigt keine zweite Anmeldung bei Apple.

Zurück zur [WoIst-Konfiguration](../iCloud%20WoIst/README.md#eigener-nominatim-server).

**Die Einrichtungsschritte sind für eine Neuinstallation gedacht.** Auf dem
bereits eingerichteten Pi genügt die bestehende Serveradresse in WoIst.
Keinen erneuten Initialimport starten und vorhandene Datenbanken oder
Dienstdateien nicht mit diesen Beispielen überschreiben.

- [Das erprobte System](#das-erprobte-system)
- [Voraussetzungen und Speichercontroller](#1-voraussetzungen-und-speichercontroller)
- [Gemeinsame Ressourcenlimits](#2-gemeinsame-ressourcenlimits)
- [Pakete und Dienstbenutzer](#3-pakete-und-dienstbenutzer)
- [Eigener PostgreSQL-Cluster](#4-eigener-postgresql-cluster)
- [Nominatim und Projektkonfiguration](#5-nominatim-und-projektkonfiguration)
- [Region und Freiplatzschutz](#6-region-herunterladen)
- [Import](#8-initialimport-starten)
- [HTTP-Dienst](#10-http-dienst-einrichten)
- [LAN und Testsystem](#11-zugriff-von-einem-anderen-symcon-system)
- [In WoIst eintragen](#12-in-woist-eintragen)

## Das erprobte System

| Bestandteil | Praxisbeispiel |
| --- | --- |
| Rechner | Raspberry Pi 5 Model B, 4 GiB RAM |
| Betriebssystem | Debian 12 Bookworm, aarch64, 64 Bit |
| Python | Debian-Python 3.11 in einer eigenen virtuellen Umgebung |
| Datenträger | microSD mit rund 59 GiB; vor der Einrichtung rund 27 GiB frei |
| Vorhandener Systemswap | Rund 8,2 GiB; davon darf Nominatim höchstens 512 MiB nutzen |
| Datenbank | PostgreSQL 15 mit PostGIS 3.3; im Praxislauf PostgreSQL 15.19 |
| OSM-Import | osm2pgsql 1.8.0 |
| Adressdienst | Nominatim 5.3.3 mit Falcon und Gunicorn 25.1.0 |
| Region | Geofabrik Brandenburg einschließlich Berlin |
| Importergebnis | Rund 6 Stunden 19 Minuten; anschließend rund 1,5 GiB Datenbankgröße |

Die Messwerte gelten für diesen Datenstand und diese Limits. Sie sind keine
Größen- oder Laufzeitzusage für neuere Daten, andere Regionen oder ganz
Deutschland. Auf größeren Rechnern können andere Grenzen sinnvoll sein.
Schnellere Datenträger können den Import deutlich beschleunigen.

Die Aufteilung ist bewusst unabhängig von Symcon:

```text
SymconiCloud / WoIst
        |
        | HTTP auf Port 8088
        v
Nominatim-API (www-data)
        |
        | lokaler PostgreSQL-Socket, Portkennung 5433
        v
PostgreSQL-Cluster 15/nominatim

API + PostgreSQL + Import teilen sich nominatim.slice.
Symcon gehört nicht zu dieser Slice.
```

## 1. Voraussetzungen und Speichercontroller

Zuerst eine aktuelle Symcon-Sicherung und Zugriff auf die Pi-Konsole vorsehen.
Die folgenden Befehle prüfen den Ausgangszustand, ohne Dienste zu verändern:

```bash
systemctl is-active symcon
free -h
df -h / /srv
lsblk -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINTS
stat -fc %T /sys/fs/cgroup
cat /sys/fs/cgroup/cgroup.controllers
sudo ss -ltnp 'sport = :8088'
if command -v pg_lsclusters >/dev/null; then
    pg_lsclusters
fi
```

Für dieses Beispiel müssen Port 8088 und ein neuer Clustername `15/nominatim`
verfügbar sein. Port 5433 darf keinem anderen PostgreSQL-Cluster gehören.
Für den Import werden vor Beginn mindestens **20 GiB frei** verlangt. Der
spätere Platzbedarf der fertigen Datenbank ist deutlich kleiner als der
zeitweise Bedarf während des Imports.

Das Dateisystem für cgroups muss `cgroup2fs` sein; die Controllerliste muss
`cpu`, `io` und insbesondere **`memory`** enthalten. Auf dem Beispiel-Pi fehlte
anfangs der Speichercontroller. Falls er bereits vorhanden ist, sind keine
Bootänderung und kein Neustart nötig.

Falls `memory` fehlt, die tatsächlich verwendete Bootdatei ermitteln. Bei dem
Beispielsystem war dies `/boot/firmware/cmdline.txt`, nicht die alte Datei unter
`/boot/cmdline.txt`. Die Datei zunächst sichern und mit `sudo nano` bearbeiten:

```bash
cat /proc/cmdline
sudo cp --no-clobber /boot/firmware/cmdline.txt /boot/firmware/cmdline.txt.before-nominatim
sudo nano /boot/firmware/cmdline.txt
```

Alle Bootparameter müssen auf **einer einzigen Zeile** bleiben. Für den
Speichercontroller `cgroup_enable=memory` ergänzen; eine vorhandene explizite
Deaktivierung `cgroup_disable=memory` entfernen. Andere Bootparameter erhalten.
Ein dafür erforderlicher Pi-Neustart unterbricht auch Symcon und gehört deshalb
in ein geplantes Zeitfenster. Danach die Controllerliste und den aktiven
Symcon-Dienst erneut prüfen. Erst mit wirksamen Limits importieren.

Die [Raspberry-Pi-Dokumentation zu cmdline.txt](https://www.raspberrypi.com/documentation/computers/configuration.html#kernel-command-line-cmdline-txt)
beschreibt die einzeilige Bootkonfiguration.

## 2. Gemeinsame Ressourcenlimits

Die Limits gelten **gemeinsam** für Datenbank, Import und API. Drei einzelne
RAM-Limits gleicher Größe würden den gewünschten gemeinsamen Rahmen nicht
erzwingen. Für den Beispiel-Pi wurde folgende Datei eingerichtet:

```bash
sudo tee /etc/systemd/system/nominatim.slice >/dev/null <<'EOF'
[Unit]
Description=Resource budget for Nominatim

[Slice]
CPUAccounting=yes
MemoryAccounting=yes
IOAccounting=yes
CPUQuota=100%
CPUWeight=10
MemoryHigh=2200M
MemoryMax=2600M
MemorySwapMax=512M
IOWeight=10
IOReadBandwidthMax=/dev/mmcblk0 8M
IOWriteBandwidthMax=/dev/mmcblk0 2M
EOF
```

**`/dev/mmcblk0` ist der Datenträger des Beispiels.** Bei SSD, NVMe oder einer
anderen Datenablage beide I/O-Zeilen an den tatsächlich betroffenen physischen
Datenträger anpassen. Nicht ungeprüft die Beispielbezeichnung übernehmen.

| Einstellung | Wirkung |
| --- | --- |
| `CPUQuota=100%` | Insgesamt höchstens die Rechenzeit eines CPU-Kerns, nicht aller Pi-Kerne |
| `MemoryHigh=2200M` | Ab 2200 MiB verstärkte Speicherfreigabe/Drosselung |
| `MemoryMax=2600M` | Gemeinsame harte RAM-Grenze von 2600 MiB |
| `MemorySwapMax=512M` | Zusätzlich höchstens 512 MiB Swap für Nominatim |
| I/O-Limits | Höchstens 8.000.000 Byte/s Lesen und 2.000.000 Byte/s Schreiben |
| Niedrige Gewichte | Weniger Vorrang bei konkurrierender CPU-/I/O-Last |

Die Grenze reserviert keinen Speicher für Symcon. Bei Überschreitung des harten
Limits kann ein Nominatim-Prozess beendet werden. Auf einer gemeinsamen microSD
bleiben zudem Datenträgerlatenzen möglich; garantierte Symcon-Reaktionszeiten
lassen sich damit nicht zusagen. Die Bedeutung der Einstellungen steht in
[systemd.resource-control](https://www.freedesktop.org/software/systemd/man/252/systemd.resource-control.html).

## 3. Pakete und Dienstbenutzer

Die Pakete für Debian 12 installieren, ohne pauschal das ganze System zu
aktualisieren:

```bash
sudo apt-get update
sudo apt-get install postgresql-15 postgresql-15-postgis-3 \
    postgresql-15-postgis-3-scripts postgresql-contrib osm2pgsql \
    python3-venv python3-dev python3-icu libicu-dev libpq-dev \
    build-essential pkg-config curl ca-certificates

sudo useradd --system --user-group --home-dir /srv/nominatim \
    --shell /usr/sbin/nologin nominatim
sudo install -d -m 0755 -o nominatim -g nominatim \
    /srv/nominatim /srv/nominatim/project /srv/nominatim/data

pg_lsclusters
osm2pgsql --version
python3 --version
```

Paketinstallationen können einen neuen PostgreSQL-Cluster `15/main` automatisch
anlegen und starten. Im Praxisbeispiel war dieser neu und unbenutzt; er wurde
auf manuellen Start gesetzt und angehalten. **Vorhandene Cluster anderer
Anwendungen weder stoppen noch umkonfigurieren.** Alle folgenden
Datenbankbefehle richten sich ausdrücklich an `15/nominatim` und Port 5433.

## 4. Eigener PostgreSQL-Cluster

```bash
sudo pg_createcluster 15 nominatim --port 5433
sudo install -d -m 0755 /etc/postgresql/15/nominatim/conf.d
sudo tee /etc/postgresql/15/nominatim/conf.d/90-nominatim-small.conf >/dev/null <<'EOF'
listen_addresses = ''
shared_buffers = 128MB
effective_cache_size = 512MB
work_mem = 4MB
maintenance_work_mem = 64MB
autovacuum_work_mem = 32MB
max_connections = 20
autovacuum_max_workers = 1
max_parallel_workers_per_gather = 0
max_parallel_maintenance_workers = 0
max_wal_size = 512MB
EOF

sudo install -d -m 0755 /etc/systemd/system/postgresql@15-nominatim.service.d
sudo tee /etc/systemd/system/postgresql@15-nominatim.service.d/resources.conf >/dev/null <<'EOF'
[Service]
Slice=nominatim.slice
Nice=15
IOSchedulingClass=idle
EOF

sudo systemctl daemon-reload
sudo systemctl start postgresql@15-nominatim.service
```

Die Debian-Clusterkonfiguration muss `conf.d` einlesen; dies ist bei einer
neuen Standardkonfiguration vorgesehen. `effective_cache_size` ist nur eine
Planerannahme und keine zusätzliche RAM-Reservierung. PostgreSQL-Dauerhaftigkeit
und Autovacuum bleiben aktiv. Der leere Wert für `listen_addresses` schließt
TCP-Verbindungen zur Datenbank aus; der lokale Unix-Socket bleibt verfügbar.

Import- und API-Rolle im **getrennten Cluster** anlegen:

```bash
(
    cd /tmp || exit 1
    sudo -u postgres createuser -p 5433 --superuser nominatim
    sudo -u postgres createuser -p 5433 --no-superuser --no-createdb \
        --no-createrole www-data
)
```

`nominatim` besitzt die für diesen einfachen Initialimport benötigten Rechte.
Der HTTP-Dienst läuft später als **`www-data`**; der Import vergibt dieser Rolle
die erforderlichen Leserechte. Die Debian-Standardregel für lokale
Peer-Authentifizierung verbindet den Linux-Benutzer mit der gleichnamigen
Datenbankrolle. Keine `trust`-Freigabe und kein Datenbankpasswort sind nötig.

Vor den weiteren Schritten die tatsächlich wirksamen Grenzen kontrollieren:

```bash
systemctl show postgresql@15-nominatim.service --property=Slice
cat /sys/fs/cgroup/nominatim.slice/memory.max
cat /sys/fs/cgroup/nominatim.slice/memory.swap.max
cat /sys/fs/cgroup/nominatim.slice/cpu.max
cat /sys/fs/cgroup/nominatim.slice/io.max
systemctl is-active symcon
```

Für das Beispiel sind `Slice=nominatim.slice`, `2726297600` Byte RAM,
`536870912` Byte Swap und `100000 100000` für die CPU zu erwarten. In `io.max`
müssen für den betroffenen Datenträger `rbps=8000000` und `wbps=2000000` stehen.
Bei `max`, fehlenden Controllern oder einer falschen Slice erst die Zuordnung
korrigieren und noch keinen Import beginnen.

## 5. Nominatim und Projektkonfiguration

Die virtuelle Umgebung nutzt das Debian-Paket für PyICU. So muss dessen
aufwendiger nativer Teil nicht erneut gebaut werden:

```bash
cd /tmp || exit 1
sudo -u nominatim python3 -m venv --system-site-packages /srv/nominatim/venv
sudo systemd-run --unit=nominatim-setup --slice=nominatim.slice \
    --uid=nominatim --working-directory=/srv/nominatim --wait --collect \
    --property=Nice=15 --property=IOSchedulingClass=idle \
    /srv/nominatim/venv/bin/python -m pip install \
    'nominatim-db==5.3.3' 'nominatim-api==5.3.3' \
    'psycopg[binary]' 'falcon>=3,<5' 'gunicorn==25.1.0'

sudo -u nominatim /srv/nominatim/venv/bin/python -m pip check
sudo -u nominatim /srv/nominatim/venv/bin/nominatim --version
```

Die Versionsvorgaben dokumentieren den erprobten Nominatim-/Gunicorn-Stand;
sie bedeuten keine automatische Aktualisierungsempfehlung. Gunicorn 25.1.0
unterstützt Debian-Python 3.11 und den hier verwendeten nativen ASGI-Worker.
Ein GitHub-Klon oder Fork ist für diese PyPI-Installation nicht erforderlich.

Die Datei `/srv/nominatim/project/.env` erstellen:

```bash
sudo tee /srv/nominatim/project/.env >/dev/null <<'EOF'
NOMINATIM_DATABASE_DSN="pgsql:dbname=nominatim;host=/var/run/postgresql;port=5433"
NOMINATIM_DATABASE_WEBUSER=www-data
NOMINATIM_IMPORT_STYLE=address
NOMINATIM_DEFAULT_LANGUAGE=de
NOMINATIM_API_POOL_SIZE=2
EOF
sudo chown nominatim:nominatim /srv/nominatim/project/.env
sudo chmod 0644 /srv/nominatim/project/.env
```

Der DSN legt absichtlich **keinen festen `user=nominatim`** fest: Der Import
verbindet sich als `nominatim`, die API als `www-data`. Die Datei enthält in
diesem Socket-Setup keine Zugangsdaten und muss vom API-Benutzer gelesen werden
können. `address` importiert die für Adressen benötigten Angaben bis zur
Hausnummer; der kleine API-Pool begrenzt die Datenbankverbindungen pro Worker.

## 6. Region herunterladen

Der [Geofabrik-Auszug Brandenburg](https://download.geofabrik.de/europe/germany/brandenburg.html)
enthält Berlin bereits. Beide Gebiete nicht getrennt doppelt importieren.

```bash
sudo -u nominatim sh -c '
set -eu
cd /srv/nominatim/data
curl --fail --location --retry 3 --output brandenburg-latest.osm.pbf \
    https://download.geofabrik.de/europe/germany/brandenburg-latest.osm.pbf
curl --fail --location --retry 3 --output brandenburg-latest.osm.pbf.md5 \
    https://download.geofabrik.de/europe/germany/brandenburg-latest.osm.pbf.md5
md5sum --check brandenburg-latest.osm.pbf.md5
'
```

Nur mit erfolgreicher Prüfsumme weiterarbeiten. Falls der Anbieter gerade
zwischen zwei Tagesständen gewechselt hat, Datei und Prüfsumme zusammen erneut
laden und prüfen.

## 7. Freiplatzschutz für den Initialimport

Der Schutz gilt für diese Erstinstallation auf einem gemeinsamen Datenträger.
Vor dem Start sind mindestens **20 GiB frei** erforderlich. Unter **8 GiB**
stoppt er den Import und dessen eigenen PostgreSQL-Cluster. Er löscht keine
Daten und beendet nicht Symcon. Der Schwellenabbruch wurde im erfolgreichen
Praxislauf nicht ausgelöst und nicht durch künstliches Füllen des Datenträgers
getestet.

Im Beispiel liegen Projekt, OSM-Datei und PostgreSQL-Daten auf demselben
Root-Dateisystem. Bei getrennten Datenträgern muss der Freiplatzschutz alle
beschriebenen Dateisysteme abdecken; die folgende Ein-Dateisystem-Prüfung dann
nicht unverändert übernehmen.

Die Datei `/usr/local/sbin/nominatim-import-guard` anlegen:

```bash
sudo tee /usr/local/sbin/nominatim-import-guard >/dev/null <<'EOF'
#!/bin/sh
set -eu
free_bytes=$(df -PB1 /srv/nominatim/project | awk 'NR == 2 {print $4}')
case "$free_bytes" in
    ''|*[!0-9]*) echo 'Cannot determine available disk space.' >&2; exit 1 ;;
esac
case "${1:-}" in
    preflight)
        if [ "$free_bytes" -lt 21474836480 ]; then
            echo 'Initial import requires at least 20 GiB free.' >&2
            exit 1
        fi
        cd /srv/nominatim/data
        md5sum --check brandenburg-latest.osm.pbf.md5
        ;;
    watch)
        if [ "$free_bytes" -lt 8589934592 ]; then
            echo 'Less than 8 GiB free: stopping the Nominatim import and its cluster.' >&2
            systemctl --no-block stop nominatim-import.service postgresql@15-nominatim.service
        fi
        ;;
    *) echo 'Usage: nominatim-import-guard preflight|watch' >&2; exit 2 ;;
esac
EOF
sudo chmod 0755 /usr/local/sbin/nominatim-import-guard

sudo tee /etc/systemd/system/nominatim-disk-watch.service >/dev/null <<'EOF'
[Unit]
Description=Check free space during the initial Nominatim import

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/nominatim-import-guard watch
EOF

sudo tee /etc/systemd/system/nominatim-disk-watch.timer >/dev/null <<'EOF'
[Unit]
Description=Watch disk space during the initial Nominatim import

[Timer]
OnActiveSec=1s
OnUnitActiveSec=5s
AccuracySec=1s
Unit=nominatim-disk-watch.service
EOF
```

Der Timer wird vom Importdienst mit gestartet und nicht dauerhaft aktiviert.
Nach dem abgeschlossenen Import wird er wieder angehalten. Diese Überwachung
nicht unverändert für spätere Replikations- oder Neuimportverfahren verwenden:
Dann könnten bereits andere Anwendungen dieselbe Datenbank abfragen.

## 8. Initialimport starten

Der Import läuft als Dienst weiter, auch wenn die SSH-Verbindung getrennt wird.
Er wird nicht automatisch wiederholt. Die Datenbank `nominatim` darf für einen
neuen Initialimport noch nicht vorhanden sein; eine bestehende Installation
nicht löschen oder mit diesem Ablauf neu beginnen.

```bash
sudo tee /etc/systemd/system/nominatim-import.service >/dev/null <<'EOF'
[Unit]
Description=Initial Nominatim import for Berlin and Brandenburg
Requires=postgresql@15-nominatim.service nominatim-disk-watch.timer
After=postgresql@15-nominatim.service nominatim-disk-watch.timer

[Service]
Type=simple
User=nominatim
Group=nominatim
WorkingDirectory=/srv/nominatim/project
Environment=OMP_NUM_THREADS=1
Slice=nominatim.slice
Nice=15
IOSchedulingClass=idle
ExecStartPre=/usr/local/sbin/nominatim-import-guard preflight
ExecStart=/srv/nominatim/venv/bin/nominatim import --osm-file /srv/nominatim/data/brandenburg-latest.osm.pbf --threads 1 --osm2pgsql-cache 128 --reverse-only --no-updates
TimeoutStartSec=0
Restart=no
EOF

sudo systemctl daemon-reload
sudo systemctl start nominatim-import.service
sudo journalctl -u nominatim-import.service -n 30 -f -o cat
```

`Strg+C` beendet nur die Loganzeige, nicht den Importdienst. Die Optionen sind
bewusst sparsam: ein Thread, 128 MiB osm2pgsql-Cache und nur Reverse-Geocoding.
`--no-updates` entfernt zusätzlich Datenstrukturen für spätere OSM-Updates.

Die Anzeige kann länger ohne neue Zeile bleiben, während PostgreSQL Daten
sortiert oder Indizes erstellt. Meldungen `Rank ... ETA` beziehen sich auf den
jeweiligen Rank, nicht auf den kompletten Restimport. Auch nach Rank 30 können
Interpolationen, weitere Indexläufe und Abschlussarbeiten folgen.

Im Praxislauf begann der Import um 11:41 Uhr und endete erfolgreich um 18:00 Uhr.
Währenddessen waren Symcon weiter aktiv und die Speicher-/Datenträgerreserven
ausreichend. Den aktuellen Zustand in einem zweiten Terminal kontrollieren:

```bash
systemctl is-active symcon
systemctl show nominatim-import.service \
    --property=ActiveState,SubState,Result,ExecMainStatus
systemctl is-active nominatim-disk-watch.timer
free -h
df -h /srv/nominatim/project
```

`Result=success` und `ExecMainStatus=0` allein bedeuten während
`ActiveState=active` noch keinen fertigen Import. Entscheidend sind die
Abschlussmeldung **Import completed successfully** und der anschließend
beendete Importprozess. Bei Abbruch oder Ressourcenproblemen erst die Ursache
klären; den Initialimport nicht einfach erneut starten.

## 9. Datenbank nach dem Import prüfen

Nach erfolgreichem Abschluss die temporäre Überwachung stoppen und die
Nominatim-Prüfung im Projektverzeichnis ausführen:

```bash
sudo systemctl stop nominatim-disk-watch.timer
sudo -u nominatim sh -c '
cd /srv/nominatim/project &&
/srv/nominatim/venv/bin/nominatim admin --check-database
'
sudo -u nominatim sh -c '
cd /srv/nominatim/project &&
/srv/nominatim/venv/bin/nominatim reverse --lat 52.5163 --lon 13.3777
'
```

Die Beispielkoordinate liegt am öffentlichen Pariser Platz in Berlin. Es wird
eine passende Adresse erwartet, kein unveränderlicher Wortlaut: OSM-Daten und
Detailzuordnung können sich ändern. Datenbankverbindung, Tokenizer,
Indexvollständigkeit und Indexgültigkeit sollen erfolgreich geprüft werden.
Fehlende optionale Wikipedia-Daten sind für diese Adressauflösung kein Fehler.

## 10. HTTP-Dienst einrichten

Der API-Dienst liest dieselbe Datenbank als `www-data`, nutzt dieselbe
Ressourcen-Slice und benötigt weder Docker noch Änderungen an Symcons Webports.
Für dieses lokale beziehungsweise LAN-Setup wird Gunicorn direkt über HTTP
angesprochen; `nominatim serve` ist nur ein Entwicklungsserver und wird nicht
als dauerhafter Dienst verwendet.

```bash
sudo tee /etc/systemd/system/nominatim-api.service >/dev/null <<'EOF'
[Unit]
Description=Nominatim reverse geocoding API
Requires=postgresql@15-nominatim.service
After=network.target postgresql@15-nominatim.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/srv/nominatim/project
Environment=PYTHONDONTWRITEBYTECODE=1
Slice=nominatim.slice
Nice=15
IOSchedulingClass=idle
PrivateTmp=yes
ExecStart=/srv/nominatim/venv/bin/gunicorn --bind 127.0.0.1:8088 --workers 1 --worker-class asgi --worker-connections 16 --timeout 120 "nominatim_api.server.falcon.server:run_wsgi()"
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now nominatim-api.service
curl --noproxy '*' --connect-timeout 3 --max-time 10 \
    http://127.0.0.1:8088/status
curl --noproxy '*' --connect-timeout 3 --max-time 30 \
    'http://127.0.0.1:8088/reverse?lat=52.5163&lon=13.3777&format=jsonv2&addressdetails=1&accept-language=de'
```

`/status` soll `OK` liefern, `/reverse` ein JSON-Ergebnis. Im Praxisbeispiel
wurde genau dieser lokale Zugriff erfolgreich genutzt. Ein Worker und
`NOMINATIM_API_POOL_SIZE=2` begrenzen die parallelen Datenbankverbindungen.
Die Einrichtung orientiert sich am
[Nominatim-Frontend](https://nominatim.org/release-docs/5.3/admin/Deployment-Python/),
verwendet hier aber einen HTTP-Port statt nginx und Unix-HTTP-Socket.

## 11. Zugriff von einem anderen Symcon-System

Läuft Symcon ebenfalls auf dem Pi, reicht die bisherige Loopback-Bindung. Für
ein separates Testsystem den Dienst zusätzlich an die **eigene LAN-Adresse
des Pi** binden. Eine feste Adresse beziehungsweise DHCP-Reservierung
verhindert, dass sich die konfigurierte Adresse unbemerkt ändert.

In `nominatim-api.service` nur die `ExecStart`-Zeile entsprechend ergänzen:

```ini
ExecStart=/srv/nominatim/venv/bin/gunicorn --bind 127.0.0.1:8088 --bind <Pi-LAN-IP>:8088 --workers 1 --worker-class asgi --worker-connections 16 --timeout 120 "nominatim_api.server.falcon.server:run_wsgi()"
```

`<Pi-LAN-IP>` **vor dem Speichern** durch die tatsächliche Pi-Adresse ersetzen.
Loopback bleibt erhalten; nicht pauschal an alle Netzwerkschnittstellen binden.
Der API-Dienst bietet hier keine eigene Anmeldung: Zugriff auf vertrauenswürdige
LAN-Clients begrenzen und keine Router-Portfreigabe ins Internet einrichten.
Eine vorhandene Firewall muss den vorgesehenen Clientzugriff auf Port 8088
zulassen. PostgreSQL bleibt ausschließlich über den lokalen Socket erreichbar.

```bash
sudo systemctl daemon-reload
sudo systemctl restart nominatim-api.service
sudo ss -ltnp 'sport = :8088'
```

Symcon und PostgreSQL benötigen für diese Bindungsänderung keinen Neustart.
Anschließend **vom anderen Rechner** aus testen, wobei auch hier der
Platzhalter zu ersetzen ist:

```bash
curl --noproxy '*' --connect-timeout 3 --max-time 10 \
    'http://<Pi-LAN-IP>:8088/status'
```

Auf einem Windows-Testsystem kann derselbe Aufruf mit `curl.exe` verwendet
werden. Der Benutzer bestätigte nach dieser Erweiterung die funktionierende
Adressauflösung auch auf dem getrennten Symcon-Testsystem.

## 12. In WoIst eintragen

In jeder anfragenden WoIst-Instanz **Adressauflösung** öffnen:

1. **Gerätepositionen in Adressen auflösen** einschalten.
2. **Adressanbieter → Eigener Nominatim-Server** wählen.
3. **Basis-URL des eigenen Nominatim-Servers** eintragen:

   | Symcon-System | Basis-URL |
   | --- | --- |
   | Symcon auf dem Nominatim-Pi | `http://127.0.0.1:8088` |
   | Getrenntes Symcon-Testsystem | `http://<Pi-LAN-IP>:8088` |

4. **Adresssprache (z. B. de oder en)** auf `de` belassen und **Übernehmen**.

Nur die Basis-URL eintragen, **ohne `/reverse`** und ohne Query-Parameter.
`127.0.0.1` bezeichnet den **anfragenden Symcon-Server**. Die IP des Rechners
mit der geöffneten Konsole oder IPSView spielt für diese Auflösung keine Rolle.

Vorhandene Gerätepositionen werden im Hintergrund aufgelöst. Die Ergebnisse
erscheinen in WoIst-Liste und Popup; eine Geräteinstanz kann dieselbe Adresse
über ihre WoIst-Adressquelle in der Variable **Adresse** übernehmen. Nominatim
braucht dafür keine Apple-Zugangsdaten und keine zweite Gerätekopie. Die
Genauigkeitsregeln des Moduls bleiben wirksam: Ein eigener Server macht aus
ungenauen GPS-Koordinaten keine präzise Hausnummer.

## Datenbestand und spätere Änderungen

Dieses Beispiel enthält nur die importierte Region. Für Positionen außerhalb
des Datenbestands ist kein vollständiges Adressergebnis zu erwarten. Durch
`--reverse-only` ist die Installation für **Koordinaten → Adresse** ausgelegt,
nicht für eine allgemeine Suche nach Namen oder Adressen.

`--no-updates` spart Datenbankplatz, verhindert aber laufende
OSM-Replikationsupdates. Der Datenbestand vom Importtag bleibt bestehen;
Apple-Gerätepositionen werden davon unabhängig weiter aktualisiert. Regelmäßige
OSM-Updates und Gebietserweiterungen wurden bewusst auf später verschoben.
Sie benötigen einen gesonderten Plan mit geeignetem Import, Speicherreserve
und kontrollierter Umschaltung. Nicht auf der funktionierenden Datenbank
ungeplant neu importieren oder den Freiplatz-Guard dafür wiederverwenden.

Für Sicherungen gehören Projektverzeichnis samt `.env` und Datenbank zusammen.
Versionswechsel von Nominatim, PostgreSQL oder Gunicorn getrennt planen.

## Weiterführende Quellen

- [Nominatim 5.3: Installation](https://nominatim.org/release-docs/5.3/admin/Installation/)
- [Nominatim 5.3: Import und platzsparende Optionen](https://nominatim.org/release-docs/5.3/admin/Import/)
- [Nominatim 5.3: Konfiguration](https://nominatim.org/release-docs/5.3/customize/Settings/)
- [Geofabrik: Brandenburg einschließlich Berlin](https://download.geofabrik.de/europe/germany/brandenburg.html)
- [Gunicorn 25.1.0: Paket und Python-Voraussetzung](https://pypi.org/project/gunicorn/25.1.0/)

Die erfolgreiche Pi-Installation und LAN-Anbindung wurden durch die Ausgaben
und Rückmeldungen des Benutzers bestätigt. Die hier zusammengefasste
Anleitung wurde nicht als erneute Neuinstallation auf dem laufenden Pi
ausgeführt.
