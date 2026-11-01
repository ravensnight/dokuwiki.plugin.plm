
class Products {
    
    constructor(appControl) {
        this.appControl = appControl;
        this.statusList = null;
        this.products = null;
        this.variants = null;
        this.variantParts = null;
        this.categories = null;
    }

    async setup() {

        var api = new Api('content/categories');
        this.categories = await api.all();

        api = new Api('content/status');
        this.statusList = await api.all();

        this.products = new ItemContainer(
            'content/products',
            {
                'title': 'Product List',
                'select': (product) => this.selectProduct(product),
                'actions' : null, // adds default actions create/edit/delete
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

        if (product == null) {
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
            "revisionId" : null
        };

        if (this.variants == null) {
            this.variants = new ItemContainer('content/variants',
                {
                    'title': 'Product Variants',
                    'select': (variant) => this.selectVariant(variant),
                    'actions': null, // adds default actions create/edit/delete
                    'fields': [
                        {
                            'name': 'id',
                            'label': 'ID',
                        },
                        {
                            'name':'name',
                            'label':'Name',
                            'select' : true,
                            'editable': true,
                        },
                        {
                            'name': 'description',
                            'label': 'Description',
                            'editable': true,
                        },
                        {
                            'name': 'productId',
                            'label': 'Product ID',
                            'hidden': true
                        },
                        {
                            'name': 'revisionTag',
                            'label': 'Assigned Part Revision',
                        },
                    ]
                },  
                defaultData, 
            );
        }
        else {
            this.variants.setDefaults(defaultData);
        }

        this.variants.loadFiltered({
            'productId' : `${product.id}`
        });
    }

    async selectVariant(variant) {    

        if (variant == null) {
            this.variant = null;
            this.variantParts = null;
            return;
        }

        this.variant = variant;

        if (!this.variantParts) {
            this.variantParts = new ItemContainer(
                'content/versions',
                {
                    "title": "Assignable Part Versions",
                    'select': (partVersion, field) => this.selectRevision(partVersion, field),
                    'actions': {
                    },
                    "fields": [
                        {
                            "name": "id",
                            "label": "ID",
                        },
                        {
                            "name": "revisionTag",
                            "label": "Revision",
                            'select': true,
                            "html": (item) => { return `<span>${item.partDescription}</span><span class='small' >${item.revisionTag}</span>` }
                        },
                        {
                            'name': 'partCategoryId',
                            'label': 'Category',
                            'choice': this.categories,
                        },
                        {
                            "name": "reference",
                            "label": "Reference",
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
                            "html": (item) => { return (item.id == this.variant.revisionId) ? "<i class='fa fa-check-square-o' >" : "<i class='fa fa-square-o' >"; }
                        }
                    ]
                },
                null,
            );
        }

        await this.variantParts.loadItems(true);
    }

    async selectRevision(revision, field) {
        switch (field) {
            case 'revisionTag':
                await this.appControl.loadView('revision', revision);
                break;

            case 'assign':
                await this.assignRevision(revision.id);
                console.log(`Assign part version ID: ${revision.id}`);
                break;

            default:
                console.log(`${field} select for version ID: ${revision.id}`);
                break;
        }        
    }

    async assignRevision(revisionId) {

        if (this.variant === null || this.variant.id === null) return;


        // settings for create
        var api = new Api('content/variants');

        this.variant =await api.put(this.variant.id, {
            'revisionId' : revisionId
        });

        this.variants.loadItems(true);
    }
}