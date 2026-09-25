<?php

abstract class PlmMacro {

    /** @var HtmlBuilder */
    private readonly HtmlBuilder $out;

    protected function __construct() {
        $this->out = new HtmlBuilder();
    }

    protected function out() : HtmlBuilder {
        return $this->out;
    }

    protected function error(string $msg) : void {
        $this->out()->tag('div', $msg, 'error');
    }

    /**
     * Override function for rendering the content.
     */
    public abstract function render(Writer $writer, MacroHeader $header, ?string $content = null): void;
}