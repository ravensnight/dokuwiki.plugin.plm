<?php

class PlmMgrBOM extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = $params['parentId'];

        if ($key === null) {
            return $this->getAll($db);
        }
        else {
            return $this->forVersionKey($db, $key);
        }
    }

    protected function getAll(PlmDB $db) : array {
        /** @var Revision[] $versions */
        $versions = Revision::entries($db);

        $result = [];
        foreach ($versions as $v) {
            $result[] = $this->createVersionData($v, null);
        }

        return $result;
    }

    protected function forVersionKey(PlmDB $db, int $parentKey) : ?array {

        $db = PlmDB::get();

        /** @var Revision */
        $parentVersion = Revision::byPK($db, $parentKey);
        if ($parentVersion === null) {
            return null;
        }

        $parents = PartItemRef::getAncestors($db, $parentVersion->pk);

        // add myself to the list of exclusions
        if ($parents !== null) {
            $parents[] = $parentVersion->pk;
        } else {
            $parents = [ $parentVersion->pk ];
        }

        /** @var Revision[] */
        $children = $parentVersion->fetchAllExcept($db, $parents);
        if ($children !== null) {
            $res = [];
            foreach ($children as $child) {
                $res[] = $this->createVersionData($child, $parentVersion->pk);
            }

            return $res;
        }

        return null;
    }

    /**
     * Creates an array representation of a version
     *
     * @param Revision
     * @param ?int
     * @return array
     */
    private function createVersionData(Revision $childVersion, ?int $parentKey): array
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

        /** @var Variant[] */
        $variantIDs = [];
        $quantity = 0;
        $designators = '';

        if ($itemRef !== null) {
            if (isset($itemRef->quantity)) $quantity = $itemRef->quantity;
            if (isset($itemRef->designators)) $designators = $itemRef->designators;

            $variants = $itemRef->fetchVariants($db);
            foreach ($variants as $var) {
                $variantIDs[] = $var->pk;
            }
        }

        return [
            "id" => $childVersion->pk,
            "parentId" => $parentKey,
            "revisionTag" => $childVersion->revisionTag,
            "partId" => $childVersion->partId,
            "partIPN" => $part->ipn,
            "partDescription" => $part->description,
            "partCategoryId" => $category->pk,
            "statusId" => $childVersion->statusId,
            "quantity" => $quantity,
            "designators" => $designators,
            'variantIDs' => $variantIDs
        ];
    }

    #[Override]
    public function update(array $inputData, ?array $attrNames): array
    {
        $db = PlmDB::get();

        $childId = static::id($inputData);
        if ($childId === null) {
            throw new Exception("Child Key/ID must not be NULL to update a bom element.");
        }

        if (!isset($inputData['parentId'])) {
            throw new Exception("Parent Key/ID must not be NULL to update a bom element.");
        }

        $parentId = $inputData['parentId'];

        // Assign quantity
        $quantity = $inputData['quantity'];
        if ($quantity !== null) {
            if ($quantity > 0) {
                PartItemRef::addChild($db, $parentId, $childId, $quantity);
            }
            else {
                PartItemRef::removeChild($db, $parentId, $childId);
            }
        }

        // assign variant IDs
        $variantIDs = $inputData['variantIDs'];
        if ($variantIDs !== null) {
            // First remove all existing variant assignments for this part item
            $itemRef = PartItemRef::entry($db, $parentId, $childId);
            if ($itemRef !== null) {
            
                // Get all current variants for this part item
                $currentVariants = $itemRef->fetchVariants($db);
                if ($currentVariants !== null) {
                    foreach ($currentVariants as $currentVariant) {
                        PartItemVariant::removeVariant($db, $itemRef->pk, $currentVariant->pk);
                    }
                }

                // Then assign new variants
                foreach ($variantIDs as $variantId) {
                    PartItemVariant::assignVariant($db, $itemRef->pk, $variantId);
                }
            }
        }

        return $inputData;
    }
}
?>