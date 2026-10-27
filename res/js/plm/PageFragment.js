/**
 * Abstract PageFragment interface for PLM plugin
 * Defines the contract for page fragments that can render HTML content
 */
class PageFragment {

    /** Construction */
    constructor(htmlSource) {
        this.htmlSource = htmlSource;
    }

    /**
     * Method to render HTML content
     * @returns {string} HTML content of the page fragment
     */
    async html() {
        const response = await fetch(this.htmlSource)
        if (!response.ok) {
            throw new Exception(`Failed to load view: ${response.status} - ${response.text()}`);
        }

        return await response.text();
    }
}
