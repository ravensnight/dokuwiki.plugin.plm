<?php

class Part extends DbObject {
    
    public string $ipn;

    public ?string $category;
    public ?string $description = null;
    
    public function __construct(int $primaryKey, string $ipn)
    {
        parent::__construct($primaryKey);
        $this->ipn = $ipn;        
    }
}
