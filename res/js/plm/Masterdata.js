class Masterdata {

    /** Load all categors */
    async getCategories() {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/categories`
        );

        if (!response.ok) {
            throw new Error(`Failed to load categories: ${response.status} - ${response.text}`);
        }

        return response.json();
    }

    /** Load all companytypes */
    async getCompanyTypes() {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/companytypes`
        );

        if (!response.ok) {
            throw new Error(`Failed to load company types: ${response.status} - ${response.text}`);
        }

        return response.json();
    }

    /** Load all companytypes */
    async getStatusList() {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/status`
        );

        if (!response.ok) {
            throw new Error(`Failed to load status list: ${response.status} - ${response.text}`);
        }

        return response.json();
    }
}