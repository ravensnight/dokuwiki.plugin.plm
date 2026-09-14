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
    /** @var PlmDB */
    private $db;

    /** @var string */
    private $variantName;

    /** @var ProductVariant */
    private $variant = null;

    /**
     * @param PlmStruct $struct
     * @param string    $variantId
     */
    public function __construct( PlmDb $db, string $variantName ) {
        $this->db = $db;
        $this->variantName = trim($variantName);
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
        $html .= '<h2>' . hsc($this->variantName) . '</h2>';
        $html .= '</div>';

        if ($this->variantName === '') {
            $html .= '<div class="plm-bom-empty">';
            $html .= 'No product variant specified.';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        /**
        * Acquire product variant
        */
        $this->variant = ProductVariant::byName($this->db, $this->variantName);
        if ($this->variant === null) {
            $html .= '<div class="plm-bom-empty">';
            $html .= 'Product variant not found.';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        // Variant & Product Attributes
        $html .= $this->renderVariantAttrs( $this->variant );
        
        /** @var VariantItemRef[] */
        $variantItems = $this->variant->fetchChildren($this->db);
        if (!$variantItems) {
            $html .= '<div class="plm-bom-empty">';
            $html .= 'No BOM items found.';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        $html .= '<div class="plm-bom-tree">';

        /*
         * Loop the children
         */
        $nodeIndex = 0;
        foreach ($variantItems as $variantItem) {
            $nodeIndex++;
            $indexPath = [];
            $indexPath[] = $nodeIndex;

            $html .= $this->renderVariantItemNode($variantItem, 0, [], $indexPath);
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render product variant data.
     * @param ProductVariant $variant
     *
     * @return string
     */
    private function renderVariantAttrs(ProductVariant $variant): string {


        $html = '<ul class="plm-bom-node-attrlist">';

        /** @var Product */
        $product = $variant->fetchProduct($this->db);

        if ($product) {
            $html .= $this->renderAttribute("Product: ", "product", hsc($product->name));        
        }

        $html .= $this->renderAttribute('Variant: ', 'variant', $variant->name);
        $html .= $this->renderAttribute('Description: ', 'description', $variant->description);

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render the data of a part to the current context
     */
    private function renderPartAttrs(Part $part) : string {

        $html = '';

        if ($part !== null) {
            $html .= '<ul class="plm-bom-node-attrlist" >';
            $html .= $this->renderAttribute('IPN: ', 'ipn', $part->ipn);
            $html .= $this->renderAttribute('Description:', 'description', $part->description);

            /** @var Category */
            $cat = $part->fetchCategory($this->db);
            if ($cat) {
                $this->renderAttribute("Category: ", 'category', $cat->name);
            }

            $html .= "</ul>";
        }


        return $html;
    }

    /**
     * Render the data of a part to the current context
     */
    private function renderVersionAttrs(PartVersion $version): string
    {

        $html = '';
        if ($version) {

            $html = '<ul class="plm-bom-node-attrlist" >';

            $html .= $this->renderAttribute("Major Version:", 'version', hsc((string)$version->major));
            $html .= $this->renderAttribute("Revision: ", 'version', hsc((string)$version->revision));

            /** @var Status */
            $stat = $version->fetchStatus($this->db);
            if ($stat) {
                $this->renderAttribute("Status: ", 'status', $stat->name);
            }

            $html .= "</ul>";
        }

        return $html;
    }

    private function renderPartItemAttrs(PartItemRef $itemRef) : string {
        if ($itemRef === null) {
            return '';
        }

        /** @var string[] */
        $variantValues = [];

        /** @var ProductVariant[] */
        $variants = $itemRef->fetchVariants($this->db);
        if ($variants) {
            foreach ($variants as $var) {
                $variantValues[] = $var->name;
            }
        }

        $html = '<ul class="plm-bom-node-attrlist">';

        $html .= $this->renderAttribute("Quantity: ", 'quantity', hsc((string)$itemRef->quantity));
        $html .= $this->renderAttribute("Designators: ", 'designators', hsc($itemRef->designators));
        $html .= $this->renderAttribute("Variants: ", 'variants', hsc(implode(', ', $variantValues)));

        $html .= '</ul>';

        return $html;
    }

    private function renderVariantItemAttrs(VariantItemRef $itemRef): string {
        if ($itemRef === null) {
            return '';
        }

        $html = '<ul class="plm-bom-node-attrlist">';
        $html .= $this->renderAttribute("Quantity: ", 'quantity', hsc((string)$itemRef->quantity));
        $html .= '</ul>';

        return $html;
    }


    private function renderPartVersionCore(PartVersion $version, array $indexPath) : string {

        /** @var Part */
        $part = $version->fetchPart($this->db);

        /*
         * Part heading:
         *   1.1 IPN — Description
         */
        $title = $part !== null ? $part->ipn : $version->name;
        if ($part->description !== '') {
            $title = $title . ' : ' . $part->description;
        }

        $html = '<h3>';
        $html .= hsc(implode('.', $indexPath));
        $html .= ' ';
        $html .= hsc($title);
        $html .= '</h3>';

        /* Part data. */
        $html .= $this->renderPartAttrs($part);

        /* Version data. */
        $html .= $this->renderVersionAttrs($version);
        
        return $html;
    }

    private function renderPartVersionChildren(PartVersion $version, int $level, array $indexPath, array $itemPath) : string {

        /** @var PartItemRef[] */
        $children = $version->fetchChildren($this->db);

        if ($children === null) {
            return '';
        }

        $itemIndex = 0;

        $html = '';
        foreach ($children as $child) {

            $itemIndex += 1;

            $nextIndexPath = $indexPath;
            $nextIndexPath[] = $itemIndex;

            $html .= $this->renderPartItemNode($child, $level + 1, $nextIndexPath, $itemPath);
        }

        return $html;
    }

    /**
     * Render variant item node.
     */
    private function renderVariantItemNode(
        VariantItemRef $itemRef, 
        int $level,
        array $indexPath,
        array $itemPath
    ) : string {

        $childVersion = $itemRef->fetchChild($this->db);
        if ($childVersion === null) {
            return '';
        }

        /*
         * Protect against cyclic BOM structures.
         */
        $versionName = $childVersion->name;
        if (isset($itemPath[$versionName])) {
            return $this->renderCycleNode($versionName, $itemRef->quantity);
        }

        $level = count($indexPath);
        $html = '<div class="plm-bom-node plm-bom-level' . $level . '" >';

        /** Common Link data */
        $html .= $this->renderPartVersionCore($childVersion, $indexPath);

        /** Specific Item link data */
        $html .= $this->renderVariantItemAttrs($itemRef);

        /*
         * Append the current version in the recursion path.
         */
        $nextItemPath = $itemPath;
        $nextItemPath[$versionName] = true;
        $html .= $this->renderPartVersionChildren($childVersion, $level, $indexPath, $nextItemPath);

        $html .= '</div>';

        return $html;
    }

    /**
     * Render one part version recursively.
     * @return string
     */
    private function renderPartItemNode(
        PartItemRef $itemRef,        
        int $level,
        array $indexPath,
        array $itemPath

    ): string {
        if (!$itemRef->appliesToVariant($this->db, $this->variant->pk)) {
            return '';
        }

        $childVersion = $itemRef->fetchChild($this->db);
        if ($childVersion === null) {
            return '';
        }

        $versionName = $childVersion->name;

        /*
         * Protect against cyclic BOM structures.
         */
        if (isset($itemPath[$versionName])) {
            return $this->renderCycleNode( $versionName, $itemRef->quantity);
        }

        $level = count($indexPath);
        $html = '<div class="plm-bom-node plm-bom-level' . $level . '" >';

        /** Common Link data */
        $html = $this->renderPartVersionCore($childVersion, $indexPath);

        /** Specific link data */
        $html .= $this->renderPartItemAttrs($itemRef);

        /*
         * Append the current version in the recursion path.
         */
        $nextItemPath = $itemPath;
        $nextItemPath[$versionName] = true;
        $html .= $this->renderPartVersionChildren($childVersion, $level, $indexPath, $nextItemPath);

        $html .= '</div>';

        return $html;
    }

    private function renderAttribute(string $name, string $cssClass, $value) {        
        $html = '<li class="plm-bom-node-attr ';
        $html .= $cssClass;
        $html .= '" >' ;
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
     * Render cycle marker.
     *
     * @param string $versionId
     * @param mixed  $quantity
     * @param int    $level
     *
     * @return string
     */
    private function renderCycleNode(
        string $versionName,
        $quantity
    ): string {

        return '<div class="plm-bom-node plm-bom-cycle" >'
            . '<strong>'
            . hsc($versionName)
            . '</strong>'
            . ' <span>(cyclic BOM reference)</span>'
            . $this->renderAttribute("Qantity: ", 'quantity', $quantity)
            . '</div>';
    }
}
