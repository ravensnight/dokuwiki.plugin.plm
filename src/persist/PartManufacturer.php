<?php

class PartManufacturer extends DbObject {

    public int $id;
    public int $partId;
    public int $manufacturerId;
    public ?string $mpn;
    public ?string $description;

    public function assign(array $fields) {
        $this->id = $fields['id'];
        $this->partId = $fields['part_ipn_id'];
        $this->manufacturerId = $fields['manufacturer_id'];
        $this->mpn = $fields['mpn'];
        $this->description = $fields['description'];
    }

    public static function createNew(int $partId, int $manufacturerId, ?string $mpn = null, ?string $description = null) : self {
        return new self([
            'part_ipn_id' => $partId,
            'manufacturer_id' => $manufacturerId,
            'mpn' => $mpn,
            'description' => $description
        ]);
    }

    public static function byPK(PlmDB $db, int $pk) : ?static {
        /** @var string */
        $q = "SELECT * FROM plm_manufacturer_part WHERE id = :pk;";
        $result = $db->querySingle($q, [
            'pk' => $pk
        ]);

        if ($result) {
            return new self($result);
        }

        return null;
    }

    /**
     * @return PartManufacturer[]
     */
    public static function byPartId(PlmDB $db, int $partId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_manufacturer_part WHERE part_ipn_id = :part_ipn_id;";
        $result = $db->fetchAll($q, [
            'part_ipn_id' => $partId
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
     * @return PartManufacturer[]
     */
    public static function byManufacturerId(PlmDB $db, int $manufacturerId): ?array
    {
        /** @var string */
        $q = "SELECT * FROM plm_manufacturer_part WHERE manufacturer_id = :manufacturer_id;";
        $result = $db->fetchAll($q, [
            'manufacturer_id' => $manufacturerId
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
     * @return PartManufacturer[]
     */
    public static function entries(PlmDB $db): array {
        $rows = $db->fetchAll("SELECT * FROM plm_manufacturer_part ORDER BY id ASC;");
        $items = [];
        foreach ($rows as $row) {
            $items[] = new self($row);
        }
        return $items;
    }

    /**
     * Check if a manufacturer is assigned to a part
     */
    public static function hasManufacturer(PlmDB $db, int $partId, int $manufacturerId) : bool {
        $q = "SELECT count(*) as amount FROM plm_manufacturer_part WHERE part_ipn_id = :part_ipn_id AND manufacturer_id = :manufacturer_id";

        $result = $db->querySingle($q, [
            'part_ipn_id' => $partId,
            'manufacturer_id' => $manufacturerId
        ]);

        return $result["amount"] > 0;
    }

    /**
     * Assign a manufacturer to a part
     */
    public static function assignManufacturer(PlmDB $db, int $partId, int $manufacturerId, ?string $mpn = null, ?string $description = null) {
        // Check if the pair already exists
        if (static::hasManufacturer($db, $partId, $manufacturerId)) {
            // Do nothing - already assigned
            return;
        } else {
            // Insert a new assignment
            $q = "INSERT INTO plm_manufacturer_part (part_ipn_id, manufacturer_id, mpn, description) VALUES (:part_ipn_id, :manufacturer_id, :mpn, :description)";
            $db->querySingle($q, [
                'part_ipn_id' => $partId,
                'manufacturer_id' => $manufacturerId,
                'mpn' => $mpn,
                'description' => $description
            ]);
        }
    }

    /**
     * Remove a manufacturer assignment from a part
     */
    public static function removeManufacturer(PlmDB $db, int $partId, int $manufacturerId) {
        // Delete the assignment if it exists
        $q = "DELETE FROM plm_manufacturer_part WHERE part_ipn_id = :part_ipn_id AND manufacturer_id = :manufacturer_id";
        $db->querySingle($q, [
            'part_ipn_id' => $partId,
            'manufacturer_id' => $manufacturerId
        ]);
    }

    /**
     * Update or create an object
     */
    public function save(PlmDB $db): void {
        if (empty($this->id)) {
            $q = "INSERT INTO plm_manufacturer_part (part_ipn_id, manufacturer_id, mpn, description) VALUES (:part_ipn_id, :manufacturer_id, :mpn, :description)";
            $db->querySingle($q, [
                'part_ipn_id' => $this->partId,
                'manufacturer_id' => $this->manufacturerId,
                'mpn' => $this->mpn,
                'description' => $this->description
            ]);
            $this->id = $db->lastInsertedId('plm_manufacturer_part');
        } else {
            $q = "UPDATE plm_manufacturer_part SET part_ipn_id = :part_ipn_id, manufacturer_id = :manufacturer_id, mpn = :mpn, description = :description WHERE id = :id";
            $db->querySingle($q, [
                'id' => $this->id,
                'part_ipn_id' => $this->partId,
                'manufacturer_id' => $this->manufacturerId,
                'mpn' => $this->mpn,
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
        $q = "DELETE FROM plm_manufacturer_part WHERE id = :id";
        $db->querySingle($q, ['id' => $this->id]);
    }

    /**
     * Get the part this manufacturer assignment refers to
     */
    public function fetchPart(PlmDB $db): ?Part {
        return Part::byPK($db, $this->partId);
    }
}
?>