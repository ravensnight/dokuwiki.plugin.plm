<?php

if (!defined('DOKU_INC')) {
    die();
}

class ApiManager extends ApiBase {
    /**
     * @param PlmDB $db
     */
    public function __construct(PlmDB $db) {
        parent::__construct($db);
    }

    /**
     * GET /api/manager?view={parts|versions|bom|products|companies|master_data}
     * 
     * @param Writer $out
     * @param array $nodePath
     * @return Event
     */
    protected function doGet(Writer $out, array $nodePath): Event
    {
        global $INPUT;
        $view = $INPUT->get->str('view', 'parts');
        
        $allowedViews = ['parts', 'versions', 'bom', 'products', 'companies', 'master_data'];
        if (!in_array($view, $allowedViews)) {
            ApiBase::error(400, 'Invalid view requested.');
        }

        $data = PlmManagerService::getData($view);
        
        $out->write(json_encode([
            'html' => $data['html'],
            'data' => $data['master_data'] ?? $data,
            'status' => 'success'
        ]));

        return Event::none('manager');
    }

    /**
     * POST /api/manager?view=master_data
     * 
     * @param Writer $out
     * @param array $nodePath
     * @return Event
     */
    protected function doPost(Writer $out, array $nodePath): Event
    {
        global $INPUT;
        $view = $INPUT->get->view ?? '';
        
        if ($view !== 'master_data') {
            ApiBase::error(400, 'View must be master_data for POST requests.');
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            ApiBase::error(400, 'Invalid JSON body.');
        }

        $type = $body['type'] ?? null;
        if (!$type) {
            ApiBase::error(400, 'Missing type.');
        }

        PlmManagerService::createMasterData($type, $body);

        $out->write(json_encode([
            'status' => 'success',
            'message' => 'Entity created successfully'
        ]));

        return Event::none('manager');
    }

    /**
     * PUT /api/manager?view=master_data&id={pk}
     * 
     * @param Writer $out
     * @param array $nodePath
     * @return Event
     */
    protected function doPut(Writer $out, array $nodePath): Event
    {
        global $INPUT;
        $view = $INPUT->get->view ?? '';
        $pk = $INPUT->get->id ?? null;

        if ($view !== 'master_data') {
            ApiBase::error(400, 'View must be master_data for PUT requests.');
        }
        if (!$pk) {
            ApiBase::error(400, 'Missing ID.');
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            ApiBase::error(400, 'Invalid JSON body.');
        }

        $type = $body['type'] ?? null;
        
        PlmManagerService::updateMasterData($type, (int)$pk, $body);

        $out->write(json_encode([
            'status' => 'success',
            'message' => 'Entity updated successfully'
        ]));

        return Event::none('manager');
    }

    /**
     * DELETE /api/manager?view=master_data&id={pk}
     * 
     * @param Writer $out
     * @param array $nodePath
     * @return Event
     */
    protected function doDelete(Writer $out, array $nodePath): Event
    {
        global $INPUT;
        $view = $INPUT->get->view ?? '';
        $pk = $INPUT->get->id ?? null;

        if ($view !== 'master_data') {
            ApiBase::error(400, 'View must be master_data for DELETE requests.');
        }
        if (!$pk) {
            ApiBase::error(400, 'Missing ID.');
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $type = $body['type'] ?? null;

        PlmManagerService::deleteMasterData($type, (int)$pk);

        $out->write(json_encode([
            'status' => 'success',
            'message' => 'Entity deleted successfully'
        ]));

        return Event::none('manager');
    }
}
