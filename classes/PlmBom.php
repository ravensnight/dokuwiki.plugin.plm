<?php

/**
 * PLM BOM renderer.
 *
 * BOM root:
 *   /plm:bom > PRODUCT-VARIANT
 *
 * Product variant:
 *   plm_product_variant.variant_id
 *
 * First BOM level:
 *   plm_product_variant_item
 *     variant_id -> plm_product_variant.variant_id
 *     version_id -> plm_part_version.version_id
 *     quantity
 *
 * Recursive BOM:
 *   plm_part_item
 *     parent_version_id -> plm_part_version.version_id
 *     child_version_id  -> plm_part_version.version_id
 *     quantity
 *     designators
 *     variants -> plm_product_variant.variant_id
 */

class PlmBom {
    /** @var PlmStruct */
    private $struct;

    /** @var string */
    private $variantId;

    /** @var string */
    private $variantRid = '';

    /** @var array<string,array|null> */
    private $versionCache = [];

    /** @var array<string,array|null> */
    private $partCache = [];

    /** @var array<string,array> */
    private $itemsCache = [];

    /** @var array<string,array|null> */
    private $variantCache = [];

    /**
     * @param PlmStruct $struct
     * @param string    $variantId
     */
    public function __construct(
        PlmStruct $struct,
        string $variantId
    ) {
        $this->struct = $struct;
        $this->variantId = trim($variantId);
    }

