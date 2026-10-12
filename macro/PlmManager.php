<?php

/**
 * PLM Manager Macro
 * Provides a Single Page Application (SPA) interface for managing all PLM entities.
 *
 * Usage: {{plm:manager}}
 */

class PlmManager extends PlmMacro {
    private $config = [];

    public function __construct() {
        parent::__construct();
    }

    /**
     * Initialize the macro with configuration.
     * @param mixed $config
     * @throws Exception
     */
    public function init(mixed $config): void {
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Override function for rendering the content.
     * @param Writer $out
     */
    public function render(Writer $out): void {
        $view = $this->config['view'] ?? 'parts';

        // Get data for ALL views to properly populate SPA HTML properties
        $partsData = PlmManagerService::getData('parts');
        $versionsData = PlmManagerService::getData('versions');
        $bomData = PlmManagerService::getData('bom');
        $productsData = PlmManagerService::getData('products');
        $companiesData = PlmManagerService::getData('companies');
        $masterData = PlmManagerService::getData('master_data');

        // The SPA Shell
        // Alpine.js is assumed to be loaded automatically by the plugin.
        $html = '<div x-data="plmManager()" class="plm-manager-app">
                <header class="plm-manager-header">
                    <h1 class="plm-manager-title">PLM Management Console</1>
                    <nav class="plm-manager-nav">
                        <button @click="setView(\'parts\')">Parts</button>
                        <button @click="setView(\'versions\')">Versions</button>
                        <button @click="setView(\'bom\')">BOM</button>
                        <button @click="setView(\'products\')">Products</button>
                        <button @click="setView(\'companies\')">Companies</button>
                        <button @click="setView(\'master_data\')">Master Data</button>
                    </nav>
                </header>
                <main class="plm-manager-content">
                    <template x-if="currentView === \'parts\'">
                        <div x-html="partsHtml"></div>
                    </template>
                    <template x-if="currentView === \'versions\'">
                        <div x-html="versionsHtml"></div>
                    </template>
                    <template x-if="currentView === \'bom\'">
                        <div x-html="bomHtml"></div>
                    </template>
                    <template x-if="currentView === \'products\'">
                        <div x-html="productsHtml"></div>
                    </template>
                    <template x-if="currentView === \'companies\'">
                        <div x-html="companiesHtml"></div>
                    </template>
                    <template x-if="currentView === \'master_data\'">
                        <div x-html="masterDataHtml"></div>
                    </template>
                </main>
              </div>>';

        // Create the proper data structure that JavaScript expects
        $fullData = [
            'currentView' => $view,
            'partsHtml' => $partsData['html'],
            'versionsHtml' => $versionsData['html'],
            'bomHtml' => $bomData['html'],
            'productsHtml' => $productsData['html'],
            'companiesHtml' => $companiesData['html'],
            'masterDataHtml' => $masterData['html'],
            // Include the data for the current view for JavaScript functions to use
            'parts' => $partsData['parts'],
            'versions' => $versionsData['versions'],
            'bom' => $bomData['bom'],
            'products' => $productsData['products'],
            'companies' => $companiesData['companies'],
            'master_data' => $masterData['master_data']
        ];

        $out->write($html);
        $out->write("<script>window.plmManagerData = " . json_encode($fullData) . ";</script>");
    }
}
