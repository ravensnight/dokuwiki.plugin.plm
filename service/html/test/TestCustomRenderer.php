<?php
/**
 * TestCustomRenderer Class
 * 
 * A test custom renderer implementation for demonstrating functionality.
 */
require_once '../core/AbstractRenderer.php';

class TestCustomRenderer extends AbstractRenderer
{
    /**
     * Render method for custom content handling
     * 
     * @param HtmlBuilder $html The HTML builder instance
     * @param mixed $content The content to render
     * @param mixed $data The data provided by DataProvider
     * @return void
     */
    public function render(HtmlBuilder $html, $content, $data = null)
    {
        // Create a table with the data
        if ($data && is_array($data)) {
            $html->addElement('table', ['class' => 'test-table'], function() use ($data) {
                $tableHtml = '<thead><tr><th>Name</th><th>Age</th></tr></thead><tbody>';
                foreach ($data as $person) {
                    $tableHtml .= '<tr><td>' . htmlspecialchars($person['name']) . '</td><td>' . htmlspecialchars($person['age']) . '</td></tr>';
                }
                $tableHtml .= '</tbody>';
                return $tableHtml;
            });
        } else {
            // Fall back to default rendering
            $html->addElement('span', [], (string)$content);
        }
    }
}
?>