<?php

class PlmMgrParts extends PlmMgrBase {

    #[Override]
    public function getData(): mixed
    {
        $db = PlmDB::get();

        /** @var Part[] $parts */
        $parts = Part::entries($db);

        $result = [];
        foreach ($parts as $p) {

            $cat = $p->fetchCategory($db);

            $result[] = [
                "id" => $p->pk,
                "ipn" => $p->ipn,
                "description" => $p->description,
                "category" => $cat ? $cat->name : 'n/a'
            ];
        }

        return $result;
    }
}