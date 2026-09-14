<?php

/**
 * PLM BOM renderer.
 */
class PlmBom extends PlmMacro {

    /** @var string */
    private $variantName;

    /** @var ProductVariant */
    private $variant = null;

    /**
     * @param PlmDb $db
     * @param string $variantName
     */
    public function __construct( Doku_Renderer $renderer, PlmDb $db, string $variantName ) {
        parent::__construct($renderer, $db);

        $this->variantName = trim($variantName);
    }

    /**
     * Render the complete BOM.
     */
    public function render(MacroHeader $header, ?string $content = null): void
    {
        $this->out()
            ->opn('div', 'plm-bom')
            ->opn('div', 'plm-bom-variant')
            ->opn('h2')->add(hsc($this->variantName))->cls()
            ->cls();

        if ($this->variantName === '') {
            $this->out()
                ->tag('div', 'No product variant specified.', 'plm-bom-empty')
                ->flush();
            return;
        }

        /**
        * Acquire product variant
        */
        $this->variant = ProductVariant::byName($this->db, $this->variantName);
        if ($this->variant === null) {
            $this->out()
                ->tag('div', 'Product variant not found.', 'plm-bom-empty')
                ->flush();
            return;
        }

        // Variant & Product Attributes
        $this->renderVariantAttrs($this->variant);
        
        /** @var VariantItemRef[] */
        $variantItems = $this->variant->fetchChildren($this->db);
        if (!$variantItems) {
            $this->out()
                ->tag('div', 'No BOM items found.', 'plm-bom-empty')
                ->flush();
            return;
        }

        $this->out()->opn('div', 'plm-bom-tree');

        /*
         * Loop the children
         */
        $nodeIndex = 0;
        foreach ($variantItems as $variantItem) {
            $nodeIndex++;
            $indexPath = [];
            $indexPath[] = $nodeIndex;

            $this->renderVariantItemNode($variantItem, 0, [], $indexPath);
        }

        $this->out()->cls()->flush();
    }

    /**
     * Render product variant data.
     * @param ProductVariant $variant
     *
     */
    private function renderVariantAttrs(ProductVariant $variant): void {
        $this->out()->opn('ul', 'plm-bom-node-attrlist');

        /** @var Product */
        $product = $variant->fetchProduct($this->db);

        if ($product) {
            $this->renderAttribute('Product: ', 'product', hsc($product->name));
        }

        $this->renderAttribute('Variant: ', 'variant', $variant->name);
        $this->renderAttribute('Description: ', 'description', $variant->description);

        $this->out()->cls();
    }

    /**
     * Render the data of a part to the current context
     */
    private function renderPartAttrs(?Part $part) : void {
        if ($part !== null) {
            $this->out()->opn('ul', 'plm-bom-node-attrlist');

            $this->renderAttribute('IPN: ', 'ipn', $part->ipn);
            $this->renderAttribute('Description:', 'description', $part->description);

            /** @var Category */
            $cat = $part->fetchCategory($this->db);
            if ($cat) {
                $this->renderAttribute('Category: ', 'category', $cat->name);
            }

            $this->out()->cls();
        }
    }

    /**
     * Render the data of a part to the current context
     */
    private function renderVersionAttrs(PartVersion $version): void
    {
        if ($version) {
            $this->out()->opn('ul', 'plm-bom-node-attrlist');

            $this->renderAttribute('Major Version:', 'version', hsc((string)$version->major));
            $this->renderAttribute('Revision: ', 'version', hsc((string)$version->revision));

            /** @var Status */
            $stat = $version->fetchStatus($this->db);
            if ($stat) {
                $this->renderAttribute('Status: ', 'status', $stat->name);
            }

            $this->out()->cls();
        }
    }

