# Sql

## Beschreibung

Die ComponoKit\Sql-Library ist eine einfache, aber leistungsfähige Abstraktion über PDO, die Fokus auf:

* klare Schnittstellen,
* starke Typisierung,
* ausdrucksstarke Fehlerbehandlung,

legt.

## Inhalt

### SqlManager

Die Pdo-Wrapper-Klasse. Sie sorgt dafür, dass die Verbindung zu Datenbank nur dann aufgebaut wird, wenn sie gebraucht wird und nicht wie bei vorigen Versionen bzw. bei `PDO` beim Instanziieren des Objekts. 

### MySqlManager

Erweitert den SqlManager, in dem er dafür sorgt, dass Verbindungstimeouts ("MySql server has gone away"), nach zu langer ungenutzten offenen Verbindung, abgefangen werden und automatisch eine erneute Verbindung aufgebaut wird.

### DefaultSqlManagerConfig

Konfigurationsklasse, die das Konfigurations-Interface implementiert. Der Aufruf erfolgt mit einem Array mit den Konfigurationsdaten der Datenbank oder mit `fromFile` anhand der Konfigurationsdatei,
welche das Array mit den Daten beinhaltet.

### InjectingSqlManager

Trait, welcher den `SqlManager` in den Constructor injected.

### UsingSqlTransactions

Wie `InjectingSqlManager`, nur dass noch zusätzliche Methoden zur Verfügung gestellt werden, die das Interface `PerformsTransactions` erfüllen.

## Konfiguration

Beispielkonfiguration:

````PHP
$config = new DefaultSqlManagerConfig([
    'host'     => 'localhost',
    'database' => 'my_app_db',
    'user'     => 'user',
    'password' => 'secret',
    'port'     => 3306,
    'charset'  => 'utf8mb4',
]);

$sqlManager = new SqlManager($config);
````

oder per PHP-Konfigurationsdatei:

````PHP
DefaultSqlManagerConfig::fromFile( 'path/to/config/db.php' );
````

##  SqlManager – Verwendung

Verbindung
````PHP
$sqlManager->connect(); // optional – wird bei Bedarf automatisch hergestellt
$pdo = $sqlManager->getPdo(); // Zugriff auf native PDO

````
Transaktionen
````PHP
$sqlManager->beginTransaction();
try 
{
    $sqlManager->commit();
} 
catch (\Throwable $exception) 
{
    $sqlManager->rollBack();
    throw $exception;
}
````

Vorbereitetes Statement
````PHP
$stmt = $sqlManager->prepare('SELECT * FROM users WHERE id = :id');
$row = $stmt->fetchRow(['id' => 5]);
````

Dump Import
````PHP
$sqlManager->importDump( 'path/to/dump.sql' );
````

##  PreparedSqlStatement – Verwendung

Rückgabe einzelner Werte
````PHP
$value = $stmt->fetchValue(['id' => 1]); // z.B. 'Alice'
````

Rückgabe mehrerer Werte (Iterator)
````PHP
foreach ($stmt->fetchValues() as $value) 
{
    echo $value;
}
````

Ganze Zeile als Array
````PHP
$row = $stmt->fetchRow(['id' => 1]);
````

Mehrere Zeilen
````PHP
foreach ($stmt->fetchRows() as $row) 
{
    // $row ist assoziatives Array
}

````

Entity-Mapping
````PHP
$entity = $stmt->fetchEntity(User::class, ['id' => 1]);
$users = iterator_to_array($stmt->fetchEntities(User::class));
````

Gruppierte Ergebnisse
Erfordert, dass die Daten sortiert nach der Gruppierung sind:
````PHP
foreach ($stmt->fetchGroupedBy('role') as $role => $users) 
{
    echo "Rolle: $role, Benutzer: " . count($users);
}
````

------

## Fehlerbehandlung

Alle Query-Methoden werfen bei SQL-Fehlern eine QueryException, die Folgendes enthält:

* Fehlertext ($e->getMessage())
* Fehlerdetails ($e->getErrors())
* SQL-Query ($e->getQuery())
* Bind-Parameter ($e->getPreparedParameters())

Transaktionsfehler werfen spezialisierte Exceptions:

* TransactionRuntimeException
* TransactionLogicException

------
## Docker

**Initial:**

* `docker-compose build --build-arg GITHUB_TOKEN="{TOKEN}"` (Use your GitHub token instead of `{TOKEN}`)
* `docker-compose up -d`

**Start development environment:**

* `docker-compose up -d`

**Update composer dependencies**

* `docker-compose run sql_lib composer update -vvv`

-----
