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

Erweitert den SqlManager, in dem er dafür sorgt, dass Verbindungsabbrüche ("MySQL server has gone away" / "Lost connection", Codes 2006 und 2013) bei `prepare` und `execute` abgefangen werden und automatisch einmalig eine erneute Verbindung aufgebaut wird.
Läuft gerade eine Transaktion, wird **nicht** neu verbunden, sondern eine `TransactionRuntimeException` geworfen, da die Transaktion mit der Verbindung verloren gegangen ist.

### TransactionManager

Implementiert `ManagesTransactions` und verwaltet verschachtelte Transaktionen über Savepoints (siehe unten).

### DefaultSqlManagerConfig

Konfigurationsklasse, die das Konfigurations-Interface implementiert. Der Aufruf erfolgt mit einem Array mit den Konfigurationsdaten der Datenbank oder mit `fromFile` anhand der Konfigurationsdatei,
welche das Array mit den Daten beinhaltet.

### InjectingRelationalDatabaseManager

Trait, welcher einen `ManagesRelationalDatabases` in den Constructor injected (`$this->dbManager`).

### UsingDatabaseTransactions

Ergänzt `InjectingRelationalDatabaseManager` um Methoden, die das Interface `UsesTransactions` erfüllen.

## Konfiguration

Pflichtfelder sind `host`, `database`, `user` und `password`. `port` ist standardmäßig `3306`, `charset` standardmäßig `utf8mb4` (wird im DSN gesetzt).
Unter `options` angegebene PDO-Optionen werden mit den Defaults (gepufferte Queries, Forward-Only-Cursor) zusammengeführt.
`PDO::ATTR_ERRMODE` wird beim Verbinden immer auf `ERRMODE_EXCEPTION` gesetzt und kann nicht überschrieben werden.

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
$statement = $sqlManager->prepare('SELECT * FROM users WHERE id = :id');
$row = $statement->fetchRow(['id' => 5]);
````

Insert, Update, Delete
````PHP
$affectedRows = $sqlManager->execute('UPDATE users SET active = :active WHERE id = :id', ['active' => 1, 'id' => 5]);
$sqlManager->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'Alice']);
$id = $sqlManager->lastInsertId();
````

Dump Import
````PHP
$sqlManager->importDump( 'path/to/dump.sql' );
````

##  PreparedSqlStatement – Verwendung

Rückgabe einzelner Werte
````PHP
$value = $statement->fetchValue(['id' => 1]); // z.B. 'Alice', null bei NULL-Spalte oder keinem Treffer
````

Rückgabe mehrerer Werte (Iterator)
````PHP
foreach ($statement->fetchValues() as $value) 
{
    echo $value;
}
````

Ganze Zeile als Array
````PHP
$row = $statement->fetchRow(['id' => 1]);
````

Mehrere Zeilen
````PHP
foreach ($statement->fetchRows() as $row) 
{
    // $row ist assoziatives Array
}

````

Entity-Mapping
````PHP
$entity = $statement->fetchEntity(User::class, ['id' => 1]);
$users = iterator_to_array($statement->fetchEntities(User::class));
````

Gruppierte Ergebnisse
Erfordert, dass die Daten sortiert nach der Gruppierung sind:
````PHP
foreach ($statement->fetchGroupedBy('role') as $role => $users) 
{
    echo "Rolle: $role, Benutzer: " . count($users);
}
````

Anzahl betroffener Zeilen
````PHP
$statement->execute(['id' => 1]);
$count = $statement->getAffectedRowCount();
````

## TransactionManager – Verwendung

````PHP
$transactionManager = new TransactionManager($sqlManager);

$transactionManager->begin();
try
{
    $transactionManager->commit();
}
catch (\Throwable $exception)
{
    $transactionManager->rollBack();
    throw $exception;
}
````

Oder kompakter mit Callback – bei einer Exception wird automatisch zurückgerollt und die Exception weitergeworfen:
````PHP
$userId = $transactionManager->transactional(function (ManagesTransactions $transactionManager) use ($sqlManager): string {
    $sqlManager->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'Alice']);

    return $sqlManager->lastInsertId();
});
````

Verschachtelte Transaktionen werden über `SAVEPOINT` abgebildet. Ein inneres `rollBack()` verwirft nur die Änderungen seit dem inneren `begin()`, die äußere Transaktion bleibt bestehen. `getTransactionLevel()` liefert die aktuelle Tiefe.
Wurde am `SqlManager` bereits direkt eine Transaktion gestartet, wirft `begin()` eine `TransactionLogicException`.

Beispiel für verschachtelte Transaktionen: Die Bestellung wird gespeichert, auch wenn das Schreiben des Audit-Logs fehlschlägt.
````PHP
$transactionManager->transactional(function (ManagesTransactions $transactionManager) use ($sqlManager): void {
    $sqlManager->execute('INSERT INTO orders (customer_id) VALUES (:customerId)', ['customerId' => 42]);
    $orderId = $sqlManager->lastInsertId();

    try
    {
        $transactionManager->transactional(function () use ($sqlManager, $orderId): void {
            $sqlManager->execute('INSERT INTO audit_log (order_id) VALUES (:orderId)', ['orderId' => $orderId]);
            $sqlManager->execute('UPDATE statistics SET order_count = order_count + 1');
        });
    }
    catch (QueryException $exception)
    {
        $logger->warning('Audit-Log konnte nicht geschrieben werden', ['exception' => $exception]);
    }

    $sqlManager->execute('UPDATE customers SET last_order_id = :orderId WHERE id = :customerId', ['orderId' => $orderId, 'customerId' => 42]);
});
````

Ablauf auf SQL-Ebene:

| Aufruf | Level | Ausgeführtes SQL |
|---|---|---|
| äußeres `begin()` | 0 → 1 | `START TRANSACTION` |
| inneres `begin()` | 1 → 2 | `SAVEPOINT transaction_level_1` |
| inneres `commit()` | 2 → 1 | `RELEASE SAVEPOINT transaction_level_1` |
| inneres `rollBack()` (bei Fehler) | 2 → 1 | `ROLLBACK TO SAVEPOINT transaction_level_1` |
| äußeres `commit()` | 1 → 0 | `COMMIT` |

Schlägt eine Query im inneren Block fehl, werden nur der Audit-Eintrag und das Statistik-Update verworfen. Die Bestellung und das Kunden-Update werden trotzdem committet. Wird die Exception nicht abgefangen, rollt auch die äußere Transaktion vollständig zurück.

`SAVEPOINT`, `RELEASE SAVEPOINT` und `ROLLBACK TO SAVEPOINT` sind Standard-SQL (SQL:1999) und werden von MySQL/MariaDB (InnoDB), PostgreSQL und SQLite unterstützt. DDL-Statements (`CREATE`, `ALTER`, `DROP` …) lösen in MySQL einen impliziten Commit aus und verwerfen dabei alle Savepoints.

------

## Fehlerbehandlung

Alle Query-Methoden werfen bei SQL-Fehlern eine QueryException (`RuntimeException`), die Folgendes enthält:

* Fehlertext ($e->getMessage())
* SQL-Query ($e->getQuery())
* Bind-Parameter ($e->getPreparedParameters())
* Treiber-Fehlercode ($e->getDriverErrorCode())
* Ursprüngliche `PDOException` ($e->getPrevious())

Transaktionsfehler werfen spezialisierte Exceptions:

* TransactionRuntimeException
* TransactionLogicException

------
## Docker

Siehe [DOCKER.md](DOCKER.md).