    private function renderPartItemAttrs(?PartItemRef $itemRef) : void {
        if ($itemRef === null) {
            return;
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

        $this->out()->opn('ul', 'plm-bom-node-attrlist');
        $this->renderAttribute('Quantity: ', 'quantity', hsc((string)$itemRef->quantity));
        $this->renderAttribute('Designators: ', 'designators', hsc($itemRef->designators));
        $this->renderAttribute('Variants: ', 'variants', hsc(implode(', ', $variantValues)));
        $this->out()->cls();
    }

    private function renderVariantItemAttrs(?VariantItemRef $itemRef): void {
        if ($itemRef === null) {
            return;
        }

        $this->out()->opn('ul', 'plm-bom-node-attrlist');
        $this->renderAttribute('Quantity: ', 'quantity', hsc((string)$itemRef->quantity));
        $this->out()->cls();
    }


    private function renderPartVersionCore(PartVersion $version, array $indexPath) : void {

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

        $this->out()->opn('h3')
            ->add(hsc(implode('.', $indexPath)))
            ->add(' ')
            ->add(hsc($title))
            ->cls();

        /* Part data. */
        $this->renderPartAttrs($part);

        /* Version data. */
        $this->renderVersionAttrs($version);
    }

    private function renderPartVersionChildren(PartVersion $version, int $level, array $indexPath, array $itemPath) : void {

        /** @var PartItemRef[] */
        $children = $version->fetchChildren($this->db);

        if ($children === null) {
            return;
        }

        $itemIndex = 0;

        foreach ($children as $child) {

            $itemIndex += 1;

            $nextIndexPath = $indexPath;
            $nextIndexPath[] = $itemIndex;

            $this->renderPartItemNode($child, $level + 1, $nextIndexPath, $itemPath);
        }
    }

    /**
     * Render variant item node.
     */
    private function renderVariantItemNode( VariantItemRef $itemRef,  int $level, array $indexPath, array $itemPath ) : void {

        $childVersion = $itemRef->fetchChild($this->db);
        if ($childVersion === null) {
            return;
        }

        /*
         * Protect against cyclic BOM structures.
         */
        $versionName = $childVersion->name;
        if (isset($itemPath[$versionName])) {
            $this->renderCycleNode($versionName, $itemRef->quantity);
            return;
        }

        $level = count($indexPath);
        $this->out()->opn('div', 'plm-bom-node plm-bom-level' . $level);

        $this->renderPartVersionCore($childVersion, $indexPath);

        /** Specific Item link data */
        $this->renderVariantItemAttrs($itemRef);

        /*
         * Append the current version in the recursion path.
        */
        $nextItemPath = $itemPath;
        $nextItemPath[$versionName] = true;
        $this->renderPartVersionChildren($childVersion, $level, $indexPath, $nextItemPath);

        $this->out()->cls();
    }

    /**
     * Render one part version recursively.
     */
    private function renderPartItemNode( PartItemRef $itemRef, int $level, array $indexPath, array $itemPath): void {

        if (!$itemRef->appliesToVariant($this->db, $this->variant->pk)) {
            return;
        }

        $childVersion = $itemRef->fetchChild($this->db);
        if ($childVersion === null) {
            return;
        }

        $versionName = $childVersion->name;

        /*
         * Protect against cyclic BOM structures.
        */
        if (isset($itemPath[$versionName])) {
            $this->renderCycleNode($versionName, $itemRef->quantity);
            return;
        }

        $level = count($indexPath);
        $this->out()->opn('div', 'plm-bom-node plm-bom-level' . $level);

        /** Common Link data */
        $this->renderPartVersionCore($childVersion, $indexPath);

        /** Specific link data */
        $this->renderPartItemAttrs($itemRef);

        /*
         * Append the current version in the recursion path.
        */
        $nextItemPath = $itemPath;
        $nextItemPath[$versionName] = true;
        $this->renderPartVersionChildren($childVersion, $level, $indexPath, $nextItemPath);

        $this->out()->cls();
    }

    private function renderAttribute(string $name, string $cssClass, $value): void {
        $this->out()
            ->opn('li', 'plm-bom-node-attr ' . $cssClass)
            ->tag('span', $name, 'plm-bom-node-attr-key')
            ->tag('span', empty($value) ? 'n/a' : (string) $value, 'plm-bom-node-attr-value')
            ->cls();
    }

    /**
     * Render cycle marker.
     *
     * @param string $versionId
     * @param mixed  $quantity
     * @param int    $level
     *
     */
    private function renderCycleNode( string $versionName, $quantity ): void {
        $this->out()
            ->opn('div', 'plm-bom-node plm-bom-cycle')
            ->opn('strong')
            ->add(hsc($versionName))
            ->cls()
            ->add(' ')
            ->opn('span')
            ->add('(cyclic BOM reference)')
            ->cls();

        $this->renderAttribute('Qantity: ', 'quantity', $quantity);
        $this->out()->cls();
    }
}
