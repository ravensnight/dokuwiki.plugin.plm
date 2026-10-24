
class Products {
    
    constructor() {
        this.products = null;
        this.variants = null;
    }

    async setup() {
        this.products = new ItemContainer(
            '/doku.php?plm=/api/v1/content/products',
            {
                'title': 'Product',
                'fields': [
                    {
                        'name': 'id',
                        'label': 'ID',
                        'editable': false,
                    },
                    {
                        'name': 'name',
                        'label': 'Name',
                        'editable': true,
                        'select': 'id'
                    },
                    {
                        'name': 'description',
                        'label': 'Description',
                        'editable': true,
                    },
                ]
            },
            {
                'id' : null,
                'name' : '',
                'description': ''
            },

            (productId) => this.selectProduct(productId)
        );

        await this.products.loadItems();
    }

    async selectProduct(productId) {

        if (productId === null) {
            this.variants = null;
            return;
        }

        console.log("Set variants: " + productId);

        const defaults = {
            "id" : null,
            "name" : '',
            "description" : '',
            "productId" : productId
        };

        if (this.variants == null) {
            this.variants = new ItemContainer('/doku.php?plm=/api/v1/content/variants',
                {
                    'title': 'Variant',
                    'fields': [
                        {
                            'name': 'id',
                            'label': 'ID',
                            'editable': false,
                            'hidden': false
                        },
                        {
                            'name':'name',
                            'label':'Name',
                            'select' : null,
                            'editable': true,
                            'hidden': false
                        },
                        {
                            'name': 'description',
                            'label': 'Description',
                            'editable': true,
                            'hidden': false
                        },
                        {
                            'name': 'productId',
                            'label': 'Product ID',
                            'editable': false,
                            'hidden': true
                        },
                    ]
                },  
                defaults, 
                null
            );
        }
        else {
            this.variants.setDefaults(defaults);
        }

        this.variants.loadFiltered(`productId=${productId}`);
    }
}