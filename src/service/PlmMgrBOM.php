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

    protected function forVersionKey(PlmDB $db, int $key) : ?array {

        $db = PlmDB::get();

        /** @var PartVersion */
        $v = PartVersion::byPK($db, $key);
        if ($v === null) {
            return null;
        }

        $parents = PartItemRef::getAncestors($db, $key);

        // add myself to the list of exclusions
        if ($parents !== null) {
            $parents[] = $key;
        } else {
            $parents = [ $key ];
        }

        /** @var PartVersion[] */
        $children = $v->fetchAllExcept($db, $parents);
        if ($children !== null) {
            $res = [];
            foreach ($children as $c) {
                $res[] = $this->createVersionData($c, $key);
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
    private function createVersionData(PartVersion $v, ?int $key): array
    {
        $db = PlmDB::get();
        $p = $v->fetchPart($db);

        $isChild = $key === null ? false : $v->isChildOf($db, $key);

        return [
            "id" => $v->pk,
            "name" => $v->name,
            "major" => $v->major,
            "revision" => $v->revision,
            "statusId" => $v->statusId,
            "partId" => $v->partId,
            "partIPN" => $p->ipn,
            "partDescription" => $p->description,
            "isChild" => $isChild
        ];
    }    
}