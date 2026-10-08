<?php

/**
 */
class PlmVersionEdit extends PlmMacro
{
    private string $ctx = '';

    public function init(mixed $config): void
    {
        if (!isset($config['id'])) {
            throw new Exception('plm:versionedit - missing attribute id.');
        }

        $this->ctx = trim($config['id']);
    }

    public function render(HtmlBuilder $html, ): void
    {
        $db = PlmDB::get();

        $html->opn('div', 'plm_versionedit', [ 'id' => $this->ctx, ]);
        PartFormFactory::renderCreateForm($html, $db);
        $html->cls();
    }
}
