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
        if (!isset($this->registry[$name])) {

            switch ($name) {
                case 'parts':
                    $this->registry[$name] = new PlmMgrParts();
                    break;

                case 'versions':
                    $this->registry[$name] = new PlmMgrVersions();
                    break;

                case 'products':
                    $this->registry[$name] = new PlmMgrProducts();
                    break;

                case 'variants':
                    $this->registry[$name] = new PlmMgrVariants();
                    break;

                case 'companies':
                    $this->registry[$name] = new PlmMgrCompanies();
                    break;

                case 'status':
                    $this->registry[$name] = new PlmMgrStatus();
                    break;

                case 'companytypes':
                    $this->registry[$name] = new PlmMgrCompanyTypes();
                    break;

                case 'categories':
                    $this->registry[$name] = new PlmMgrCategories();
                    break;

                case 'bom':
                    $this->registry[$name] = new PlmMgrBOM();
                    break;

                default:
                    throw new Exception("SubService not defined: " . $name);
            }
        }

        return $this->registry[$name];
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

    public function update(string $view, mixed $inputData, ?array $attrNames) : mixed {
        $subService = $this->getSubService($view);
        if ($subService) {
            return $subService->update($inputData, $attrNames);
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
