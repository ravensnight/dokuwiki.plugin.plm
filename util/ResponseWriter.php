<?php

class ResponseWriter extends Writer {
    
    public int $statusCode = 200;
    public string $contentType = 'text/html; charset=utf-8';

    /** @var array[string => string] */
    public array $additionalHeaders = [];

    public function __construct() {
    }

    /**
     * Write a string to 
     */
    public function write(string $buffer): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: ' . $this->contentType);

        foreach ($this->additionalHeaders as $h => $v) {
            if (($h !== '') && ($v !== null)) {
                header($h . ': ' . $v);
            }
        }

        echo $buffer;
    }
}
