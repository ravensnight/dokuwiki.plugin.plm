<?php

class PartItemRef extends DbObject {

    public int $parentVersionId;
    public int $childVersionId;
    public int $quantity;
    public ?string $designators = null;

    public function assign(array $fields) {
        $this->parentVersionId = $fields['parent_version_id'];
        $this->childVersionId = $fields['child_version_id'];
        $this->quantity = $fields['quantity'];
        $this->designators = $fields['designators'] ?? null;
    }

    public function createNew(int $parentVersionId, int $childVersionId, int $quantity = 1, ?string $designators = null) {
        return new self([
            'parent_version_id' => $parentVersionId,
            'child_version_id' => $childVersionId,
            'quantity' => $quantity,
            'designators' => $designators
        ]);
    }

    public static function byPK(PlmDB $db, int $pk) : ?static {
        /** @var string */
        $q = "SELECT * FROM plm_part_item WHERE id = :pk;";
        $result = $db->querySingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new self($result);
        }

        return null;
    }

    /**
     * @return PartItemRef[]
     */
    public static function byParentVersionId(PlmDB $db, int $parentVersionId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_part_item WHERE parent_version_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $parentVersionId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row ) {
                $res[] = new self($row);
            }

            return $res;
        }

        return null;
    }

    /**
     * @return PartItemRef[]
     */
    public static function byChildVersionId(PlmDB $db, int $childVersionId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_part_item WHERE child_version_id = :pk;";
        $result = $db->fetchAll($q, [
            'pk' => $childVersionId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row) {
                $res[] = new self($row);
            }

            return $res;
        }

        return null;
    }

    /**
     * @return PartItemRef[]
     */
    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_part_item ORDER BY id ASC;");
        $items = [];
        foreach ($rows as $row) {
            $items[] = new self($row);
        }
        return $items;
    }

    public static function addChild(PlmDB $db, int $parentId, int $childId, ?int $amount = null, ?string $designators = null) {
        // Check if the pair already exists
        if (static::hasChild($db, $parentId, $childId)) {
            return;
        }

        // Insert a new child entry
        $q = "INSERT INTO plm_part_item (parent_version_id, child_version_id, quantity, designators) VALUES (:parent, :child, :amount, :designators)";
        $db->querySingle($q, [
            'parent' => $parentId,
            'child' => $childId,
            'amount' => $amount === null ? 1 : $amount,
            'designators' => $designators
        ]);
    }

    public static function removeChild(PlmDB $db, int $parentId, int $childId) {
        // Delete the child entry if the pair exists
        $q = "DELETE FROM plm_part_item WHERE parent_version_id = :parent AND child_version_id = :child";
        $db->querySingle($q, [
            'parent' => $parentId,
            'child' => $childId
        ]);
    }

    /**
     * @var ProductVariant[]
     */
    public function fetchVariants(PlmDB $db) : ?array {

        $q = 'SELECT pv.* FROM plm_product_variant pv JOIN plm_part_item_variant piv ON piv.variant_id = pv.id WHERE piv.part_item_id = :pk;';

        $result = $db->fetchAll($q, [
            'pk' => $this->pk
        ]);

        if ($result) {
            $res = [];
            foreach($result as $row) {
                $res[] = new ProductVariant($row);
            }

            return $res;
        }

        return null;
    }

    /** Get the child item for this reference link */
    public function fetchChild(PlmDB $db) : PartVersion {
        return PartVersion::byPK($db, $this->childVersionId);
    }

    /**
     * Returns true, if this version shall be applied to given variant.
     */
    public function appliesToVariant(PlmDB $db, int $variantId) : bool {
        $q = 'SELECT count(*) as amount FROM plm_part_item_variant WHERE part_item_id = :pk AND variant_id = :variant_id;';

        $result = $db->querySingle($q, [
            'pk' => $this->pk,
            'variant_id' => $variantId
        ]);

        return $result["amount"] > 0;
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void {
        if (empty($this->pk)) {
            $q = "INSERT INTO plm_part_item (parent_version_id, child_version_id, quantity, designators) VALUES (:parent_version_id, :child_version_id, :quantity, :designators)";
            $db->querySingle($q, [
                'parent_version_id' => $this->parentVersionId,
                'child_version_id' => $this->childVersionId,
                'quantity' => $this->quantity,
                'designators' => $this->designators
            ]);
            $this->pk = $db->lastInsertedId('plm_part_item');
        } else {
            $q = "UPDATE plm_part_item SET parent_version_id = :parent_version_id, child_version_id = :child_version_id, quantity = :quantity, designators = :designators WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->pk,
                'parent_version_id' => $this->parentVersionId,
                'child_version_id' => $this->childVersionId,
                'quantity' => $this->quantity,
                'designators' => $this->designators // Fixed typo: was $this->designators
            ]);
        }
    }

    /**
     * Delete the referenced object
     */
    public function delete(PlmDB $db, bool $approve): void {
        if (!$approve || empty($this->pk)) {
            return;
        }
        $q = "DELETE FROM plm_part_item WHERE id = :id";
        $db->querySingle($q, ['id' => $this->pk]);
    }

    /** 
     * Check, if there is a given child assigned to parent 
     */
    public static function hasChild(PlmDB $db, int $parent, int $child) : bool {
        $q = "SELECT count(*) as amount FROM plm_part_item WHERE parent_version_id = :parentId AND child_version_id = :childId";

        $result = $db->querySingle($q, [
            "parentId" => $parent,
            "childId" => $child
        ]);
        
        /** @var bool */
        $res = ($result["amount"] > 0);
        return $res;
    }

    /**
     * Get all ancestors of a given child ID (recursive)
     * This finds all parents (direct and indirect) of a specific child version ID
     * 
     * @param PlmDB $db
     * @param int $childId
     * @return PartItemRef[]
     */
    public static function getAncestors(PlmDB $db, int $childId): ?array
    {
        /** @var string */
        $q = "
            WITH RECURSIVE_PARENT AS (
                SELECT 
                    parent_version_id, 
                    child_version_id,
                    0 as depth,
                    CAST(child_version_id AS TEXT) as path
                FROM plm_part_item 
                WHERE child_version_id = :child_id
                
                UNION ALL
                
                SELECT 
                    p.parent_version_id,
                    p.child_version_id,
                    r.depth + 1,
                    r.path || ',' || p.child_version_id
                FROM plm_part_item p
                JOIN RECURSIVE_PARENT r ON p.child_version_id = r.parent_version_id
                WHERE r.path NOT LIKE '%' || p.child_version_id || '%'
                AND r.depth < 10
            )

            SELECT DISTINCT parent_version_id, child_version_id, depth
            FROM RECURSIVE_PARENT
            ORDER BY depth;
        ";

        $result = $db->fetchAll($q, [
            'child_id' => $childId
        ]);

        if ($result) {
            $res = [];
            foreach ($result as $row) {
                $res[] = $row['parent_version_id'];
            }            
            return $res;
        }

        return null;
    }
}