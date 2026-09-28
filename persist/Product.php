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

    #[Override]
    public function save(PlmDB $db)
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function delete(PlmDB $db, bool $aprove)
    {
        throw new \Exception('Not implemented');
    }
}
