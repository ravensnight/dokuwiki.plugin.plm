<?php

class Product extends DbObject {
    
    public string $name;
    public ?string $description = null;

    /**
     * Hidden constructor
     */
    protected function assign(array $fields) {
        $this->name = $fields['product_id'];
        $this->description = $fields['description'];
    }

    /**
     * Create new
     */
    public static function createNew(string $name, ?string $description = null) : self {
        return new self([
            'product_id' => $name,
            'description' => $description
        ]);
    }

    /**
     * Find product by name
     */
    public static function byPK(PlmDB $db, int $pk): ?static {
        $result = $db->querySingle("SELECT * FROM plm_product WHERE id = :pk", [
            'pk' => $pk
        ]);

        if ($result) {
            /** @var Product */
            return new Product($result);
        }

        return null;
    }

    /**
     * Find product by name
     */
    public static function byName(PlmDB $db, string $name) : ?self {
        $result = $db->querySingle("SELECT * FROM plm_product WHERE product_id = :product_id", [
            'product_id' => $name
        ]);

        if ($result) {
            return new Product($result);
        }

        return null;        
    }

    /**
     * @return ProductVariant[]
     */
    public function fetchVariants(PlmDB $db) : ?array {
        return ProductVariant::byProductId($db, $this->pk);
    }

    /**
     * Get all products
     * @return Product[]
     */
    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_product ORDER BY product_id ASC;");
        $res = [];
        foreach ($rows as $row) {
            $res[] = new Product($row);
        }
        return $res;
    }

    #[Override]
    public function save(PlmDB $db): void
    {
        if (empty($this->pk)) {
            $q = "INSERT INTO plm_product (product_id, description) VALUES (:product_id, :description)";
            $db->querySingle($q, [
                'product_id' => $this->name,
                'description' => $this->description
            ]);

            $this->pk = $db->lastInsertedId('plm_product');

        } else {
            $q = "UPDATE plm_product SET product_id = :product_id, description = :description WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->pk,
                'product_id' => $this->name,
                'description' => $this->description
            ]);
        }
    }

    #[Override]
    public function delete(PlmDB $db, bool $approve): void
    {
        if (!$approve || empty($this->pk)) {
            return;
        }
        $q = "DELETE FROM plm_product WHERE id = :id";
        $db->querySingle($q, ['id' => $this->pk]);
    }
}
