class Versions {

    /** Load all versions */
    async getVersions() {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/versions`
        );

        if (!response.ok) {
            throw new Error(`Failed to load view data: ${response.status} - ${response.text}`);
        }

        return response.json();
    }
}