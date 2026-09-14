<?php

class PartVersion extends DbObject {
    
    public string $name;
    public int $partId;
    public int $statusId;

    public int $major = -1;
    public int $revision = -1;

    public function assign(array $fields) {
        $this->name = $fields['version_id'];
        $this->major = $fields['major'];
        $this->revision = $fields['revision'];
        $this->partId = $fields['part_id'];
        $this->statusId = $fields['status_id'];
    }

    public static function createNew(Part $part, Status $status, string $name, int $major = 1, int $revision = 1) : self {
        return new self([
            'version_id' => $name, 
            'major' => $major,
            'revision' => $revision,
            'part_id' => $part->pk,
            'status_id' => $status->pk
        ]);
    }

    public static function byPK(PlmDB $db, int $pk): ?static
    {
        $result = $db->fetchSingle("SELECT * FROM  plm_part_version WHERE id = :pk;", [
            'pk' => $pk
        ]);

        if ($result) {
            return new PartVersion($result);
        }

        return null;
    }

    public static function byName(PlmDB $db, string $name): ?self
    {

        $result = $db->fetchSingle("SELECT * FROM  plm_part_version WHERE version_id = :version_id;", [
            'version_id' => $name
        ]);

        if ($result) {
            return new PartVersion($result);
        }

        return null;
    }

    /**
     * @return PartVersion[]
     */
    public static function byPartId(PlmDB $db, int $partId): ?array {

        $result = $db->fetchAll("SELECT * FROM  plm_part_version WHERE part_id = :part_id;", [
            'part_id' => $partId
        ]);

        if ($result) {
            $res = [];

            foreach ($result as $row) {
                $res[] = new PartVersion($row);
            }

            return $res;
        }

        return null;
    }

    /**
     * @return PartItemRef[]
     */
    public function fetchChildren(PlmDB $db) : ?array {    
        return PartItemRef::byParentVersionId($db, $this->pk);
    }

    /**
     * @return PartItemRef[]
     */
    public function fetchParents(PlmDB $db): ?array
    {
        return PartItemRef::byChildVersionId($db, $this->pk);
    }

    /**
     * The part this version belongs to
     */
    public function fetchPart(PlmDB $db) : Part {
        return Part::byPK($db, $this->partId);
    }

    /**
     * The part this version belongs to
     */
    public function fetchStatus(PlmDB $db): Status
    {
        return Status::byPK($db, $this->statusId);
    }
}
