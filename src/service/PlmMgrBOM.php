<?php

class PlmMgrBOM extends PlmMgrBase {

    #[Override]
    public function getData(array $params): ?array
    {
        $db = PlmDB::get();
        $key = $params['parentId'];
        $pid = $this->id($params);

        if ($pid !== null) {
            return $this->getChildren($db, $pid);
        }
        elseif ($key !== null) {
            return $this->getAssignableRevisions($db, $key);
        }
        else {
            return $this->getAll($db);
        }
    }

    protected function getAll(PlmDB $db) : array {
        /** @var Revision[] $versions */
        $versions = Revision::entries($db);

        $result = [];
        foreach ($versions as $rev) {
            $result[] = WebObjectsFactory::buildBOMNodeData($rev, null);
        }

        return $result;
    }

    /**
     * Find all revisions that can be assigned to the given $parentKey
     */
    protected function getChildren(PlmDB $db, int $parentId) : ?array {

        $db = PlmDB::get();

        /** @var Revision */
        $parentVersion = Revision::byPk($db, $parentId);
        if ($parentVersion === null) {
            return null;
        }

        /** @var PartItemRef[] */
        $childrenRef = $parentVersion->fetchChildren($db);

        if ($childrenRef !== null) {
            $res = [];
            foreach ($childrenRef as $child) {

                $rev = $child->fetchChild($db);
                if ($rev === null) continue;

                $res[] = WebObjectsFactory::buildBOMNodeData($rev, $parentId);
            }

            return $res;
        }

        return NULL;
    }    
    /**
     * Find all revisions that can be assigned to the given $parentKey
     */
    protected function getAssignableRevisions(PlmDB $db, int $parentKey) : ?array {

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
                $res[] = WebObjectsFactory::buildBOMNodeData($child, $parentVersion->pk);
            }

            return $res;
        }

        return null;
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