<?php

class Product extends DbObject {
    
    public string $name;
    public ?string $description = null;
    
    public function __construct(int $primaryKey, string $name)
    {
        parent::__construct($primaryKey);
        $this->name = $name;
    }
}
