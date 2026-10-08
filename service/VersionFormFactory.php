<?php

if (!defined('DOKU_INC')) {
    die();
}

class VersionFormFactory {
    public static function renderCreateForm(HtmlBuilder $out, PlmDB $db, int $partId): void {
        $status = Status::fetchAll($db);

        $out->opn('form', null, [
            'hx-post' => '/doku.php?plmapi=v1/versions',
            'hx-target' => '#versionedit',
            'hx-swap' => 'innerHTML',
        ]);

        HtmlBaseFactory::input($out, 'hidden', 'partId', $partId);
        HtmlBaseFactory::formGroup($out, 'ID', 'id', '', true);
        HtmlBaseFactory::formGroup($out, 'Name', 'name', '');
        HtmlBaseFactory::formGroup($out, 'Major Version', 'major', '');
        HtmlBaseFactory::formGroup($out, 'Revision', 'revision', '');
        HtmlBaseFactory::createStatusFormGroup($out, 'Status', 'status', $status, 1);

        // Submit button
        HtmlBaseFactory::formActions($out, [
            'create' => 'Create new'
        ]);

        $out->cls(); // Close form
    }

    public static function renderEditForm(HtmlBuilder $out, PlmDB $db, PartVersion $version): void {
        $status = Status::fetchAll($db);

        $out->opn('form', null, [
            'hx-post' => '/doku.php?plmapi=v1/parts/' . $version->pk,
            'hx-target' => '#partedit',
            'hx-swap' => 'innerHTML',
        ]);

        HtmlBaseFactory::input($out, 'hidden', 'partId', $version->partId);
        HtmlBaseFactory::formGroup($out, 'ID', 'id', $version->pk, true);
        HtmlBaseFactory::formGroup($out, 'Name', 'name', $version->name);
        HtmlBaseFactory::formGroup($out, 'Major Version', 'major', $version->major);
        HtmlBaseFactory::formGroup($out, 'Revision', 'revision', $version->revision);
        HtmlBaseFactory::createStatusFormGroup($out, 'Status', 'status', $status, $version->statusId);

        // Submit button
        HtmlBaseFactory::formActions($out, [
            'reset' => 'Clear Form',
            'save' => 'Submit',
            'delete' => 'Delete'
        ]);

        $out->cls(); // Close form
    }
}
