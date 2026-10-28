<?php

abstract class DbEnum extends DbObject {

    public string $name;
    public ?string $description = null;
    public int $sort_order = 0;
    public bool $active = true;

    protected abstract static function getTableName() : string;

    public static function fetchName(PlmDB $db, int $pk): string
    {
        $obj = static::byPK($db, $pk);
        return $obj ? $obj->name : 'N/A';
    }

    public function assign(array $fields)
    {
        $this->name = $fields['name'];
        $this->description = $fields['description'];
        $this->sort_order = $fields['sort_order'];
        $this->active = ($fields['active'] > 0);
    }

    public static function createNew(string $name, ?string $description = null, bool $active = true, int $sort_oder = 0): self
    {
        return new static([
            'name' => $name,
            'sort_order' => $sort_oder,
            'active' => ($active === true ? 1 : 0),
            'description' => $description
        ]);
    }

    #[Override]
    public static function byPK(PlmDB $db, int $pk): ?static
    {
        $q = 'SELECT * FROM ' . static::getTableName() . ' WHERE id = :pk;';
        
        $result = $db->querySingle($q, ['pk' => $pk]);
        if ($result) {
            return new static($result);
        }

        return null;
    }

    public static function byName(PlmDB $db, int $name): ?self
    {
        $q = 'SELECT * FROM ' . static::getTableName() . 'WHERE name = :name;';

        $result = $db->querySingle($q, ['name' => $name]);
        if ($result) {
            return new static($result);
        }

        return null;
    }

    /**
     * @return static[]
     */
    public static function fetchAll(PlmDB $db): array
    {
        $q = 'SELECT * FROM ' . static::getTableName() . ';';

        $result = $db->fetchAll($q);
        $res = [];

        foreach ($result as $row) {
            $res[] = new static($row);
        }

        return $res;
    }

    #[Override]
    public function save(PlmDB $db)
    {
        if ($this->pk) {
            // Update
            $q = 'UPDATE ' . static::getTableName() . ' SET name=:name, description=:description, sort_order=:sort_order, active=:active WHERE id=:pk;';
            $db->querySingle($q, [
                'name' => $this->name,
                'description' => $this->description,
                'sort_order' => $this->sort_order,
                'active' => $this->active ? 1 : 0,
                'pk' => $this->pk
            ]);
        } else {
            // Insert
            $q = 'INSERT INTO ' . static::getTableName() . ' (name, description, sort_order, active) VALUES (:name, :description, :sort_order, :active);';
            $db->querySingle($q, [
                'name' => $this->name,
                'description' => $this->description,
                'sort_order' => $this->sort_order,
                'active' => $this->active ? 1 : 0
            ]);
            $this->pk = $db->lastInsertedId(static::getTableName());
        }
        return $this;
    }

    #[Override]
    public function delete(PlmDB $db, bool $aprove)
    {
        if ($this->pk) {
            $q = 'DELETE FROM ' . static::getTableName() . ' WHERE id = :pk;';
            $db->querySingle($q, ['pk' => $this->pk]);
        }
        return $this;
    }
}