<?php

class PlmMgrVersions extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            $partId = PlmMgrBase::partId($params);

            if ($partId === null) {
                throw new Exception(json_encode($params));
                return $this->getAll($db);
            }
            else {
                return $this->forPartId($db, $partId);
            }
        }
        else {
            return $this->forPk($db, $key);
        }
    }

    protected function getAll(PlmDB $db) : array {
        /** @var PartVersion[] $versions */
        $versions = PartVersion::entries($db);

        $result = [];
        foreach ($versions as $v) {
            $result[] = $this->createVersionData($v);
        }

        return $result;
    }

    protected function forPk(PlmDB $db, int $key) : ?array {
        $v = PartVersion::byPk($db, $key);
        if ($v === null) {
            return null;
        }
        return $this->createVersionData($v);
    }

    protected function forPartId(PlmDB $db, int $partId) : ?array {
        $versions = PartVersion::byPartId($db, $partId);
        $result = [];

        if ($versions !== null) {
            foreach ($versions as $v) {
                $result[] = $this->createVersionData($v);
            }
        }

        return $result;
    }

    /**
     * Creates an array representation of a version
     *
     * @param PartVersion $v
     * @return array
     */
    private function createVersionData($v): array
    {
        $db = PlmDB::get();
        $p = $v->fetchPart($db);
        $s = $v->fetchStatus($db);

        return [
            "id" => $v->pk,
            "ipn" => $p ? $p->ipn : 'n/a',
            "name" => $v->name,
            "major" => $v->major,
            "revision" => $v->revision,
            "status" => $s ? $s->name : 'n/a'
        ];
    }
}