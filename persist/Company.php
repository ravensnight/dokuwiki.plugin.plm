<?php

class Company extends DbObject {
    public int $id;
    public string $name;
    public string $description;

    /**
     * Assign data to this object from given table fields.
     */
    protected function assign(array $fields)
    {
        $this->id = $fields['id'];
        $this->name = $fields['name'];
        $this->description = $fields['description'];
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void
    {
        if (empty($this->id)) {
            $q = "INSERT INTO plm_companies (name, description) VALUES (:name, :description)";
            $db->querySingle($q, [
                'name' => $this->name,
                'description' => $this->description
            ]);
            $this->id = $db->lastInsertedId('plm_companies');
        } else {
            $q = "UPDATE plm_companies SET name = :name, description = :description WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->id,
                'name' => $this->name,
                'description' => $this->description
            ]);
        }
    }

    /**
     * Delete the referenced object.
     */
    public function delete(PlmDB $db, bool $approve): void
    {
        if (!$approve || empty($this->id)) {
            return;
        }
        $q = "DELETE FROM plm_companies WHERE id = :id";
        $db->querySingle($q, ['id' => $this->id]);
    }

    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_companies ORDER BY name ASC;");
        $items = [];
        foreach ($rows as $row) {
            $items[] = new self($row);
        }
        return $items;
    }

    /**
     * Acquire this object by given primary key.
     */
    public static function byPK(PlmDB $db, int $pk) : ?static
    {
        $result = $db->querySingle("SELECT * FROM plm_companies WHERE id = :pk", ['pk' => $pk]);
        if ($result) {
            return new self($result);
        }
        return null;
    }
}
