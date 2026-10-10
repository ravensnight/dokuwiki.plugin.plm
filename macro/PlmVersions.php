<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmVersions extends PlmBuilderMacro
{
    private string $PREFIX = '/doku.php?plmapi=v1/';
    private string $ctx;

    public function init(mixed $config): void
    {
        if (!isset($config['id'])) {
            throw new Exception('plm:versions - missing attribute id.');
        }

        $this->ctx = trim($config['id']);
    }

    public function build(HtmlBuilder $html): void
    {
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
