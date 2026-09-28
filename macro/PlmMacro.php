<?php

abstract class PlmMacro {

    protected function __construct() {
    }

    protected function error(HtmlBuilder $out, string $msg) : void {
        $out->tag('div', $msg, 'error');
    }

    /**
     * Override function for rendering the content.
     */
    public abstract function render(HtmlBuilder $html, MacroHeader $header, ?string $content = null): void;
}