<?php
declare(strict_types=1);

namespace Tests\Support;

class FakeStatement
{
    public function __construct(private string $sql = '')
    {
    }

    public function execute(array $params = []): bool
    {
        return true;
    }

    public function fetch(int $mode = 0): array|false
    {
        return false;
    }

    public function fetchAll(int $mode = 0): array
    {
        return [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return 0;
    }

    public function bindParam(string $param, mixed &$var, int $type = 0): bool
    {
        return true;
    }
}

class FakePdo
{
    public function prepare(string $sql): FakeStatement
    {
        return new FakeStatement($sql);
    }

    public function query(string $sql): FakeStatement
    {
        return new FakeStatement($sql);
    }
}
