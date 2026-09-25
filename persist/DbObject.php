<?php

abstract class DbObject {

    public ?int $pk = null;

    /**
     * Construction
     */
    protected function __construct(?array $fields = null) {
        if ($fields) {
            $this->pk = $fields['id'];
            $this->assign($fields);
        } 
    }

    /**
     * Assign data to this object from given table fields.
     */
    protected abstract function assign(array $fields);

    /**
     * Acquire this object by given primary key.
     */
    public static abstract function byPK(PlmDB $db, int $pk) : ?static;
}
