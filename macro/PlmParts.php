<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmParts extends PlmBuilderMacro
{
    private string $PREFIX = '/doku.php?plmapi=v1/';
    private string $ctx = '';

    public function init(mixed $config) : void {
        if (!isset($config['id'])) {
            throw new Exception('plm:parts - missing attribute id.');
        }

        $this->ctx = trim($config['id']);
    }    

    protected function build(HtmlBuilder $html): void
    {
        $html->opn('div', 'plm_parts', [
            'id' => $this->ctx,
            'hx-get' => $this->PREFIX . 'parts',
            'hx-trigger' => 'part[event.detail.trigger=change] from:body',
            'hx-target' => '#' . $this->ctx,
            'hx-swap' => 'innerHTML'
        ]);

        $db = PlmDB::get();
        PartListFactory::renderPartList($html, $db);
        
        $html->cls(); // div
    }
}
