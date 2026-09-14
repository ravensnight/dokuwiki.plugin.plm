<?php

abstract class DbEnum extends DbObject
{

    public string $name;
    public ?string $description = null;
    public ?int $sort_order = null;
    public bool $active;

    protected abstract static function getTableName() : string;

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
        
        $result = $db->fetchSingle($q, ['pk' => $pk]);
        if ($result) {
            return new static($result);
        }

        return null;
    }

    public static function byName(PlmDB $db, int $name): ?self
    {
        $q = 'SELECT * FROM ' . static::getTableName() . 'WHERE name = :name;';

        $result = $db->fetchSingle($q, ['name' => $name]);
        if ($result) {
            return new static($result);
        }

        return null;
    }

    /**
     * @return static[]
     */
    public static function fetchAll(PlmDB $db): ?array
    {
        $q = 'SELECT * FROM ' . static::getTableName() . ';';

        $result = $db->fetchAll($q);
        if ($result) {
            $res = [];

            foreach ($result as $row) {
                $res[] = new static($row);
            }
        }

        return null;
    }
}
