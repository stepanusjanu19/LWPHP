<?php

use Doctrine\DBAL\Connection;
use Kei\Lwphp\Base\Migration;
use Doctrine\DBAL\Schema\Table;

class CreateArticlesTable extends Migration
{
    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['articles'])) {
            $table = new Table('articles');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('name', 'string', ['length' => 255]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $schemaManager->createTable($table);
        }
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['articles'])) {
            $schemaManager->dropTable('articles');
        }
    }
}