    /**
     * Render the complete BOM.
     *
     * @return string
     */
    public function render(): string
    {
        $html = '<div class="plm-bom">';

        $html .= '<div class="plm-bom-variant">';
        $html .= '<h2>' . hsc($this->variantId) . '</h2>';
        $html .= '</div>';

        if ($this->variantId === '') {
            $html .= '<div class="plm-bom-empty">';
            $html .= 'No product variant specified.';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        /*
         * IMPORTANT:
         *
         * variant_id in plm_product_variant is a TEXT field,
         * not a Lookup. Therefore findProductVariant() uses
         * findOne() with variant_id directly.
         */
        $variant = $this->findProductVariant(
            $this->variantId
        );

        if ($variant === null) {
            $html .= '<div class="plm-bom-empty">';
            $html .= 'Product variant not found.';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        $html .= $this->renderVariantData(
            $variant
        );

        /*
         * IMPORTANT:
         *
         * variant_id in plm_product_variant_item IS a Lookup.
         * Therefore we cannot filter it with
         *
         *   variant_id=MC1210F-BK
         *
         * and expect Struct to interpret that as a lookup RID.
         *
         * Instead we load the rows and compare the referenced
         * RID with the RID of the selected product variant.
         */
        $roots = $this->findVariantItems(
            $variant
        );

        if (!$roots) {
            $html .= '<div class="plm-bom-empty">';
            $html .= 'No BOM items found.';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        $html .= '<div class="plm-bom-tree">';

        /*
         * Number the first BOM level:
         *
         *   1. Part A
         *   2. Part B
         *   3. Part C
         */
        $rootNumber = 0;

        foreach ($roots as $root) {
            $versionId = $this->getLookupDisplayValue(
                $root['version_id'] ?? null
            );

            if ($versionId === '') {
                continue;
            }

            $rootNumber++;

            $quantity = $this->getQuantity(
                $root
            );

            $html .= $this->renderVersionNode(
                $versionId,
                $quantity,
                0,
                [],
                $root,
                (string) $rootNumber
            );
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render product variant data.
     *
     * @param array $variant
     *
     * @return string
     */
    private function renderVariantData(
        array $variant
    ): string {

        $productId = $this->getLookupDisplayValue(
            $variant['product_id'] ?? null
        );

        $html = '<ul class="plm-bom-node-attrlist">';

        $html .= $this->renderAttribute("Product", hsc($productId));
        
        $html .= $this->renderFieldIfPresent(
            $variant, 'variant_id', 'Variant'
        );

        $html .= $this->renderFieldIfPresent(
            $variant, 'description', 'Description'
        );

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render one part version recursively.
     *
     * @param string     $versionId
     * @param mixed      $quantity
     * @param int        $level
     * @param array      $path
     * @param array|null $sourceItem
     * @param string     $number
     *
     * @return string
     */
    private function renderVersionNode(
        string $versionId,
        $quantity,
        int $level,
        array $path,
        ?array $sourceItem = null,
        string $number = ''
    ): string {
        $versionId = trim($versionId);

        if ($versionId === '') {
            return '';
        }

        /*
         * Protect against cyclic BOM structures.
         */
        if (isset($path[$versionId])) {
            return $this->renderCycleNode(
                $versionId,
                $quantity,
                $level
            );
        }

        $version = $this->findVersion($versionId);
        if ($version === null) {
            return '<div class="plm-bom-node plm-bom-missing"'
                . '<h3>'
                . ($number !== ''
                    ? hsc($number) . ' '
                    : '')
                . hsc($versionId)
                . '</h3>'
                . '<div class="plm-bom-error-text">'
                . '(part version not found)'
                . '</div>'
                . $this->renderQuantity($quantity)
                . '</div>';
        }

        /*
         * part in plm_part_version is a Lookup to
         * plm_part.ipn.
         */
        $partId = $this->getLookupDisplayValue($version['part'] ?? null);
        $part = null;
        if ($partId !== '') {
            $part = $this->findPart($partId);
        }

        /*
         * Part heading:
         *
         *   1.1 IPN — Description
         *
         * The part itself is always the main heading.
         */
        $title = $partId !== ''
            ? $partId
            : $versionId;

        $description = '';

        if ($part !== null) {
            $description = $this->getFieldValue(
                $part,
                'description'
            );
        }

        if ($description !== '') {
            $title = $title . ' : ' . $description;
        }

        $html = '<div class="plm-bom-node plm-bom-level' . $level . '" >';
        $html .= '<h3>';

        if ($number !== '') {
            $html .= hsc($number) . ' ';
        }

        $html .= hsc($title);
        $html .= '</h3>';

        /** Item data */
        $html .= $this->renderQuantity($quantity);

        /* Part data. */
        $html .= $this->renderPartData($part,$partId);

        /* Version data. */
        $html .= $this->renderVersionData($version,$versionId);

        /*
         * Data stored directly on the BOM relationship.
         */
        $html .= $this->renderSourceItemData(
            $sourceItem
        );

        /*
         * Keep the current version in the recursion path.
         */
        $nextPath = $path;
        $nextPath[$versionId] = true;

        /*
         * Find children by parent_version_id Lookup.
         */
        $children = $this->findChildItems(
            $version
        );

        /*
         * Number only children that actually apply to
         * the selected product variant.
         *
         * Example:
         *
         *   1.2 Parent
         *       1.2.1 Child A
         *       1.2.2 Child B
         */
        $childNumber = 0;

        foreach ($children as $child) {
            $applies = $this->itemAppliesToVariant(
                $child,
                $this->variantId
            );

            if (!$applies) {
                continue;
            }

            $childVersionId =
                $this->getLookupDisplayValue(
                    $child['child_version_id'] ?? null
                );

            if ($childVersionId === '') {
                continue;
            }

            $childNumber++;

            $childQuantity = $this->getQuantity(
                $child
            );

            $childItemNumber = $number !== ''
                ? $number . '.' . $childNumber
                : (string) $childNumber;

            $html .= $this->renderVersionNode(
                $childVersionId,
                $childQuantity,
                $level + 1,
                $nextPath,
                $child,
                $childItemNumber
            );
        }

        $html .= '</div>';

        return $html;
    }

    private function renderAttribute($name, $value) {        
        $html = '<li class="plm-bom-node-attr" >';
        $html .= '<span class="plm-bom-node-attr-key" >';
        $html .= $name;
        $html .= '</span>';
        $html .= '<span class="plm-bom-node-attr-value" >';
        $html .= empty($value) ? 'n/a' : $value;
        $html .= '</span>';
        $html .= '</li>';

        return $html;
    }

    /**
     * Render information from a BOM relationship row.
     *
     * @param array|null $item
     *
     * @return string
     */
    private function renderSourceItemData(?array $item ): string {
        if ($item === null) {
            return '';
        }

        $designators = $this->getFieldValue($item,'designators');
        
        $variantValues = [];
        if (array_key_exists('variants',$item)) {
            $variantValues =
                $this->getLookupDisplayValues(
                    $item['variants']
                );
        }

        $html = '<ul class="plm-bom-node-attrlist">';

        $html .= $this->renderAttribute("Designators:", hsc($designators));
        $html .= $this->renderAttribute("Variants:", hsc(implode(', ',$variantValues)));

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render part information.
     *
     * @param array|null $part
     * @param string     $partId
     *
     * @return string
     */
    private function renderPartData(
        ?array $part,
        string $partId
    ): string {
        if ( $part === null && $partId === '' ) {
            return '';
        }

        $html = '<ul class="plm-bom-node-attrlist" >';

        if ($part !== null) {
            $html .= $this->renderFieldIfPresent(
                $part, 'ipn', 'IPN:'
            );

            $html .= $this->renderFieldIfPresent(
                $part, 'description', 'Description:'
            );

            $html .= $this->renderFieldIfPresent(
                $part, 'category', 'Category:'
            );
        } 
        else {
            $html .= $this->renderAttribute("IPN:", $partId);
        }        

        $html .= "</ul>";
        return $html;
    }

    /**
     * Render part version information.
     *
     * @param array  $version
     * @param string $versionId
     *
     * @return string
     */
    private function renderVersionData(
        array $version,
        string $versionId
    ): string {
        $html = '<ul class="plm-bom-node-attrlist" >';
        $html .= $this->renderAttribute("Version:", hsc($versionId));

        $html .= $this->renderFieldIfPresent(
            $version, 'revision', 'Revision:'
        );

        $html .= $this->renderFieldIfPresent(
            $version, 'status', 'Status:'
        );

        $html .= "</ul>";
        return $html;
    }

    /**
     * Render one normal field.
     *
     * @param array  $row
     * @param string $field
     * @param string $label
     *
     * @return string
     */
    private function renderFieldIfPresent(
        array $row,
        string $field,
        string $label
    ): string {

        $value = $this->getFieldValue( $row, $field );
        return $this->renderAttribute( $label, $value );
    }

    /**
     * Render quantity.
     *
     * @param mixed $quantity
     *
     * @return string
     */
    private function renderQuantity(
        $quantity
    ): string {
        if (
            $quantity === null
            || $quantity === ''
        ) {
            return '';
        }

        if (is_numeric($quantity)) {
            $number = (float) $quantity;

            if (floor($number) === $number) {
                $value = (string) (int) $number;
            } else {
                $value = rtrim(
                    rtrim(
                        number_format(
                            $number,
                            6,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                );
            }
        } else {
            $value = (string) $quantity;
        }

        $html = '<ul class="plm-bom-node-attrlist" >';
        $html .= $this->renderAttribute("Quantity:", hsc($value));
        $html .= '</ul>';

        return $html;
    }

    /**
     * Render cycle marker.
     *
     * @param string $versionId
     * @param mixed  $quantity
     * @param int    $level
     *
     * @return string
     */
    private function renderCycleNode(
        string $versionId,
        $quantity,
        int $level
    ): string {

        return '<div class="plm-bom-node plm-bom-cycle"'
            . ' style="margin-left:'
            . (20 * $level)
            . 'px">'
            . '<strong>'
            . hsc($versionId)
            . '</strong>'
            . ' <span>(cyclic BOM reference)</span>'
            . $this->renderQuantity($quantity)
            . '</div>';
    }

    /**
     * Find the selected product variant.
     *
     * IMPORTANT:
     * plm_product_variant.variant_id is a TEXT field.
     *
     * @param string $variantId
     *
     * @return array|null
     */
    private function findProductVariant(
        string $variantId
    ): ?array {
        $variantId = trim($variantId);

        if ($variantId === '') {
            return null;
        }

        if (array_key_exists(
            $variantId,
            $this->variantCache
        )) {
            return $this->variantCache[$variantId];
        }

        try {
            $result = $this->struct->findOne(
                'plm_product_variant',
                'variant_id='
                . $this->quoteFilterValue(
                    $variantId
                )
            );
        } catch (\Throwable $e) {
            $this->variantCache[$variantId] = null;

            return null;
        }

        if (
            !is_array($result)
            || !isset($result['row'])
            || !is_array($result['row'])
        ) {
            $this->variantCache[$variantId] = null;

            return null;
        }

        $rawRow = $result['row'];

        $fieldIndexes = isset($result['fieldIndexes'])
            && is_array($result['fieldIndexes'])
            ? $result['fieldIndexes']
            : [];

        $row = [];

        foreach ($fieldIndexes as $field => $index) {
            if (array_key_exists($index, $rawRow)) {
                $row[$field] = $rawRow[$index];
            }
        }

        /*
         * Preserve the Struct RID.
         *
         * This is needed because plm_product_variant_item.variant_id
         * references this record via Lookup.
         */
        if (isset($result['_pk'])) {
            $row['_pk'] = $result['_pk'];
        } elseif (isset($result['rid'])) {
            $row['_pk'] = $result['rid'];
        }

        if (isset($result['pid'])) {
            $row['pid'] = $result['pid'];
        }

        if (isset($result['rid'])) {
            $row['rid'] = $result['rid'];
        }

        $this->variantCache[$variantId] = $row;

        return $row;
    }

    /**
     * Find BOM rows belonging to the selected product variant.
     *
     * plm_product_variant_item.variant_id is a Lookup.
     *
     * @param array $variant
     *
     * @return array
     */
    private function findVariantItems(
        array $variant
    ): array {
        $result = $this->struct->search(
            'plm_product_variant_item',
            [
                'variant_id',
                'version_id',
                'quantity',
            ]
        );

        $rows = $this->extractRows(
            $result,
            [
                'variant_id',
                'version_id',
                'quantity',
            ]
        );

        if (!$rows) {
            return [];
        }

        $variantRid = '';

        if (isset($variant['_pk'])) {
            $variantRid = (string) $variant['_pk'];
        } elseif (isset($variant['rid'])) {
            $variantRid = (string) $variant['rid'];
        }

        if ($variantRid === '') {
            return [];
        }

        $matches = [];

        foreach ($rows as $row) {
            if ($this->lookupContainsRid(
                $row['variant_id'] ?? null,
                $variantRid
            )) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    /**
     * Find a part version by its Text version_id.
     *
     * @param string $versionId
     *
     * @return array|null
     */
    private function findVersion(
        string $versionId
    ): ?array {
        $versionId = trim($versionId);

        if ($versionId === '') {
            return null;
        }

        if (array_key_exists(
            $versionId,
            $this->versionCache
        )) {
            return $this->versionCache[$versionId];
        }

        try {
            $result = $this->struct->findOne(
                'plm_part_version',
                'version_id='
                . $this->quoteFilterValue(
                    $versionId
                )
            );
        } catch (\Throwable $e) {
            $this->versionCache[$versionId] = null;

            return null;
        }

        if (
            !is_array($result)
            || !isset($result['row'])
            || !is_array($result['row'])
        ) {
            $this->versionCache[$versionId] = null;

            return null;
        }

        $rawRow = $result['row'];

        $fieldIndexes = isset($result['fieldIndexes'])
            && is_array($result['fieldIndexes'])
            ? $result['fieldIndexes']
            : [];

        $row = [];

        foreach ($fieldIndexes as $field => $index) {
            if (array_key_exists($index, $rawRow)) {
                $row[$field] = $rawRow[$index];
            }
        }

        if (isset($result['_pk'])) {
            $row['_pk'] = $result['_pk'];
        } elseif (isset($result['rid'])) {
            $row['_pk'] = $result['rid'];
        }

        if (isset($result['pid'])) {
            $row['pid'] = $result['pid'];
        }

        if (isset($result['rid'])) {
            $row['rid'] = $result['rid'];
        }

        $this->versionCache[$versionId] = $row;

        return $row;
    }

    /**
     * Find a part by its Text IPN.
     *
     * @param string $partId
     *
     * @return array|null
     */
    private function findPart(
        string $partId
    ): ?array {
        $partId = trim($partId);

        if ($partId === '') {
            return null;
        }

        if (array_key_exists(
            $partId,
            $this->partCache
        )) {
            return $this->partCache[$partId];
        }

        try {
            $result = $this->struct->findOne(
                'plm_part',
                'ipn='
                . $this->quoteFilterValue(
                    $partId
                )
            );
        } catch (\Throwable $e) {
            $this->partCache[$partId] = null;

            return null;
        }

        if (
            !is_array($result)
            || !isset($result['row'])
            || !is_array($result['row'])
        ) {
            $this->partCache[$partId] = null;

            return null;
        }

        $rawRow = $result['row'];

        $fieldIndexes = isset($result['fieldIndexes'])
            && is_array($result['fieldIndexes'])
            ? $result['fieldIndexes']
            : [];

        $row = [];

        foreach ($fieldIndexes as $field => $index) {
            if (array_key_exists($index, $rawRow)) {
                $row[$field] = $rawRow[$index];
            }
        }

        if (isset($result['_pk'])) {
            $row['_pk'] = $result['_pk'];
        } elseif (isset($result['rid'])) {
            $row['_pk'] = $result['rid'];
        }

        if (isset($result['pid'])) {
            $row['pid'] = $result['pid'];
        }

        if (isset($result['rid'])) {
            $row['rid'] = $result['rid'];
        }

        $this->partCache[$partId] = $row;

        return $row;
    }

    /**
     * Find child BOM items for a part version.
     *
     * parent_version_id is a Lookup to plm_part_version.version_id.
     * Therefore all rows are loaded and matched by the displayed
     * version_id value.
     *
     * @param array $parentVersion
     *
     * @return array
     */
    private function findChildItems(
        array $parentVersion
    ): array {
        $parentVersionId = $this->getLookupDisplayValue(
            $parentVersion['version_id'] ?? null
        );

        if ($parentVersionId === '') {
            return [];
        }

        if (array_key_exists(
            $parentVersionId,
            $this->itemsCache
        )) {
            return $this->itemsCache[$parentVersionId];
        }

        $result = $this->struct->search(
            'plm_part_item',
            [
                'parent_version_id',
                'child_version_id',
                'quantity',
                'designators',
                'variants',
            ]
        );

        $rows = $this->extractRows(
            $result,
            [
                'parent_version_id',
                'child_version_id',
                'quantity',
                'designators',
                'variants',
            ]
        );

        if (!$rows) {
            $this->itemsCache[$parentVersionId] = [];

            return [];
        }

        $matches = [];

        foreach ($rows as $row) {
            if (!array_key_exists(
                'parent_version_id',
                $row
            )) {
                continue;
            }

            $rowParentVersionId = $this->getLookupDisplayValue(
                $row['parent_version_id']
            );

            if (
                trim($rowParentVersionId) ===
                trim($parentVersionId)
            ) {
                $matches[] = $row;
            }
        }

        $this->itemsCache[$parentVersionId] = $matches;

        return $matches;
    }

    /**
     * Check whether a BOM item applies to the selected
     * product variant.
     *
     * Empty variants means "all variants".
     *
     * @param array $item
     * @param string $variantId
     *
     * @return bool
     */
    private function itemAppliesToVariant(
        array $item,
        string $variantId
    ): bool {
        $variants = $item['variants'] ?? null;

        /*
         * Empty Multi-Value Lookup means:
         * this item applies to all product variants.
         */
        if (
            $variants === null ||
            $variants === ''
        ) {
            return true;
        }

        /*
         * First try the human-readable Lookup values.
         */
        $variantValues = $this->getLookupDisplayValues(
            $variants
        );

        foreach ($variantValues as $value) {
            if (
                trim((string) $value) ===
                trim($variantId)
            ) {
                return true;
            }
        }

        /*
         * Fallback:
         * compare the referenced Struct RIDs.
         */
        $selectedVariant = $this->findProductVariant(
            $variantId
        );

        if ($selectedVariant === null) {
            return false;
        }

        $variantRid = '';

        if (isset($selectedVariant['_pk'])) {
            $variantRid = (string) $selectedVariant['_pk'];
        } elseif (isset($selectedVariant['rid'])) {
            $variantRid = (string) $selectedVariant['rid'];
        }

        if ($variantRid === '') {
            return false;
        }

        return $this->lookupContainsRid(
            $variants,
            $variantRid
        );
    }

    /**
     * Check whether a Lookup value contains a particular RID.
     *
     * @param mixed  $value
     * @param string $rid
     *
     * @return bool
     */
    private function lookupContainsRid(
        $value,
        string $rid
    ): bool {
        if (
            $value === null
            || $value === ''
            || $rid === ''
        ) {
            return false;
        }

        /*
         * Best case: PlmStruct knows how to decode Struct
         * Lookup values.
         */
        if (method_exists(
            $this->struct,
            'getLookupPrimaryKeys'
        )) {
            try {
                $keys = $this->struct->getLookupPrimaryKeys(
                    $value
                );

                if (is_array($keys)) {
                    foreach ($keys as $key) {
                        if ((string) $key === $rid) {
                            return true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                /*
                 * Fall through to the recursive fallback.
                 */
            }
        }

        /*
         * Arrays can contain nested lookup values.
         */
        if (is_array($value)) {
            foreach ($value as $entry) {
                if ($this->lookupContainsRid(
                    $entry,
                    $rid
                )) {
                    return true;
                }
            }

            return false;
        }

        /*
         * Struct Value objects expose getValue().
         */
        if (is_object($value)) {
            if (method_exists(
                $value,
                'getValue'
            )) {
                try {
                    $raw = $value->getValue();

                    if ($this->lookupContainsRid(
                        $raw,
                        $rid
                    )) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    /*
                     * Fall through.
                     */
                }
            }

            /*
             * Last fallback: string representation.
             */
            if (method_exists(
                $value,
                '__toString'
            )) {
                try {
                    return (string) $value === $rid;
                } catch (\Throwable $e) {
                    return false;
                }
            }

            return false;
        }

        /*
         * Scalar fallback.
         */
        return (string) $value === $rid;
    }

    /**
     * Get display value of one Lookup field.
     *
     * This deliberately does NOT use getLookupPrimaryKeys(),
     * because this method is used when we need the human-readable
     * value such as:
     *
     *   MC1210F-BK
     *   MC1210F
     *   PV-001
     *
     * rather than a Struct RID.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function getLookupDisplayValue(
        $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return '';
        }

        if (is_object($value)) {
            if (method_exists(
                $value,
                'getDisplayValue'
            )) {
                try {
                    return trim(
                        (string) $value->getDisplayValue()
                    );
                } catch (\Throwable $e) {
                    // Fall through.
                }
            }

            if (method_exists(
                $value,
                'getValue'
            )) {
                try {
                    $raw = $value->getValue();

                    if (
                        is_scalar($raw)
                        && (string) $raw !== ''
                    ) {
                        return trim(
                            (string) $raw
                        );
                    }
                } catch (\Throwable $e) {
                    // Fall through.
                }
            }

            if (method_exists(
                $value,
                '__toString'
            )) {
                try {
                    return trim(
                        (string) $value
                    );
                } catch (\Throwable $e) {
                    return '';
                }
            }

            return '';
        }

        /*
         * Multi-value Lookup.
         *
         * This is not used for matching; it is only a fallback
         * for display.
         */
        if (is_array($value)) {
            $values = $this->getLookupDisplayValues(
                $value
            );

            return implode(
                ', ',
                $values
            );
        }

        return trim(
            (string) $value
        );
    }

    /**
     * Get display values from a Lookup or Multi-Value Lookup.
     *
     * @param mixed $value
     *
     * @return array
     */
    private function getLookupDisplayValues($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        // Struct Value object
        if (is_object($value)) {
            if (method_exists($value, 'getDisplayValue')) {
                try {
                    $display = $value->getDisplayValue();

                    // Multi-Value Lookup
                    if (is_array($display)) {
                        $values = [];

                        foreach ($display as $entry) {
                            if ($entry === null || $entry === '') {
                                continue;
                            }

                            $values[] = trim((string)$entry);
                        }

                        return $values;
                    }

                    // Single-Value Lookup
                    if (is_scalar($display)) {
                        $display = trim((string)$display);

                        if ($display !== '') {
                            return [$display];
                        }
                    }
                } catch (\Throwable $e) {
                    // Fallback below
                }
            }

            // Fallback: raw value
            if (method_exists($value, 'getValue')) {
                try {
                    $raw = $value->getValue();

                    if (is_array($raw)) {
                        $values = [];

                        foreach ($raw as $entry) {
                            if ($entry === null || $entry === '') {
                                continue;
                            }

                            $values[] = trim((string)$entry);
                        }

                        return $values;
                    }
                } catch (\Throwable $e) {
                    // Ignore and return empty
                }
            }

            return [];
        }

        // Plain PHP array
        if (is_array($value)) {
            $values = [];

            foreach ($value as $entry) {
                if ($entry === null || $entry === '') {
                    continue;
                }

                if (is_object($entry)) {
                    $nested = $this->getLookupDisplayValues($entry);

                    foreach ($nested as $nestedValue) {
                        $values[] = $nestedValue;
                    }

                    continue;
                }

                $values[] = trim((string)$entry);
            }

            return $values;
        }

        // Scalar
        $value = trim((string)$value);

        return $value === '' ? [] : [$value];
    }

    /**
     * Get a normal field value as string.
     *
     * @param array  $row
     * @param string $field
     *
     * @return string
     */
    private function getFieldValue(
        array $row,
        string $field
    ): string {
        if (!array_key_exists(
            $field,
            $row
        )) {
            return '';
        }

        $value = $row[$field];

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim(
                (string) $value
            );
        }

        return $this->getLookupDisplayValue(
            $value
        );
    }

    /**
     * Get quantity.
     *
     * @param array $row
     *
     * @return mixed
     */
    private function getQuantity(
        array $row
    ) {
        if (!array_key_exists(
            'quantity',
            $row
        )) {
            return null;
        }

        $quantity = $row['quantity'];

        if (is_object($quantity)) {
            if (method_exists(
                $quantity,
                'getValue'
            )) {
                try {
                    return $quantity->getValue();
                } catch (\Throwable $e) {
                    // Fall through.
                }
            }

            if (method_exists(
                $quantity,
                'getDisplayValue'
            )) {
                try {
                    return $quantity->getDisplayValue();
                } catch (\Throwable $e) {
                    // Fall through.
                }
            }
        }

        return $quantity;
    }

    /**
     * Extract rows from PlmStruct::search().
     *
     * @param mixed $result
     * @param array $fields
     *
     * @return array
     */
    private function extractRows($result, array $fields): array
    {
        if (
            !is_array($result)
            || !isset($result['rows'])
            || !is_array($result['rows'])
        ) {
            return [];
        }

        $rows = [];

        foreach ($result['rows'] as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $row = [];

            foreach ($fields as $index => $field) {
                if (array_key_exists($index, $rawRow)) {
                    $row[$field] = $rawRow[$index];
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Quote a value for a Struct filter.
     *
     * @param string $value
     *
     * @return string
     */
    private function quoteFilterValue(
        string $value
    ): string {
        if (preg_match(
            '/^[A-Za-z0-9._:-]+$/',
            $value
        )) {
            return $value;
        }

        $value = str_replace(
            '\\',
            '\\\\',
            $value
        );

        $value = str_replace(
            '"',
            '\\"',
            $value
        );

        return '"' . $value . '"';
    }
}
