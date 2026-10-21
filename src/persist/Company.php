<?php

class Company extends DbObject {

    public string $name;
    public int $type_id;
    public string $description;

    /**
     * Assign data to this object from given table fields.
     */
    protected function assign(array $fields)
    {
        $this->name = $fields['name'];
        $this->description = $fields['description'];
        $this->type_id = $fields['type_id'];
    }

    /**
     * Create a new company instance
     *
     * @param string $name
     * @param string $description
     * @return Company
     */
    public static function createNew(CompanyType $type, string $name, string $description): self
    {
        return new self([
            'name' => $name,
            'description' => $description,
            'type_id' => $type->pk
        ]);
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void
    {
        if (empty($this->pk)) {
            $q = "INSERT INTO plm_companies (name, description, type_id) VALUES (:name, :description, :type_id)";
            $db->querySingle($q, [
                'name' => $this->name,
                'description' => $this->description,
                'type_id' => $this->type_id
            ]);

            $this->pk = $db->lastInsertedId('plm_companies');

        } else {
            $q = "UPDATE plm_companies SET name = :name, description = :description, type_id = :type_id WHERE id = :pk";
            $db->querySingle($q, [
                'pk' => $this->pk,
                'name' => $this->name,
                'description' => $this->description,
                'type_id' => $this->type_id
            ]);
        }
    }

    /**
     * Delete the referenced object.
     */
    public function delete(PlmDB $db, bool $approve): void
    {
        if (!$approve || empty($this->pk)) {
            return;
        }
        $q = "DELETE FROM plm_companies WHERE id = :pk";
        $db->querySingle($q, ['pk' => $this->pk]);
    }

    /**
     * Get all entries
     */
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

    /**
     * Get the type.
     */
    public function fetchType(PlmDB $db) : CompanyType {
        return CompanyType::byPK($db, $this->type_id );
    }
}
