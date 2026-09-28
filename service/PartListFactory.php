<?php

if (!defined('DOKU_INC')) {
    die();
}

class PartListFactory {

    /**
     * Renders the complete part list into a given HtmlBuilder
     * 
     * @param HtmlBuilder $html The HTML builder to render into
     * @param PlmDB $db The database connection
     * @return void
     */
    public static function renderPartList(HtmlBuilder $html, PlmDB $db): void
    {
        $html->opn('table')
            ->opn('thead')
            ->opn('tr')
            ->tag('th', 'ID', 'id')
            ->tag('th', 'IPN', 'ipn')
            ->tag('th', 'Description', 'description')
            ->tag('th', 'Category', 'category')
            ->cls() // tr
            ->cls() // thead
            ->opn('tbody');

        $partList = Part::entries($db);
        foreach ($partList as $p) {
            /** var Category */
            $cat = $p->fetchCategory($db);

            $html->opn('tr')
                ->tag('td', $p->pk, 'id')
                ->opn('td', 'ipn')
                ->opn('a', null, [
                    'href' => '/doku.php?plmapi=v1/parts/' . $p->pk,
                    'hx-get' => '/doku.php?plmapi=v1/parts/' . $p->pk,
                    'hx-target' => '#partedit',
                    'hx-swap' => 'innerHTML'
                ])
                ->add($p->ipn)
                ->cls() // a
                ->cls() // td
                ->tag('td', $p->description, 'description')
                ->tag('td', $cat->name, 'category')
                ->cls(); // tr
        }

        $html->cls() // tbody
            ->cls(); // table
    }

    /**
     * Renders the complete part list into a given HtmlBuilder
     * 
     * @param HtmlBuilder $html The HTML builder to render into
     * @param PlmDB $db The database connection
     * @param int $partId 
     * @return void
     */
    public static function renderPartVersionList(HtmlBuilder $html, PlmDB $db, int $partId): void {
        $html->opn('table')
            ->opn('thead')
            ->opn('tr')
            ->tag('th', 'ID', 'id')
            ->tag('th', 'Name', 'ipn')
            ->tag('th', 'Major Version', 'major')
            ->tag('th', 'Revision', 'revision')
            ->tag('th', 'Status', 'status')
            ->cls() // tr
            ->cls() // thead
            ->opn('tbody');

        $partVersion = PartVersion::byPartId($db, $partId);        
        foreach ($partVersion as $v) {

            /** @var Status $status */
            $status = $v->fetchStatus($db);

            $html->opn('tr')
                ->tag('td', $v->pk, 'id')
                ->opn('td', 'Name')
                ->opn('a', null, [
                    'href' => '/doku.php?plmapi=v1/versions/' . $v->pk,
                    'hx-get' => '/doku.php?plmapi=v1/versions/' . $v->pk,
                    'hx-target' => '#versionedit',
                    'hx-swap' => 'innerHTML'
                ])
                ->add($v->name)
                ->cls() // a
                ->cls() // td
                ->tag('td', $v->major, 'major')
                ->tag('td', $v->revision, 'revision')
                ->tag('td', $status->name, 'status')
                ->cls(); // tr
        }

        $html->cls() // tbody
            ->cls(); // table
    }
}