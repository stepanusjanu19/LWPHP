<?php

namespace Kei\Lwphp\Base;

use Doctrine\DBAL\Connection;

/**
 * Abstract class for database migrations.
 */
abstract class Migration
{
    /**
     * Run the migrations.
     */
    abstract public function up(Connection $db): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(Connection $db): void;
}
