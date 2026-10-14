<?php

class PlmMgrStatus extends PlmMgrBase {


    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Status[] */
            $categories = Status::fetchAll($db);
            return PlmMgrBase::transformEnumList($categories);
        } else {

            /** @var Status */
            $cat = Status::byPK($db, $key);
            return PlmMgrBase::transformEnum($cat);
        }
    }
}