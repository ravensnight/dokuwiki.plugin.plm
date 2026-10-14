class Companies {

    /** Load all parts */
    async getCompanies() {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/companies`
        );

        if (!response.ok) {
            throw new Error(`Failed to load view data: ${response.status} - ${response.text}`);
        }

        return response.json();
    }
}