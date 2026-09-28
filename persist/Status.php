<?php

class Status extends DbEnum
{
    #[Override]
    protected static function getTableName(): string
    {
        return 'plm_status';
    }

    #[Override]
    public function save(PlmDB $db)
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function delete(PlmDB $db, bool $aprove)
    {
        throw new \Exception('Not implemented');
    }
}
