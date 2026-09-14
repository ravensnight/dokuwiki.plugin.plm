<?php

class Status extends DbEnum
{
    #[Override]
    protected static function getTableName(): string
    {
        return 'plm_categories';
    }
}
