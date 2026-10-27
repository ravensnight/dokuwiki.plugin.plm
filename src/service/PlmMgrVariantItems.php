<?php

class PlmMgrVariantItems extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $pk = PlmMgrBase::id($params);

        if ($pk === null) {
            $variantId = PlmMgrBase::variantId($params);

            if ($variantId === null) {
                return $this->getAll($db);
            }
            else {
                return $this->forVariantId($db, $variantId);
            }
        }
        else {
            return $this->forPk($db, $pk);
        }
    }

    /**
     * Creates an array representation of a variant item.
     *
     * @param VariantItemRef $v
     * @return array
     */
    private function createItemData(PlmDB $db, VariantItemRef $v): array
    {
        $version = $v->fetchChild($db);
        $part = $version->fetchPart($db);

        return [
            "id" => $v->pk,
            "variantId" => $v->productVariantId,
            "versionId" => $v->childVersionId,
            "quantity" => $v->quantity,
            "versionName" => $version->name,
            "versionMajor" => $version->major,
            "versionRevision" => $version->revision,
            "partId" => $version->partId,
            "partIPN" => $part->ipn,
            "partDescription" => $part->description
        ];
    }

    public function forPk(PlmDB $db, int $pk): array
    {
        $ref = VariantItemRef::byPk($db, $pk);
        return $this->createItemData($db, $ref);
    }

    public function getAll(PlmDB $db) {
        $result = VariantItemRef::entries($db);
        $res = [];
        if ($result) {
            foreach ($result as $item) {
                $res[] = $this->createItemData($db, $item);
            }
        }

        return $res;
    }

    public function forVariantId(PlmDB $db, int $variantId) {
        $result = VariantItemRef::byProductVariantId($db, $variantId);
        $res = [];
        if ($result) {
            foreach ($result as $item) {
                $res[] = $this->createItemData($db, $item);
            }
        }

        return $res;
    }
}