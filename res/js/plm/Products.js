
class Products {
    
    constructor(appController) {
        this.products = null;
        this.variants = null;
        this.variantParts = null;
        this.appControl = appController;
        this.partSelect = false;
    }

    async setup() {
        this.products = new ItemContainer(
            '/doku.php?plm=/api/v1/content/products',
            {
                'title': 'Product',
                'features' : {
                    'create' : {}, 'edit' : {}, 'delete' : {},
                    'select': { 'callback' : (productId) => this.selectProduct(productId) }
                },
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
                    }
                ]
            },
            {
                'id' : null,
                'name' : '',
                'description': ''
            }
        );

        await this.products.loadItems();
    }

    async selectProduct(productId) {

        if (productId === null) {
            this.variants = null;
            return;
        }

        const defaultData = {
            "id" : null,
            "name" : '',
            "description" : '',
            "productId" : productId,
            "partVersionId" : null
        };

        if (this.variants == null) {
            this.variants = new ItemContainer('/doku.php?plm=/api/v1/content/variants',
                {
                    'title': 'Variant',
                    'features': {
                        'create' : {}, 'edit' : {}, 'delete' : {},
                        'select' : { 'callback' : (row) => this.selectVariant(row) }
                    },
                    'fields': [
                        {
                            'name': 'id',
                            'label': 'ID',
                            'editable': false,
                            'hidden': false,
                        },
                        {
                            'name':'name',
                            'label':'Name',
                            'select' : 'id',
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
                        {
                            'name': 'partVersionId',
                            'label': 'Master Part Version',
                            'editable': false,
                        },
                    ]
                },  
                defaultData, 
            );
        }
        else {
            this.variants.setDefaults(defaultData);
        }

        this.variants.loadFiltered(`productId=${productId}`);
    }

    async selectVariant(variantId) {    

        if (variantId == null) {
            this.variant = null;
            this.variantParts = null;
            return;
        }

        var resp = await fetch(`/doku.php?plm=/api/v1/content/variants/${variantId}`);
        if (!resp.ok) {
            throw new Error(`Failed to load variant: ${response.status} - ${response.text}`);
        }

        this.variant = await resp.json();

        if (!this.variantParts) {
            this.variantParts = new ItemContainer(
                '/doku.php?plm=/api/v1/content/versions',
                {
                    "title": "Part Version",
                    "features": { 
                        'create': {}, 'edit': {}, 'delete': {},
                        'select' : { 'callback' : (versionId) => this.setPartVersionId(versionId) }
                    },
                    "fields": [
                        {
                            "name": "id",
                            "label": "ID",
                            "editable": false,
                        },
                        {
                            "name": "name",
                            "label": "Name",
                            "select" : "id",
                            "editable": true,
                        },
                        {
                            "name": "major",
                            "label": "Major Version",
                            "editable": true,
                        },
                        {
                            "name": "revision",
                            "label": "Revision",
                            "editable": true,
                        },
                        {
                            "name": "statusId",
                            "label": "Status",
                            "editable": true,
                            "choice": this.statusList,
                        },
                        {
                            "name": "selected",
                            "label": "Selected",
                            "editable": false,
                            "value": (item) => { return item.id == this.variant.partVersionId; }
                        }
                    ]
                },
                null,
            );
        }

        await this.variantParts.loadItems();
    }

    async setPartVersionId(versionId) {
        console.log("Assign part version ID: " + versionId);
    }
}