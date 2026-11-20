<?php

class PartSupplier extends DbObject {

    public int $id;
    public int $mpnId;
    public int $supplierId;
    public ?string $spn;
    public ?string $description;

    public function assign(array $fields) {
        $this->id = $fields['id'];
        $this->mpnId = $fields['mpn_id'];
        $this->supplierId = $fields['supplier_id'];
        $this->spn = $fields['spn'];
        $this->description = $fields['description'];
    }

    public static function createNew(int $mpnId, int $supplierId, ?string $spn = null, ?string $description = null) : self {
        return new self([
            'mpn_id' => $mpnId,
            'supplier_id' => $supplierId,
            'spn' => $spn,
            'description' => $description
        ]);
    }

    public static function byPK(PlmDB $db, int $pk) : ?static {
        /** @var string */
        $q = "SELECT * FROM plm_supplier_part WHERE id = :pk;";
        $result = $db->querySingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new self($result);
        }

        return null;
    }

    /**
     * @return PartSupplier[]
     */
    public static function byMpnId(PlmDB $db, int $mpnId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_supplier_part WHERE mpn_id = :mpn_id;";
        $result = $db->fetchAll($q, [
            'mpn_id' => $mpnId
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
     * @return PartSupplier[]
     */
    public static function bySupplierId(PlmDB $db, int $supplierId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_supplier_part WHERE supplier_id = :supplier_id;";
        $result = $db->fetchAll($q, [
            'supplier_id' => $supplierId
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
     * @return PartSupplier[]
     */
    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_supplier_part ORDER BY id ASC;");
        $items = [];
        foreach ($rows as $row) {
            $items[] = new self($row);
        }
        return $items;
    }

    /**
     * Check if a supplier is assigned to a manufacturer part
     */
    public static function hasSupplier(PlmDB $db, int $mpnId, int $supplierId) : bool {
        $q = "SELECT count(*) as amount FROM plm_supplier_part WHERE mpn_id = :mpn_id AND supplier_id = :supplier_id";

        $result = $db->querySingle($q, [
            'mpn_id' => $mpnId,
            'supplier_id' => $supplierId
        ]);

        return $result["amount"] > 0;
    }

    /**
     * Assign a supplier to a manufacturer part
     */
    public static function assignSupplier(PlmDB $db, int $mpnId, int $supplierId, ?string $spn = null, ?string $description = null) {
        // Check if the pair already exists
        if (static::hasSupplier($db, $mpnId, $supplierId)) {
            // Do nothing - already assigned
            return;
        } else {
            // Insert a new assignment
            $q = "INSERT INTO plm_supplier_part (mpn_id, supplier_id, spn, description) VALUES (:mpn_id, :supplier_id, :spn, :description)";
            $db->querySingle($q, [
                'mpn_id' => $mpnId,
                'supplier_id' => $supplierId,
                'spn' => $spn,
                'description' => $description
            ]);
        }
    }

    /**
     * Remove a supplier assignment from a manufacturer part
     */
    public static function removeSupplier(PlmDB $db, int $mpnId, int $supplierId) {
        // Delete the assignment if it exists
        $q = "DELETE FROM plm_supplier_part WHERE mpn_id = :mpn_id AND supplier_id = :supplier_id";
        $db->querySingle($q, [
            'mpn_id' => $mpnId,
            'supplier_id' => $supplierId
        ]);
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void {
        if (empty($this->id)) {
            $q = "INSERT INTO plm_supplier_part (mpn_id, supplier_id, spn, description) VALUES (:mpn_id, :supplier_id, :spn, :description)";
            $db->querySingle($q, [
                'mpn_id' => $this->mpnId,
                'supplier_id' => $this->supplierId,
                'spn' => $this->spn,
                'description' => $this->description
            ]);
            $this->id = $db->lastInsertedId('plm_supplier_part');
        } else {
            $q = "UPDATE plm_supplier_part SET mpn_id = :mpn_id, supplier_id = :supplier_id, spn = :spn, description = :description WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->id,
                'mpn_id' => $this->mpnId,
                'supplier_id' => $this->supplierId,
                'spn' => $this->spn,
                'description' => $this->description
            ]);
        }
    }

    /**
     * Delete the referenced object
     */
    public function delete(PlmDB $db, bool $approve): void {
        if (!$approve || empty($this->id)) {
            return;
        }
        $q = "DELETE FROM plm_supplier_part WHERE id = :id";
        $db->querySingle($q, ['id' => $this->id]);
    }

    /**
     * Get the manufacturer part this supplier assignment refers to
     */
    public function fetchManufacturerPart(PlmDB $db): ?PartManufacturer {
        return PartManufacturer::byPK($db, $this->mpnId);
    }

    /**
     * Get the supplier this assignment refers to
     */
    public function fetchSupplier(PlmDB $db): ?Company {
        return Company::byPK($db, $this->supplierId);
    }
}
?>