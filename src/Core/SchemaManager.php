<?php

namespace Kei\Lwphp\Core;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * SchemaManager
 *
 * Reads Doctrine entity metadata and creates/updates the DB schema.
 * Called once on App boot in development mode — safe to call repeatedly
 * because updateSchema() is idempotent (only applies diffs).
 *
 * For production: disable debug mode and run `php bin/migrate.php` instead.
 */
class SchemaManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Create-or-update all entity tables.
     * Adds missing columns/indexes; never drops existing data.
     */
    public function updateSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        if (empty($metadata)) {
            return;
        }

        // updateSchema computes a diff and applies only missing DDL
        $schemaTool->updateSchema($metadata);
    }

    /**
     * Drop and recreate ALL tables. Destructive — use only in tests.
     */
    public function recreateSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        if (!empty($metadata)) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }
    }

    /**
     * Print current schema as SQL (dry-run).
     *
     * @return string[]
     */
    public function getSchemaSql(): array
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        return $schemaTool->getCreateSchemaSql($metadata);
    }
}
