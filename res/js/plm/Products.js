
class Products {
    
    constructor(appControl) {
        this.statusList = null;
        this.products = null;
        this.variants = null;
        this.variantParts = null;
        this.appControl = appControl;
        this.partSelect = false;
    }

    async setup() {
        this.products = new ItemContainer(
            '/doku.php?plm=/api/v1/content/products',
            {
                'title': 'Product List',
                'features' : {
                    'create' : {}, 'edit' : {}, 'delete' : {},
                    'select': { 'callback' : (product) => this.selectProduct(product) }
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
                        'select': true,
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

    async selectProduct(product) {

        if (product === null) {
            this.selectVariant(null);
            this.variants = null;
            return;
        }

        // variants default data
        const defaultData = {
            "id" : null,
            "name" : '',
            "description" : '',
            "productId" : product.id,
            "partVersionId" : null
        };

        if (this.variants == null) {
            this.variants = new ItemContainer('/doku.php?plm=/api/v1/content/variants',
                {
                    'title': 'Product Variants',
                    'features': {
                        'create' : {}, 'edit' : {}, 'delete' : {},
                        'select' : { 'callback' : (variant) => this.selectVariant(variant) }
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
                            'select' : true,
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
                            'name': 'partVersionName',
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

        this.variants.loadFiltered(`productId=${product.id}`);
    }

    async selectVariant(variant) {    

        if (variant == null) {
            this.variant = null;
            this.variantParts = null;
            return;
        }

        this.variant = variant;

        /** Load status list, if not yet done */
        if (this.statusList == null) {
            const response = await fetch('/doku.php?plm=/api/v1/content/status');
            if (!response.ok) {
                throw new Error(`Failed to load statús : ${response.status} - ${response.text}`);
            }

            this.statusList = await response.json();
        }

        if (!this.variantParts) {
            this.variantParts = new ItemContainer(
                '/doku.php?plm=/api/v1/content/versions',
                {
                    "title": "Assignable Part Versions",
                    "features": { 
                        'select': { 'callback': (partVersion, field) => this.selectVersion(partVersion, field) }
                    },
                    "fields": [
                        {
                            "name": "id",
                            "label": "ID",
                        },
                        {
                            "name": "partIPN",
                            "label": "IPN",
                        },
                        {
                            "name": "name",
                            "label": "Name",
                            'select' : true
                        },
                        {
                            "name": "major",
                            "label": "Major Version",
                        },
                        {
                            "name": "revision",
                            "label": "Revision",
                        },
                        {
                            "name": "statusId",
                            "label": "Status",
                            "choice": this.statusList,
                        },
                        {
                            "name": "assign",
                            "label": "Assigned",
                            'select': true,
                            "value": (partVersion) => { return (partVersion.id == this.variant.partVersionId) ? 'Yes' : 'No'; }
                        }
                    ]
                },
                null,
            );
        }

        await this.variantParts.loadItems(true);
    }

    async selectVersion(partVersion, field) {
        switch (field) {
            case 'assign':
                await this.setPartVersion(partVersion.id);
                console.log(`Assign part version ID: ${partVersion.id}`);
                break;

            case 'name':
                await this.appControl.loadView('details', partVersion);
                return;

            default:
                console.log(`${field} select for version ID: ${partVersion.id}`);
                break;
        }        
    }

    async setPartVersion(partVersionId) {

        if (this.variant === null || this.variant.id === null) return;

        this.variant.partVersionId = partVersionId;

        // settings for create
        var uri = `/doku.php?plm=/api/v1/content/variants/${this.variant.id}`;

        const response = await fetch(uri, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(this.variant)
        });

        if (!response.ok) {
            throw new Error(`Update Request failed: ${response.status} - ${await response.text()}`);
        }

        this.variant = await response.json();
        this.variants.loadItems();

    }
}