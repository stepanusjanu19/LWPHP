<?php

use Kei\Lwphp\Base\Migration;
use Doctrine\DBAL\Connection;

/**
 * Migration: CreateTestTable
 */
class CreateTestTable extends Migration
{
    public function up(Connection $db): void
    {
        // Write your SQL queries to apply this migration
        // Example: $db->executeStatement("CREATE TABLE example (id INT PRIMARY KEY)");
    }

    public function down(Connection $db): void
    {
        // Write your SQL queries to rollback this migration
        // Example: $db->executeStatement("DROP TABLE example");
    }
}
