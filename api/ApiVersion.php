<?php

if (!defined('DOKU_INC')) {
    die();
}

class ApiVersion extends ApiBase {

    public function __construct(PlmDB $db) {
        parent::__construct($db);
    }

    protected function doGet(HtmlBuilder $out, array $nodePath): Event
    {
        $this->requireRole('reader');

        if (!empty($nodePath)) {
            return $this->doGetSingle($out, $nodePath);
        } else {
            return $this->doGetList($out, $nodePath);
        }
    }

    protected function doGetSingle(HtmlBuilder $out, array $nodePath): Event
    {
        ApiBase::error(400, 'Not supported yet.');

        /**
        $this->requireRole('reader');

        $id = $nodePath[0];
        $part = Part::byPK($this->db(), (int)$id);

        if (!$part) {
            PartFormFactory::renderCreateForm($out, $this->db());
        } else {
            PartFormFactory::renderEditForm($out, $this->db(), $part);
        }

        return 'partselect';
         */
        return Event::none('version');
    }

    protected function doGetList(HtmlBuilder $out, array $nodePath): Event
    {
        $this->requireRole('reader');

        global $INPUT;
        $partId = $INPUT->get->int('partId');

        if ($partId !== 0) {
            PartListFactory::renderPartVersionList($out, PlmDB::get(), $partId);
        }

        return Event::reset('version');
    }

    protected function doPost(HtmlBuilder $out, array $nodePath): Event
    {        
        // Access form data through DokuWiki's global input system
        global $INPUT;
        $submitAction = $INPUT->post->str('action', '');

        switch ($submitAction) {
            /**
            case 'create':
                $this->doCreate($out, $nodePath);
                return 'partupdate';

            case 'save':
                $this->doUpdate($out, $nodePath);
                return 'partupdate';

            case 'reset':
                PartFormFactory::renderCreateForm($out, $this->db());
                return null;

            case 'delete':
                $this->doDelete($out, $nodePath);
                return 'partupdate';
            */
            default:
                ApiBase::error(400, 'Unknown submit action ' . $submitAction);
                return Event::none('version');
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

    protected function doCreate(HtmlBuilder $out, array $nodePath): Event {
        $this->requireRole('author');

        // Access form data through DokuWiki's global input system
        global $INPUT;

        // Get form data for creating new part
        /**
        $ipn = $INPUT->post->str('ipn', '');
        $description = $INPUT->post->str('description', '');
        $categoryId = $INPUT->post->int('category', 0);

        // Fetch the category object
        $category = Category::byPK($this->db(), $categoryId);

        // Create new part object using factory method
        $part = Part::createNew($category, $ipn, $description);
        
        // Save the new part to the database (this will generate new ID)
        $part->save($this->db());

        // Show the edit form for the newly created part
        PartFormFactory::renderEditForm($out, $this->db(), $part);
        */
        $this->error(400, "Not supported yet.");
        return Event::none('version');
    }

    protected function doUpdate(HtmlBuilder $out, array $nodePath): Event {
        global $INPUT;

        $this->requireRole('author');

        /**
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
            PartFormFactory::renderEditForm($out, $this->db(), $part);
        } else {
            // If part doesn't exist, create a new one
            PartFormFactory::renderCreateForm($out, $this->db());
        }
        */
        $this->error(400, "Not supported yet.");
        return Event::none('version');
    }

    protected function doDelete(HtmlBuilder $out, array $nodePath): Event
    {
        $this->requireRole('admin');

        /**
        $partId = $this->getPk($nodePath);
        $part = Part::byPK($this->db(), $partId);
        if ($part) {
            $part->delete($this->db(), true);
        }

        PartFormFactory::renderCreateForm($out, $this->db());
        */

        $this->error(400, "Not supported yet.");
        return Event::none('version');
    }        
}