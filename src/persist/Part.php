<?php

class Part extends DbObject {
    
    public string $ipn;
    public int $categoryId;

    public ?string $description = null;
    
    public function assign(array $fields)
    {
        $this->ipn = $fields['ipn'];
        $this->categoryId = $fields['category_id'];
        $this->description = $fields['description'];
    }

    /**
     * Create a new part with all attributes as parameters
     */
    public static function create(PlmDB $db, int $pk, string $ipn, int $categoryId, ?string $description = null) : self {
        $part = new self([
            'id' => $pk,
            'ipn' => $ipn,
            'category_id' => $categoryId,
            'description' => $description
        ]);
        
        // Save to database
        $q = "INSERT INTO plm_part (id, ipn, category_id, description) VALUES (:id, :ipn, :category_id, :description)";
        $db->querySingle($q, [
            'id' => $pk,
            'ipn' => $ipn,
            'category_id' => $categoryId,
            'description' => $description
        ]);
        
        return $part;
    }

    public static function createNew(Category $category, string $ipn, ?string $description) : self {
        return new self([
            'ipn' => $ipn,
            'category_id' => $category->pk,
            'description' => $description
        ]);
    }

    public static function byPK(PlmDB $db, int $pk): ?static
    {

        /** @var string */
        $q = "SELECT * FROM  plm_part WHERE id = :pk;";
        $result = $db->querySingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new Part($result);
        }

        return null;
    }

    public static function byIPN(PlmDB $db, string $ipn) : ?self {

        /** @var string */
        $q = "SELECT * FROM  plm_part WHERE ipn = :ipn;";
        $result = $db->querySingle($q, [
            'ipn' => $ipn
        ]);

        if ($result) {
            return new Part($result);
        }

        return null;
    }

    /**
     * @return Part[]
     */
    public static function entries(PlmDB $db): array
    {
        $rows = $db->fetchAll('SELECT * FROM plm_part ORDER BY ipn ASC;');
        $parts = [];

        foreach ($rows as $row) {
            $parts[] = new Part($row);
        }

        return $parts;
    }

    /**
     * @return PartVersion[]
     */
    public function fetchVersions(PlmDB $db) : ?array {
        return PartVersion::byPartId($db, $this->pk);
    }

    public function fetchCategory(PlmDB $db) : ?Category {
        return Category::byPK($db, $this->categoryId);
    }

    /**
     * Save current part attributes to database
     * If pk is empty, creates a new entry; otherwise updates existing
     */
    public function save(PlmDB $db): void
    {
        if (empty($this->pk)) {
            // Create new part - get a new ID and insert
            $q = "INSERT INTO plm_part (ipn, category_id, description) VALUES (:ipn, :category_id, :description)";

            $db->querySingle($q, [
                'ipn' => $this->ipn,
                'category_id' => $this->categoryId,
                'description' => $this->description
            ]);

            // Get the newly created ID and assign it to this object
            // This assumes that the database returns the last inserted ID
            $this->pk = $db->lastInsertedId('plm_part');
        } else {
            // Update existing part
            $q = "UPDATE plm_part SET ipn = :ipn, category_id = :category_id, description = :description WHERE id = :id";

            $db->querySingle($q, [
                'id' => $this->pk,
                'ipn' => $this->ipn,
                'category_id' => $this->categoryId,
                'description' => $this->description
            ]);
        }
    }

    /**
     * Save current part attributes to database
     * If pk is empty, creates a new entry; otherwise updates existing
     */
    public function delete(PlmDB $db, bool $aprove): void {
        if ($aprove == false) return;
        if (empty($this->pk)) return;

        $q = "DELETE FROM plm_part WHERE id = :pk";
        $db->querySingle($q, [
            "pk" => $this->pk
        ]);
    }
}
