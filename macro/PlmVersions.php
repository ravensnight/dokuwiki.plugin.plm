<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmVersions extends PlmMacro
{
    private string $PREFIX = '/doku.php?plmapi=v1/';
    private string $ctx;

    public function __construct(string $context)
    {
        parent::__construct();
        $this->ctx = trim($context);
    }

    public function render(HtmlBuilder $html, MacroHeader $header, ?string $body = null): void
    {
        global $INPUT;

        $html->opn('div', 'plm_versions', [
            'id' => $this->ctx,
            'hx-get' => $this->PREFIX . 'versions',
            'hx-trigger' => 'part from:body',
            'hx-vals' => 'js:{partId: event.detail.id}',
            'hx-target' => '#' . $this->ctx,
            'hx-swap' => 'innerHTML'
        ]);

        $db = PlmDB::get();

        $html->tag('div', 'No part selcted', 'info');

        $html->cls(); // div
    }
}
