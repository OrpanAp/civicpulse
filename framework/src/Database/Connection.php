<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database;

use PDO;
use PDOStatement;
use LogicException;
use Throwable;

class Connection
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function driverName(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->run($sql, $bindings);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $statement = $this->run($sql, $bindings);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function scalar(string $sql, array $bindings = []): mixed
    {
        $statement = $this->run($sql, $bindings);

        $result = $statement->fetchColumn();

        return $result === false ? null : $result;
    }

    public function statement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new LogicException(
                'Nested transactions are not supported yet.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);

            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        $statement->execute($bindings);

        return $statement;
    }
}
