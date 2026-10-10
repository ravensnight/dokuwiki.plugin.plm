<?php

/**
 * Renders all PLM parts as a table.
 *
 * The variant name is intentionally retained for the future variant filter,
 * but it does not affect the list yet.
 */
class PlmParts extends PlmBakeryMacro
{
    /** Get the template to render */
    #[Override]
    protected function getTemplateName(): string {
        return "part-list.xml";
    }

    /** Get the context to use for rendering */
    #[Override]
    protected function getContext(array $config): array {
        return [];
    }
}
