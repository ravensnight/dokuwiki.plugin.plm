class Products {

    /** Load all products */
    async getProducts() {
        const response = await fetch(
            `/doku.php?plm=/api/v1/content/products`
        );

        if (!response.ok) {
            throw new Error(`Failed to load view data: ${response.status} - ${response.text}`);
        }

        return response.json();
    }
}