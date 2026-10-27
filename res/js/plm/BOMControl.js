class BOMControl {

    constructor(context) {

        if (context == null) {
            throw new Error("Context must not be empty");
        }

        this.variantId = context['variantId'];

        this.variant = null;
        this.product = null;
    }

    async load(uri) {
        const resp = await fetch(uri);
        if (!resp.ok) {
            throw new Error(`Failed to variant: ${resp.status} - ${resp.text()}`);
        }

        return await resp.json();
    }

    async setup() {

        this.variant = await this.load(`/doku.php?plm=/api/v1/content/variants/${this.variantId}`);

        if (this.variant) {
            this.product = await this.load(`/doku.php?plm=/api/v1/content/products/${this.variant.productId}`);

            this.variantParts = new ItemContainer('/doku.php?plm=/api/v1/content/variantitems',
                {
                    "title" : "Variant Part Items",
                    "features" : {
                    },
                    'fields': [
                        {
                            'name': 'id',
                            'label': 'ID',
                        },
                        {
                            'name': 'partIPN',
                            'label': 'IPN',
                        },
                        {
                            'name': 'partDescription',
                            'label': 'Description',
                        },
                        {
                            'name': 'versionName',
                            'label': 'VersionName',
                        },
                        {
                            'name': 'versionMajor',
                            'label': 'Major',
                        },
                        {
                            'name': 'versionRevision',
                            'label': 'Revision',
                        },
                        {
                            'name': 'quantity',
                            'label': 'Quantity',
                            'editable' : true
                        }
                    ]
                },
                null, // defaults
            );

            this.variantParts.loadFiltered(`variantId=${this.variant.id}`);
        }
    } 
}
