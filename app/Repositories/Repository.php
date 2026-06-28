<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

abstract class Repository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}

