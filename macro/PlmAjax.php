<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmAjax extends PlmMacro
{
    private string $PREFIX = '/doku.php?plmapi=v1/';

    private string $function;

    public function __construct(string $function)
    {
        parent::__construct();
        $this->function = trim($function);
    }

    /**
        <div
            id="parts-container"
            hx-get="/api/plm/v1/parts"
            hx-trigger="load"
            hx-target="#parts-container"
            hx-swap="innerHTML"
        >
        <p>Parts werden geladen ...</p>
        </div>
     */
    protected function renderParts(HtmlBuilder $html, string $id) {

        $html->opn('div', 'partlist', [ 
            'id' => $id,
            'hx-get' => $this->PREFIX . 'parts',
            'hx-trigger' => 'load',
            'hx-target' => '#' . $id,
            'hx-swap' => 'innerHTML',
            'hx-boost' => 'true'
        ]);

        $html->tag('p', 'Loading ... ');
        $html->cls(); // div
    }

    public function render(HtmlBuilder $html, MacroHeader $header, ?string $body = null): void
    {
        $html->opn('div', 'plm', [ 'id' => 'plm' ]);

        switch ($this->function) {
            case 'parts':
                $this->renderParts($html, $header->reference);
                break;

            default:
                $this->error($html, "Unknown plm:ajax > <function>");
                break;
        }
    }
}
