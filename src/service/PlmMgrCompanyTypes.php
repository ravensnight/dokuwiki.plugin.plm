<?php

class PlmMgrCompanyTypes extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = PlmMgrBase::id($params);

        if ($key === null) {
            /** @var CompanyType[] */
            $list = CompanyType::fetchAll($db);
            return PlmMgrBase::transformEnumList($list);
        } else {

            /** @var CompanyType */
            $elem = CompanyType::byPK($db, $key);
            return PlmMgrBase::transformEnum($elem);
        }
    }
}