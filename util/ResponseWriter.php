<?php

class ResponseWriter extends Writer {
    
    public int $statusCode = 200;
    public string $contentType = 'text/html';

    public function __construct() {
    }

    /**
     * Write a string to 
     */
    public function write(string $buffer): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: text/html; charset=utf-8');

        echo $buffer;
    }
}
