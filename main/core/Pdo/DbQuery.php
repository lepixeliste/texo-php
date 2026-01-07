<?php

namespace Core\Pdo;

/**
 * Base interface for running query on Db
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

interface DbQuery
{
    /**
     * Runs the query on a Db instance and returns a collection of records.
     * 
     * @param  \Core\Pdo\Db $db The database instance
     * @return \Core\Collection
     * @throws \Core\Pdo\DbQueryException
     */
    public function run(Db $db);
}
