<?php

if (!defined('DOKU_INC')) {
    die();
}

class ApiPart extends ApiBase {

    public function __construct(PlmDB $db) {
        parent::__construct($db);
    }

    protected function renderPartForm(Writer $out, ?Part $part) : void {
        $context = [];

        if ($part) {
            $context['mode:edit'] = true;
            $context['part:id'] = $part->pk;
            $context['part:ipn'] = $part->ipn;
            $context['part:description'] = $part->description;
            $context['part:category'] = $part->categoryId;
        } else {
            $context['mode:create'] = true;
        }

        /** @var HtmlBakery $b */
        $b = new HtmlBakery();            

        $b->parse( $out, PLM_PLUGIN_TEMPLATE_DIR . '/part-form.xml', $context );
    }

    protected function doGet(Writer $out, array $nodePath): Event
    {
        $this->requireRole('reader');

        $evt = null;
        if (!empty($nodePath)) {
            $evt = $this->doGetSingle($out, $nodePath);
        } else {
            $evt = $this->doGetList($out, $nodePath);
        }
            
        return $evt;
    }

    protected function doGetSingle(Writer $out, array $nodePath): Event
    {
        $this->requireRole('reader');

        $id = $nodePath[0];
        $part = null;
        
        if ($id) {
            $part = Part::byPK($this->db(), (int)$id);
        }

        $this->renderPartForm($out, $part);
        if ($part !== null) {
            return Event::select('part', $part->pk);
        } else {
            return Event::reset('part');
        }
    }

    protected function doGetList(Writer $out, array $nodePath): Event
    {
        $html = new HtmlBuilder();
        $this->requireRole('reader');
        PartListFactory::renderPartList($html, $this->db());
        $html->flush($out);

        return Event::reset('part');
    }

    protected function doPost(Writer $out, array $nodePath): Event
    {
        // Access form data through DokuWiki's global input system
        global $INPUT;
        $submitAction = $INPUT->post->str('action', '');

        switch ($submitAction) {
            case 'create':
                return $this->doCreate($out, $nodePath);

            case 'save':
                return $this->doUpdate($out, $nodePath);

            case 'delete':
                return $this->doDelete($out, $nodePath);

            case 'reset':
                $this->renderPartForm($out, null);
                return Event::reset('part');

            default:
                ApiBase::error(400, 'Unknown submit action ' . $submitAction);
                return Event::none('part');
        }
    }

    private function getPk(array $nodePath): ?int
    {
        global $INPUT;
        // For normal update processing (save action)
        // Using primary key from URL to get current part data
        if ($INPUT->post->has('pk')) {
            return $INPUT->post->int('pk', 0);
        } else {
            if (!empty($nodePath)) {
                return $nodePath[0];
            } else {
                ApiBase::error(400, 'Primary key not sent for part update.');
            }
        }
    }

    protected function doCreate(Writer $out, array $nodePath): Event {
        $this->requireRole('author');

        // Access form data through DokuWiki's global input system
        global $INPUT;

        // Get form data for creating new part
        $ipn = $INPUT->post->str('ipn', '');
        $description = $INPUT->post->str('description', '');
        $categoryId = $INPUT->post->int('category', 0);

        // Fetch the category object
        $category = Category::byPK($this->db(), $categoryId);

        // Create new part object using factory method
        $part = Part::createNew($category, $ipn, $description);
        
        // Save the new part to the database (this will generate new ID)
        $part->save($this->db());

        $this->renderPartForm($out, $part);
        return Event::change('part', $part->pk);
    }

    protected function doUpdate(Writer $out, array $nodePath): Event {
        global $INPUT;

        $this->requireRole('author');

        $partId = $this->getPk($nodePath);
        $part = Part::byPK($this->db(), $partId);
        if ($part) {

            // Get form data for updating
            $ipn = $INPUT->post->str('ipn', '');
            $description = $INPUT->post->str('description', '');
            $categoryId = $INPUT->post->int('category', 0);

            // Update the part with new values
            $part->ipn = $ipn;
            $part->description = $description;
            $part->categoryId = $categoryId;

            // Save the updated part to database
            $part->save($this->db());

            // Show the updated form
            $this->renderPartForm($out, $part);
            return Event::change('part', $part->pk);
        } else {
            // If part doesn't exist, create a new one
            $this->renderPartForm($out, null);
            return Event::reset('part');
        }
    }

    protected function doDelete(Writer $out, array $nodePath): Event
    {
        $this->requireRole('admin');

        $partId = $this->getPk($nodePath);
        $part = Part::byPK($this->db(), $partId);
        if ($part) {
            $part->delete($this->db(), true);
        }

        $this->renderPartForm($out, null);
        return Event::change('part', $part->pk);
    }        
}