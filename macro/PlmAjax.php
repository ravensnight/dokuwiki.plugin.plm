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
    protected function renderParts(string $id) {

        $this->out()->opn('div', 'partlist', [ 
            'id' => $id,
            'hx-get' => $this->PREFIX . 'parts',
            'hx-trigger' => 'load',
            'hx-target' => '#' . $id,
            'hx-swap' => 'innerHTML',
            'hx-boost' => 'true'
        ]);

        $this->out()->tag('p', 'Loading...');
        $this->out()->cls();
    }

    public function render(Writer $writer, MacroHeader $header, ?string $body = null): void
    {
        $this->out()->opn('div', 'plm');

        switch ($this->function) {

            case 'parts':
                $this->renderParts($header->reference);
                break;

            default:
                $this->error("Unknown plm:ajax > <function>");
                break;
        }

        $this->out()->cls()->flush($writer);
    }
}
