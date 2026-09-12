<?php

class ItemRef extends DbObject {
    
    public PK $subItemVersion;
    public int $quantity = -1;
    public ?string $designators = null;
    
    public function __construct(int $primaryKey, int $subItemId)
    {
        parent::__construct($primaryKey);
        $this->subItemVersion = new PK($subItemId);
    }
}
