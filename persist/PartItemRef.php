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
        $this->designators = $fields['desginators'] ?? null;
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
        $q = "SELECT * FROM  plm_part_item WHERE id = :pk;";
        $result = $db->fetchSingle($q, [
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
        $q = "SELECT * FROM  plm_part_item WHERE parent_version_id = :pk;";
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
        $q = "SELECT * FROM  plm_part_item WHERE child_version_id = :pk;";
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

    public function fetchChild(PlmDB $db) : PartVersion {
        return PartVersion::byPK($db, $this->childVersionId);
    }

    public function fetchParent(PlmDB $db): PartVersion {
        return PartVersion::byPK($db, $this->parentVersionId);
    }

    public function appliesToVariant(PlmDB $db, int $variantPK) : bool {
        $q = 'SELECT COUNT(*) FROM plm_part_item_variant WHERE part_item_id = :itemLink AND variant_id = :variantPk;';

        $result = $db->fetchSingle($q, [
            'itemLink' => $this->pk,
            'variantPk' => $variantPK
        ]);

        if (empty($result)) return false;
        return (reset($result) > 0);
    }
}
