class Page {

    async loadHtml(viewPart) {

        const response = await fetch(
            `/doku.php?plm=/api/v1/pages/${encodeURIComponent(viewPart)}`
        );

        if (!response.ok) {
            throw new Exception(`Failed to load view: ${response.status} - ${response.text()}`);
        }

        return await response.text();
    }

}