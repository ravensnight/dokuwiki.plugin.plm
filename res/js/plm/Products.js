class Products {

    constructor() {
        this.productUri = `/doku.php?plm=/api/v1/content/products`;
        this.variantUri = `/doku.php?plm=/api/v1/content/variants`;
        this.products = null;
        this.selectedProduct = null;
        this.productLoading = false;
        this.variants = null;
        this.selectedVariant = null;
    }

    async init() {
        this.products = await this.getProducts();
    }

    /** Load all products */
    async getProducts() {
        const response = await fetch(this.productUri);
        if (!response.ok) {
            throw new Error(`Failed to load product list: ${response.status} - ${response.text}`);
        }

        return response.json();
    }

    async selectProduct(id) {
        this.productLoading = true;

        try {
            this.selectedVariant = null;

            if (id) {

                var uri = `${this.productUri}/${id}`;
                const resp = await fetch(uri);
                if (!resp.ok) {
                    throw new Error(`Failed to load product data: : ${resp.status} - ${resp.text}`)
                }

                this.selectedProduct = await resp.json();
                this.variants = await this.getVariants(id);

            } else {
                this.selectedProduct = {
                    'id': null,
                    'name': '',
                    'description': ''
                };

                this.variants = null;
            }

        } finally {
            this.productLoading = false;
        }
    }

    async getVariants(prodId) {
        var uri = `${this.variantUri}?productId=${prodId}`;
        const response = await fetch(uri);

        if (!response.ok) {
            throw new Error(`Failed to load variant list: : ${response.status} - ${response.text}`)
        }

        return await response.json();
    }

    async selectVariant(varId) {

        if (varId) {
            var uri = `${this.variantUri}/${varId}`;
            const response = await fetch(uri);

            if (!response.ok) {
                throw new Error(`Failed to load variant: ${varId}, ${response.status} - ${response.text}`);
            }

            this.selectedVariant = await response.json();
        } else {

            this.selectedVariant = {
                'id' : null,
                'name' : '',
                'description' : '',
                'productId' : this.selectedProduct.id
            }
        }
    }

    async deleteVariant(varId) {
        var uri = `${this.variantUri}/${varId}`;

        const response = await fetch(uri, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to load variant: ${varId}, ${response.status} - ${response.text}`);
        }

        this.selectedVariant = null;
        this.variants = await this.getVariants(this.selectedProduct.id);
    }

    async submitVariant(action) {
        var uri = this.variantUri;
        var method = 'POST'; // create

        switch (action) {
            case 'reset': {
                this.selectVariant(null);
                return;
            }

            case 'submit': {
                // create or update                
                if (this.selectedVariant.id) {
                    uri += `/${this.selectedVariant.id}`;
                    method = 'PUT'; // Update
                }
                break;
            }

            default: // CREATE: do nothing by default
                return;
        }

        const response = await fetch(uri, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(this.selectedVariant)
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.selectedVariant = await response.json();
        this.variants = await this.getVariants(this.selectedProduct.id);
    }    
    
    async submitProduct(action) {
        var uri = this.productUri;
        var method = 'POST'; // create

        switch (action) {
            case 'reset': {
                this.selectProduct(null);
                return;
            }

            case 'submit': {
                // create or update                
                if (this.selectedProduct.id) {
                    uri += `/${this.selectedProduct.id}`;
                    method = 'PUT'; // Update
                }
                break;
            }

            default: // CREATE: do nothing by default
                return;
        }

        const response = await fetch(uri, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(this.selectedProduct)
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.selectedProduct = await response.json();
        this.products = await this.getProducts();
    }

    async deleteProduct(id) {
        var uri = `${this.productUri}/${id}`;

        const response = await fetch(uri, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.selectProduct(null);
        this.products = await this.getProducts();

    }
}