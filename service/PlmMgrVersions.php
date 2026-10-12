<?php

class PlmMgrVersions extends PlmMgrBase {

    #[Override]
    public function getData(): mixed
    {
        $db = PlmDB::get();

        /** @var PartVersion[] $versions */
        $versions = PartVersion::entries($db);

        $result = [];
        foreach ($versions as $v) {

            $p = $v->fetchPart($db);
            $s = $v->fetchStatus($db);

            $result[] = [
                "id" => $v->pk,
                "ipn" => $p ? $p->ipn : 'n/a',
                "name" => $v->name,
                "major" => $v->major,
                "revision" => $v->revision,
                "status" => $s ? $s->name : 'n/a'
            ];
        }

        return $result;
    }
}