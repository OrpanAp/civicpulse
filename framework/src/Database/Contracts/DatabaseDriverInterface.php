<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Database\Contracts;

use PDO;

interface DatabaseDriverInterface
{
    public function connect(array $config): PDO;
}
