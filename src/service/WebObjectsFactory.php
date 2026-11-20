<?php

if (!defined('DOKU_INC')) {
    die();
}

class WebObjectsFactory {

    /**
     * @param Revision $rev
     */
    public static function buildRevisionData(Revision $rev) : ?array {

        if ($rev === null) return null;

        $db = PlmDB::get();

        $res = [
            "id" => $rev->pk,
            "revisionTag" => $rev->revisionTag,
            "reference" => $rev->reference,
            "statusId" => $rev->statusId,
        ];

        $p = $rev->fetchPart($db);
        $pd = WebObjectsFactory::buildPartData($p);

        foreach ($pd as $key => $val) {
            $res[ 'part_' . $key ] = $val;
        }

        return $res;
    }

    /**
     * 
     */
    public static function buildPartData(Part $part) : ?array {
        $db = PlmDB::get();

        /** @var PartManufacturer[] */
        $manu = $part->fetchManufacturers($db);
        $manuShort = [];

        if ($manu) {
            foreach ($manu as $m) {
                $manuShort[] = [
                    "manufacturerId" => $m->manufacturerId,
                    "mpn" => $m->mpn
                ];
            }
        }

        return [
            "id" => $part->pk,
            "ipn" => $part->ipn,
            "description" => $part->description,
            "categoryId" => $part->categoryId,
            "manufacturer" => $manuShort
        ];
    }

   /**
     * Creates an array representation of a version
     *
     * @param Revision $childVersion
     * @param ?int $parentKey
     * @return array
     */
    public static function buildBOMNodeData(Revision $childVersion, ?int $parentKey): array
    {
        $db = PlmDB::get();

        /** @var Part */
        $part = $childVersion->fetchPart($db);

        /** @var Category */
        $category = $part->fetchCategory($db);

        /** @var ?PartItemRef */
        $itemRef = null;
        if ($parentKey !== null) {
            $itemRef = PartItemRef::entry($db, $parentKey, $childVersion->pk);
        }

        $res = WebObjectsFactory::buildRevisionData($childVersion);
        $variantIDs = [];
        $quantity = 0;
        $designators = '';

        if ($itemRef !== null) {

            if (isset($itemRef->quantity)) $quantity = $itemRef->quantity;
            if (isset($itemRef->designators)) $designators = $itemRef->designators;

            /** @var Variant[] */
            $variants = $itemRef->fetchVariants($db);
            foreach ($variants as $var) {
                $variantIDs[] = $var->pk;
            }

        }

        $res["parentId"] = $parentKey;
        $res["quantity"] = $quantity;
        $res["designators"] = $designators;
        $res["variantIDs"] = $variantIDs;

        return $res;
    }

}
