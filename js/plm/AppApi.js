class AppApi {
    async loadView(view) {
        const response = await fetch(
            `/doku.php?plm=/api/v1/pages/${encodeURIComponent(view)}`
        );

        if (!response.ok) {
            throw new Error(`Failed to load view: ${response.status} - ${response.text}`);
        }

        return response.text();
    }

    async loadViewData(view) {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/${encodeURIComponent(view)}`
        );

        if (!response.ok) {
            throw new Error(`Failed to load view data: ${response.status} - ${response.text}`);
        }

        return response.json();
    }
}
