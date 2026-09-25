<?php

/**
 * PLM BOM renderer.
 */
class PlmBom extends PlmMacro {



    private readonly string $variantName;
    private readonly PlmDB $db;

    /**
     * @var ProductVariant
     */
    private $variant = null;

    /**
     * @param PlmDb $db
     * @param string $variantName
     */
    public function __construct(PlmDb $db, string $variantName ) {
        parent::__construct();

        $this->db = $db;
        $this->variantName = trim($variantName);
    }

    /**
     * Render the complete BOM.
     */
    public function render(Writer $writer, MacroHeader $header, ?string $content = null): void
    {
        $this->out()
            ->opn('div', 'plm-bom')
            ->opn('div', 'plm-bom-variant')
            ->opn('h2')->add(hsc($this->variantName))->cls()
            ->cls();

        if ($this->variantName === '') {
            $this->out()
                ->tag('div', 'No product variant specified.', 'plm-bom-empty')
                ->flush($writer);
            return;
        }

        /**
        * Acquire product variant
        */
        $this->variant = ProductVariant::byName($this->db, $this->variantName);
        if ($this->variant === null) {
            $this->out()
                ->tag('div', 'Product variant not found.', 'plm-bom-empty')
                ->flush($writer);
            return;
        }

        // Variant & Product Attributes
        $this->renderVariantAttrs($this->variant);
        
        /** 
         * Fetch Part Versions
         * @var VariantItemRef[] 
         */
        $variantItems = $this->variant->fetchChildren($this->db);
        if (!$variantItems) {
            $this->out()
                ->tag('div', 'No BOM items found.', 'plm-bom-empty')
                ->flush($writer);
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

            $this->renderVariantItemRef($variantItem, 0, $indexPath, []);
        }

        $this->out()->cls()->flush($writer);
    }

    /**
     * Render product variant data.
     * @param ProductVariant $variant
     *
     */
    private function renderVariantAttrs(ProductVariant $variant): void {
        $this->out()->opn('ul', 'plm-bom-node-attrlist');

        $attrs = [
            'Variant Name' => $variant->name,
            'Description' => $variant->description
        ];

        /** @var Product */
        $product = $variant->fetchProduct($this->db);
        if ($product) {
            $attrs['Owned by'] = hsc($product->name);
        }

        $this->renderAttributeList($attrs);

        $this->out()->cls();
    }

    private function collectVariantItemRefAttrs(VariantItemRef $itemRef): array {
        return ['Quantity ' => hsc((string)$itemRef->quantity) ];
    }

    private function collectPartItemRefAttrs(PartItemRef $itemRef) : array {

        if ($itemRef !== null) {
            $variantValues = [];
            /** @var ProductVariant[] */
            $variants = $itemRef->fetchVariants($this->db);
            if ($variants) {
                foreach ($variants as $var) {
                    $variantValues[] = $var->name;
                }
            }

            return [
                'Quantity' => (string) $itemRef->quantity,
                'Designators' => $itemRef->designators,
                'Variants' => implode(', ', $variantValues)
            ];
        }

        return [];
    }

    /**
     * Render version and (optionally) part-item attributes in one table.
     */
    private function collectVersionAttrs(PartVersion $version): array {
        $res = [
            'Version' => (string) $version->major . '.rev' . (string) $version->revision
        ];

        /** @var Status */
        $stat = $version->fetchStatus($this->db);
        if ($stat) {
            $res['Status'] = $stat->name;
        }

        return $res;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function renderAttributeList(array $attributes): void {
        $this->out()->opn('div', 'plm-bom-node-attrlist');

        foreach ($attributes as $name => $value) {
            $cssClass = strtolower(str_replace([' ', ':'], ['_',''], trim($name)));
            $this->renderAttribute($name, $cssClass, $value ?? '');
        }

        $this->out()->cls();
    }


    private function renderPartHeader(PartVersion $version, array $indexPath) : void {

        /** @var Part */
        $part = $version->fetchPart($this->db);

        if ($part === null) {
            $this->out()->opn('h3')
                ->add(hsc(implode('.', $indexPath)))
                ->add(' ')
                ->add(hsc($version->name))
                ->cls();

            return;
        }

        /*
         * Part heading:
         *   1.1 IPN — Description
         */
        $title = $part->ipn;
        if ($part->description !== '') {
            $title = $title . ' : ' . $part->description;
        }

        $this->out()->opn('h3')
            ->add(hsc(implode('.', $indexPath)))
            ->add(' ')
            ->add(hsc($title))
            ->cls();

        $attrs = [
            'IPN' => $part->ipn,
            'Description' => $part->description
        ];

        /** @var Category */
        $cat = $part->fetchCategory($this->db);
        if ($cat) {
            $attrs['Category'] = $cat->description . ' (' . $cat->name . ')';
        }

        $this->renderAttributeList($attrs);
    }

    private function renderPartVersionChildren(PartVersion $version, int $level, array $indexPath, array $itemPath) : void {

        /** @var PartItemRef[] */
        $children = $version->fetchChildren($this->db);

        if ($children === null) {
            return;
        }

        $itemIndex = 0;

        foreach ($children as $itemRef) {

            $itemIndex += 1;

            $nextIndexPath = $indexPath;
            $nextIndexPath[] = $itemIndex;

            $this->renderPartItemRef($itemRef, $level + 1, $nextIndexPath, $itemPath);
        }
    }

    /**
     * Render variant item node.
     */
    private function renderVariantItemRef(VariantItemRef $itemRef,  int $level, array $indexPath, array $itemPath ) : void {

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

        /** Render part header */
        $this->renderPartHeader($childVersion, $indexPath);

        /** @var array<string, mixed> */
        $attributes = [];

        /* Version data. */
        $attributes += $this->collectVersionAttrs($childVersion);

        /** Specific Item link data */
        $attributes += $this->collectVariantItemRefAttrs($itemRef);

        /** Render all attributes */
        $this->renderAttributeList($attributes);

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
    private function renderPartItemRef( PartItemRef $itemRef, int $level, array $indexPath, array $itemPath): void {

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

        /** Render part heading and attrs */
        $this->renderPartHeader($childVersion, $indexPath);

        /** @var array<string, mixed> */
        $attributes = [];

        /* Version data. */
        $attributes += $this->collectVersionAttrs($childVersion);

        /** Specific Item link data */
        $attributes += $this->collectPartItemRefAttrs($itemRef);

        /** Render all attributes */
        $this->renderAttributeList($attributes);

        /*
         * Append the current version in the recursion path.
        */
        $nextItemPath = $itemPath;
        $nextItemPath[$versionName] = true;
        $this->renderPartVersionChildren($childVersion, $level, $indexPath, $nextItemPath);

        $this->out()->cls();
    }

    private function renderAttribute(string $name, string $cssClass, string $value): void {
        $this->out()
            ->opn('div', 'plm-bom-node-attr ' . $cssClass)
            ->tag('span', $name . ': ', 'key')
            ->tag('span', empty($value) ? 'n/a' : (string) $value, 'value')
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

        $this->renderAttribute('Qantity', 'quantity', $quantity);
        $this->out()->cls();
    }
}
