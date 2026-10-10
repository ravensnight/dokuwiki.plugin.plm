<?php

class PartListProvider extends Provider {

    public function provide(array $context) : array {
        $db = PlmDB::get();
        $res = [];

        /** @var Part[] $list */
        $list = Part::entries($db);

        foreach ($list as $part) {

            $cat = $part->fetchCategory($db);
            $entry = [
                "id" => $part->pk,
                "ipn" => $part->ipn,
                "description" => $part->description,
                "category" => $cat ? $cat->name : 'not found'
            ];

            $res[] = $entry;
        }

        return $res;
    }
}
