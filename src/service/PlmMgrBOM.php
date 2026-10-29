<?php

class PlmMgrBOM extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params); // version id

        if ($key === null) {
            return $this->getAll($db);
        }
        else {
            return $this->forVersionKey($db, $key);
        }
    }

    protected function getAll(PlmDB $db) : array {
        /** @var PartVersion[] $versions */
        $versions = PartVersion::entries($db);

        $result = [];
        foreach ($versions as $v) {
            $result[] = $this->createVersionData($v, null);
        }

        return $result;
    }

    protected function forVersionKey(PlmDB $db, int $parentKey) : ?array {

        $db = PlmDB::get();

        /** @var PartVersion */
        $parentVersion = PartVersion::byPK($db, $parentKey);
        if ($parentVersion === null) {
            return null;
        }

        $parents = PartItemRef::getAncestors($db, $parentVersion->pk);

        // add myself to the list of exclusions
        if ($parents !== null) {
            $parents[] = $parentVersion->pk;
        } else {
            $parents = [$parentVersion->pk ];
        }

        /** @var PartVersion[] */
        $children = $parentVersion->fetchAllExcept($db, $parents);
        if ($children !== null) {
            $res = [];
            foreach ($children as $child) {
                $res[] = $this->createVersionData($child, $parentVersion->pk);
            }

            return $res;
        }

        return null;
    }

    /**
     * Creates an array representation of a version
     *
     * @param PartVersion $v
     * @return array
     */
    private function createVersionData(PartVersion $childVersion, ?int $parentKey): array
    {
        $db = PlmDB::get();

        $isChild = PartItemRef::hasChild($db, $parentKey, $childVersion->pk);
        $part = $childVersion->fetchPart($db);
        return [
            "id" => $childVersion->pk,
            "name" => $childVersion->name,
            "major" => $childVersion->major,
            "revision" => $childVersion->revision,
            "statusId" => $childVersion->statusId,
            "partId" => $childVersion->partId,
            "partIPN" => $part->ipn,
            "partDescription" => $part->description,
            "isChild" => $isChild ? 1 : 0
        ];
    }    

    #[Override]
    public function update(array $inputData, ?array $attrNames): array
    {
        $id = static::id($inputData);
        if ($id === null) {
            throw new Exception("Key/ID mus not be NULL to update a bom element.");
        }

        if (!isset($inputData['command'])) {
            throw new Exception("Command is not set for BOM update.");
        }
        if (!isset($inputData['childId'])) {
            throw new Exception("childId is not set for BOM update.");
        }

        $cmd = $inputData['command'];
        $cid = $inputData['childId'];

        $db = PlmDB::get();

        switch ($cmd)  {
            case 'add' :
                PartItemRef::addChild($db, $id, $cid);
                break;

            case 'remove' :
                PartItemRef::removeChild($db, $id, $cid);
                break;

            default:
                throw new Exception("Unknown command for BOM update: " . $cmd);
        }

        return [];
    }    
}