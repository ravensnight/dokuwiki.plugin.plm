<?php

class Status extends DbObject {
    
    public string $name;
    public ?string $description = null;
    public ?int $sort_order = null;
    public bool $active = false;
    
    public function __construct(int $primaryKey, string $name)
    {
        parent::__construct($primaryKey);
        $this->name = $name;
    }
}
