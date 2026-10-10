<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmPartEdit extends PlmBakeryMacro
{
    public function init(mixed $config): void
    {
    }

    #[Override]
    protected function getTemplateName() : string {
        return 'part-form.xml';
    }

    #[Override]
    protected function getContext(array $config): array
    {
        return [ "mode:create" => "true" ];
    }
}
