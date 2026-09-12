<?php

abstract class DbObject {

    public readonly PK $key;

    protected function __construct(int $primaryKey)
    {
        $this->key = new PK($primaryKey);
    }
    
}