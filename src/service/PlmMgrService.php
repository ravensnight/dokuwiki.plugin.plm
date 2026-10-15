<?php

/**
 * Service for fetching data related to the PLM Manager views.
 */
class PlmMgrService {

    /** @var PlmMgrBase[] $registry */
    private array $registry = [];

    /** @var PlmMgrService $me */
    private static ?PlmMgrService $me = null;

    private function __construct() {
    }

    public static function get() : PlmMgrService {
        if (PlmMgrService::$me === null) {
            PlmMgrService::$me = new PlmMgrService();
        }

        return PlmMgrService::$me;
    }

    private function getSubService(string $name) : PlmMgrBase {
        if (!isset($registry[$name])) {

            switch ($name) {
                case 'parts':
                    $registry[$name] = new PlmMgrParts();
                    break;

                case 'versions':
                    $registry[$name] = new PlmMgrVersions();
                    break;

                case 'products':
                    $registry[$name] = new PlmMgrProducts();
                    break;

                case 'companies':
                    $registry[$name] = new PlmMgrCompanies();
                    break;

                case 'status':
                    $registry[$name] = new PlmMgrStatus();
                    break;

                case 'companytypes':
                    $registry[$name] = new PlmMgrCompanyTypes();
                    break;

                case 'categories':
                    $registry[$name] = new PlmMgrCategories();
                    break;

                default:
                    throw new Exception("SubService not defined: " . $name);
            }
        }

        return $registry[$name];
    }

    /**
     * Get data for the requested view.
     * 
     * @param string $view
     * @return array
     */
    public function getData(string $view, array $params): array {

        $subService = $this->getSubService($view);
        if ($subService) {
            return $subService->getData($params);
        }

        throw new Exception('SubService not found: ' . $view);
    }

    public function create(string $view, mixed $inputData) : mixed {
        $subService = $this->getSubService($view);
        if ($subService) {
            return $subService->create($inputData);
        }

        throw new Exception('SubService not found: ' . $view);

    }

    public function update(string $view, mixed $inputData) : mixed {
        $subService = $this->getSubService($view);
        if ($subService) {
            return $subService->update($inputData);
        }

        throw new Exception('SubService not found: ' . $view);

    }

    public function delete(string $view, mixed $inputData) : void {
        $subService = $this->getSubService($view);
        if ($subService) {
            $subService->delete($inputData);
            return;
        }

        throw new Exception('SubService not found: ' . $view);
    }
}
