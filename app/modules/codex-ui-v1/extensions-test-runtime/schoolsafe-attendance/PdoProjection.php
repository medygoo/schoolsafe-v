<?php
declare(strict_types=1);
require_once __DIR__ . '/ArrivalProjection.php';

function SchoolSafeArrivalProjectPdo(PDO $database, array $event, array $mapping): array
{
    if ($database->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql' || $database->inTransaction()) {
        throw new RuntimeException('DEDICATED_POSTGRES_CONNECTION_REQUIRED');
    }
    return SchoolSafeArrivalProject($event, $mapping, static function (string $sql, array $parameters) use ($database): array {
        $statement = $database->prepare($sql);
        if (!$statement->execute($parameters)) throw new RuntimeException('ARRIVAL_DATABASE_ERROR');
        return $statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    });
}
