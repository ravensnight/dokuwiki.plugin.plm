/**
 * Abstract PageFragment interface for PLM plugin
 * Defines the contract for page fragments that can render HTML content
 */
class PageFragment {

    /** Construction */
    constructor(fragmentUri) {
        this.fragmentUri = fragmentUri;        
    }

    /**
     * Method to render HTML content
     * @returns {string} HTML content of the page fragment
     */
    async html() {
        var api = new Api(this.fragmentUri);        
        return await api.all(null, false);
    }
}
