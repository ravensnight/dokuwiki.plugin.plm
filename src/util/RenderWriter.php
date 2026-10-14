<?php

class RenderWriter extends Writer 
{
    private readonly Doku_Renderer $r;

    public function __construct(Doku_Renderer $renderer) {
        $this->r = $renderer;
    }

    public function enableCache(bool $val) {
        $this->r->info['cache'] = $val;
    }

    /**
     * Write a string to 
     */
    public function write(string $buffer): void {
        $this->r->doc .= $buffer;
    }
}
