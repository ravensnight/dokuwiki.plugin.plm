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
     * Update or create an object
     */
    protected abstract function save(PlmDB $db,);

    /**
     * Delete the referenced object.
     */
    protected abstract function delete(PlmDB $db, bool $approve);

    /**
     * Acquire this object by given primary key.
     */
    public static abstract function byPK(PlmDB $db, int $pk) : ?static;
    
    /**
     * Provide the last inserted id/key
     */
    protected function getLastInsertedId(PlmDB $db, string $tableName) : int {
        return $db->lastInsertedId($tableName);
    } 
}
