<?php

/**
 * Renderer for PLM Manager views.
 * Handles the generation of HTML for the SPA interface.
 */
class ViewRenderer {

    /**
     * Renders the Parts view.
     * @param array $parts
     * @return string
     */
    public static function renderParts(array $parts): string {
        $html = '<table class="plm-table">
            <thead>
                <tr>
                    <th>IPN</th>
                    <th>Description</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($parts as $part) {
            $category = $part['category_name'] ?? 'N/A';
            $html .= "<tr>
                        <td>{$part['ipn']}</td>
                        <td>{$part['description']}</td>
                        <td>{$category}</td>
                      </tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Renders the Versions view.
     * @param array $versions
     * @return string
     */
    public static function renderVersions(array $versions): string {
        $html = '<table class="plm-table">
            <thead>
                <tr>
                    <th>Part ID</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($versions as $v) {
            $html .= "<tr>
                        <td>{$v['part_id']}</td>
                        <td>{$v['version_id']}</td>
                        <td>{$v['status_name']}</td>
                      </tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Renders the BOM view.
     * @param array $bom
     * @return string
     */
    public static function renderBom(array $bom): string {
        $html = '<table class="plm-table">
            <thead>
                <tr>
                    <th>Parent</th>
                    <th>Child</th>
                    <th>Qty</th>
                    <th>Designators</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($bom as $item) {
            $html .= "<tr>
                        <td>{$item['parent_version_id']}</td>
                        <td>{$item['child_version_id']}</td>
                        <td>{$item['quantity']}</td>
                        <td>{$item['designators']}</td>
                      </tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Renders the Products view.
     * @param array $products
     * @return string
     */
    public static function renderProducts(array $products): string {
        $html = '<table class="plm-table">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($products as $p) {
            $html .= "<tr>
                        <td>{$p['product_id']}</td>
                        <td>{$p['description']}</td>
                      </tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Renders the Companies view.
     * @param array $companies
     * @return string
     */
    public static function renderCompanies(array $companies): string {
        $html = '<table class="plm-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($companies as $c) {
            $html .= "<tr>
                        <td>{$c['id']}</td>
                        <td>{$c['name']}</td>
                        <td>{$c['description']}</td>
                        <td>{$c['type_name']}</td>
                      </tr>";
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Renders the Master Data view.
     * Displays a list of configuration entities (Categories, CompanyTypes, etc.)
     * with CRUD capabilities.
     * 
     * @param array $data
     * @return string
     */
    public static function renderMasterData(array $data): string {
        $html = '<div class="plm-master-data-container" x-data="masterDataCRUD()">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Master Data Configuration</h2>
                <div class="btn-group">
                    <button @click="openAddModal(\'Category\')" class="btn btn-success">Add Category</button>
                    <button @click="openAddModal(\'CompanyType\')" class="btn btn-success">Add Company Type</button>
                </div>
            </div>

            <div class="plm-master-data-table">
                <table class="plm-table">
                    <thead>
                        <tr>
                            <th>Entity Type</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($data as $entity) {
            $type = $entity['type'];
            $name = $entity['name'];
            $desc = $entity['description'];
            $link = $entity['link'];

            $html .= "<tr x-data=\"{\$entity: " . json_encode($entity) . "}\">
                        <td><strong x-text=\"\$entity.type\"></strong></td>
                        <td x-text=\"\$entity.name\"></td>
                        <td x-text=\"\$entity.description\"></td>
                        <td>
                            <button @click=\"openEditModal(\$entity)\" class=\"btn btn-primary\">Edit</button>
                            <button @click=\"confirmDelete(\$entity)\" class=\"btn btn-danger\">Delete</button>
                        </td>
                      </tr>";
        }

        $html .= '</tbody>
                </table>
            </div>

            <!-- Add/Edit Modal -->
            <div x-show="showModal" class="modal d-block" style="background: rgba(0,0,0,0.5);">
                <div class="modal-content p-4" style="background: white; max-width: 500px; margin: 10% auto;">
                    <h4 x-text="modalTitle"></h4>
                    <form @submit.prevent="submitForm">
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" x-model="formData.name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea x-model="formData.description" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Sort Order</label>
                            <input type="number" x-model="formData.sort_order" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Active</label>
                            <input type="checkbox" x-model="formData.active" class="form-check-input">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" @click="showModal = false" class="btn btn-secondary me-2">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        return $html;
    }
}