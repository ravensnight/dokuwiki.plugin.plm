<?php

if (!defined('DOKU_INC')) {
    die();
}

class ApiVersion extends ApiBase {

    public function __construct(PlmDB $db) {
        parent::__construct($db);
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
        $this->requireRole('reader');

        $id = $nodePath[0];
        $version = PartVersion::byPK($this->db(), (int)$id);

        if ($version == null) {
            ApiBase::error(400, "Version not found.");
            return Event::reset('version');
        }

        VersionFormFactory::renderEditForm($out, $this->db(), $version);
        return Event::select('version', $version->pk);
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
            case 'create':
                return $this->doCreate($out, $nodePath);

            case 'save':
                return $this->doUpdate($out, $nodePath);

            case 'reset':
                PartFormFactory::renderCreateForm($out, $this->db());
                return Event::reset('version');

            case 'delete':
                return $this->doDelete($out, $nodePath);

            default:
                ApiBase::error(400, 'Unknown submit action ' . $submitAction);
                return Event::none('version');
        }
    }


    protected function doCreate(HtmlBuilder $out, array $nodePath): Event {
        $this->requireRole('author');

        // Access form data through DokuWiki's global input system
        global $INPUT;

        // Get form data for creating new part
        $name = $INPUT->post->str('name', '');
        $major = $INPUT->post->int('major', 1);
        $revision = $INPUT->post->int('revision', 1);
        $statusId = $INPUT->post->int('status', 1);
        $partId = $INPUT->post->int('partId', 0);

        // Fetch the category object
        $s = Status::byPK($this->db(), $statusId);
        $p = Part::byPK($this->db(), $partId);

        // Create new part object using factory method
        $version = PartVersion::createNew($p, $s, $name, $major, $revision);
        
        // Save the new part to the database (this will generate new ID)
        $version->save($this->db());

        // Show the edit form for the newly created part
        VersionFormFactory::renderEditForm($out, $this->db(), $version, $partId);

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