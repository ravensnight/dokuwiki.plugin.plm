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
        $result = $db->fetchSingle($q, [
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
        $result = $db->fetchSingle($q, [
            'ipn' => $ipn
        ]);

        if ($result) {
            return new Part($result);
        }

        return null;
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
}
