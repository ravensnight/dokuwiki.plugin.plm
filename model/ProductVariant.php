<?php

class ProductVariant extends DbObject {
    
    public PK $product;
    public string $name;
    public ?string $description = null;

    /** @var ItemRef[] */
    public ?array $subItems = null;

    public function __construct(int $primaryKey, string $name, int $product)
    {
        parent::__construct($primaryKey);
        $this->name = $name;
        $this->product = new PK($product);
    }
}
