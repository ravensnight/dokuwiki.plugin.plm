<?php

class PlmMgrStatus extends PlmMgrBase {


    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Status[] */
            $status = Status::fetchAll($db);
            return PlmMgrBase::transformEnumList($status);
        } else {

            /** @var Status */
            $status = Status::byPK($db, $key);
            return PlmMgrBase::transformEnum($status);
        }
    }
}