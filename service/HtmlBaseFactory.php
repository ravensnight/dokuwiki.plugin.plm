<?php

if (!defined('DOKU_INC')) {
    die();
}

class HtmlBaseFactory {

    public static function label(HtmlBuilder $out, string $labelText, ?string $forInput = null): void {
        $out->opn('label', 'form-label', ['for' => $forInput ?? $labelText]);
        $out->add($labelText);
        $out->cls(); // Close label
    }

    public static function input(HtmlBuilder $out, string $type, string $name, string $value = '', ?array $attrs = null): void {

        $opts = [
            'type' => $type,
            'id' => $name,
            'name' => $name,
            'value' => $value,
        ];

        if ($attrs !== null) {
            $opts += $attrs;
        }

        $out->tag('input', null, null, $opts);
    }

    public static function formActions(HtmlBuilder $out, array $actionNameMap) {
        $out->opn('div', 'formactions');

        foreach($actionNameMap as $action => $name) {
            $out->opn('button', null, [
                'type' => 'submit',
                'name' => 'action',
                'value' => $action
            ]);
            $out->add($name);
            $out->cls();
        }

        $out->cls();
    }

    public static function formGroup(HtmlBuilder $out, string $label, string $name, string $value, bool $readonly = false): void {
        $out->opn('div', 'form-group');
        HtmlBaseFactory::label($out, $label, $name);

        $opts = [];
        if ($readonly) {
            $opts['readonly'] = 'readonly';
        }

        HtmlBaseFactory::input($out, 'text', $name, $value, $opts);
        $out->cls(); // Close form-group
    }

    public static function createCategoryFormGroup(HtmlBuilder $out, string $label, string $name, array $categories, int $selectedCategoryId): void {
        $out->opn('div', 'form-group');
        HtmlBaseFactory::label($out, $label, $name);

        // Create dropdown for categories
        $out->opn('select', null, ['name' => $name, 'id' => $name]);
        foreach ($categories as $category) {
            $params = [
                'value' => $category->pk
            ];

            if ($category->pk === $selectedCategoryId) {
                $params[ 'selected' ] = 'selected';
            }

            $out->tag('option', $category->name, null, $params);
        }
        $out->cls(); // Close select        
        $out->cls(); // Close form-group
    }
}
