<?php

if (!defined('DOKU_INC')) {
    die();
}

class PartFormFactory {
    public static function renderCreateForm(HtmlBuilder $out, PlmDB $db): void {
        $categories = Category::fetchAll($db);

        $out->opn('form', null, [
            'hx-post' => '/doku.php?plmapi=v1/parts',
            'hx-target' => '#partedit',
            'hx-swap' => 'innerHTML',
        ]);

        HtmlBaseFactory::formGroup($out, 'ID', 'id', '', true);
        HtmlBaseFactory::formGroup($out, 'IPN', 'ipn', '');
        HtmlBaseFactory::formGroup($out, 'Description', 'description', '');
        HtmlBaseFactory::createCategoryFormGroup($out, 'Category', 'category', $categories, 1);

        // Submit button
        HtmlBaseFactory::formActions($out, [
            'create' => 'Create new'
        ]);

        $out->cls(); // Close form
    }

    public static function renderEditForm(HtmlBuilder $out, PlmDB $db, Part $part): void {
        $categories = Category::fetchAll($db);

        $out->opn('form', null, [
            'hx-post' => '/doku.php?plmapi=v1/parts/' . $part->pk,
            'hx-target' => '#partedit',
            'hx-swap' => 'innerHTML',
        ]);

        HtmlBaseFactory::formGroup($out, 'ID', 'partId', $part->pk, true);
        HtmlBaseFactory::formGroup($out, 'IPN', 'ipn', $part->ipn);
        HtmlBaseFactory::formGroup($out, 'Description', 'description', $part->description);
        HtmlBaseFactory::createCategoryFormGroup($out, 'Category', 'category', $categories, $part->categoryId);

        // Submit button
        HtmlBaseFactory::formActions($out, [
            'reset' => 'Clear Form',
            'save' => 'Submit',
            'delete' => 'Delete'
        ]);

        $out->cls(); // Close form
    }
}
