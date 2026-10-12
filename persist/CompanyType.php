<?php

/**
 * Entity for Company Types.
 */
class CompanyType extends DbEnum
{
    #[Override]
    protected static function getTableName(): string
    {
        return 'plm_company_types';
    }
}