<?php

readonly class PK {
    
    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}