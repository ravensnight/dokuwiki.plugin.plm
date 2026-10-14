<?php

class PlmMgrCategories extends PlmMgrBase {


    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var Category[] */
            $list = Category::fetchAll($db);
            return PlmMgrBase::transformEnumList($list);        
        } else {

            /** @var Category */
            $cat = Category::byPK($db, $key);
            return PlmMgrBase::transformEnum($cat);
        }
    }
}