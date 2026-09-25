<?php

if (!defined('DOKU_INC')) {
    die();
}

class ApiPart extends ApiBase {

    protected function doGet(HtmlBuilder $out, array $nodePath): void
    {
        $this->requireRole('reader');

        if (!empty($nodePath)) {
            $this->doGetSingle($out, $nodePath);
        } else {
            $this->doGetList($out, $nodePath);
        }
    }

    protected function doGetSingle(HtmlBuilder $out, array $nodePath): void
    {
        $this->requireRole('reader');

        $id = $nodePath[0];
        $part = Part::byPK($this->db(), (int)$id);

        if (!$part) {
            ApiBase::error(404, 'Part not found');
            return;
        }

        // Fetch all categories
        $categories = Category::fetchAll($this->db());

        $out->opn('form', null, [
            'hx-put' => '/doku.php?plmapi=v1/parts/' . $part->pk,
            'hx-target' => '#partedit',
            'hx-swap' => 'innerHTML',
            'hx-indicator' => '#spinner'
            ]);

        // Create form groups
        $this->createFormGroup($out, 'ID', 'pk', $part->pk, true);
        $this->createFormGroup($out, 'IPN', 'ipn', $part->ipn);
        $this->createFormGroup($out, 'Description', 'description', $part->description);
        $this->createCategoryFormGroup($out, 'Category', 'categoryId', $categories, $part->categoryId);

        $out->tag('input', null, null, [
            'type' => 'submit',
            'value' => 'Save'
        ])
        
        ->cls(); // Close form
    }

    protected function doGetList(HtmlBuilder $out, array $nodePath): void
    {
        $this->requireRole('reader');

        $out->opn('table')
            ->opn('thead')
            ->opn('tr')
            ->tag('th', 'ID', 'id')
            ->tag('th', 'IPN', 'ipn')
            ->tag('th', 'Description', 'description')
            ->tag('th', 'Category', 'category')
            ->cls() // tr
            ->cls() // thead
            ->opn('tbody');

        $partList = Part::entries($this->db());
        foreach ($partList as $p) {
            /** var Category */
            $cat = $p->fetchCategory($this->db());

            $out->opn('tr')
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

        $out->cls() // tbody
            ->cls() // table
            ->tag('div', '', 'partedit', [
                'id' => 'partedit'
            ]);
    }

    protected function doCreate(HtmlBuilder $out, array $nodePath): void
    {
        $this->requireRole('author');
        ApiBase::error(400, 'Not yet implemented');
    }

    protected function doUpdate(HtmlBuilder $out, array $nodePath): void
    {
        $this->requireRole('author');
        ApiBase::error(400, 'Not yet implemented');
    }

    protected function doDelete(HtmlBuilder $out, array $nodePath): void
    {
        $this->requireRole('admin');
        ApiBase::error(400, 'Not yet implemented');
    }

    protected function createFormGroup(HtmlBuilder $out, string $label, string $name, $value, bool $readonly = false)
    {
        $out->opn('div', 'form-group')
            ->tag('label', $label)
            ->tag('input', null, null, [
                'type' => $readonly ? 'text' : 'text',
                'name' => $name,
                'value' => $value,
                'readonly' => $readonly ? 'readonly' : null
            ])
            ->cls(); // Close div
    }

    protected function createCategoryFormGroup(HtmlBuilder $out, string $label, string $name, array $categories, $selectedCategoryId)
    {
        $out->opn('div', 'form-group')
            ->tag('label', $label)
            ->opn('select', null, [
                'name' => $name,
            ]);

            foreach ($categories as $category) {

                $params = [
                    'value' => $category->id,
                ];

                if ($category->id == $selectedCategoryId) {
                    $params['selected'] = 'selected';
                }
                $out->tag('option', $category->description . ' (' . $category->name . ')', null, $params);
            }

        $out->cls() // Close select
            ->cls(); // Close div
    }
}