<?php

use Doctrine\DBAL\Schema\Schema;
use Kei\Lwphp\Console\Command\MigrationInterface;

return new class implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Auto-generated migration for articles';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('articles');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('articles');
    }
};
