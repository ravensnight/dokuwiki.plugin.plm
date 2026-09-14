<?php

abstract class PlmMacro {

    protected PlmDB $db;

    /** @var HtmlBuilder */
    private readonly HtmlBuilder $out;

    protected function __construct(Doku_Renderer $renderer, PlmDB $db) {
        $this->db = $db;
        $this->out = new HtmlBuilder($renderer);
    }

    protected function out() : HtmlBuilder {
        return $this->out;
    }

    /**
     * Override function for rendering the content.
     */
    public abstract function render(MacroHeader $header, ?string $content = null): void;
}