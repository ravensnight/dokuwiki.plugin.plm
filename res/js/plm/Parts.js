class Parts {

    constructor() {
        this.categories = null;
        this.statusList = null;
        this.parts = null;
        this.versions = null;
    }

    async setup() {
        if (this.categories == null) {
            const response = await fetch('/doku.php?plm=/api/v1/content/categories');
            if (!response.ok) {
                throw new Error(`Failed to load categories: ${response.status} - ${response.text}`);
            }

            this.categories = await response.json();
        }

        if (this.statusList == null) {
            const response = await fetch('/doku.php?plm=/api/v1/content/status');
            if (!response.ok) {
                throw new Error(`Failed to load statús : ${response.status} - ${response.text}`);
            }

            this.statusList = await response.json();
        }

        this.parts = new ItemContainer(
            '/doku.php?plm=/api/v1/content/parts',
            {
                "title": "Part",
                'features': {
                    'create': {}, 'edit': {}, 'delete': {},
                    'select': { 'callback': (partId) => this.selectPart(partId) }
                },
                "fields" : [
                    {
                        'name': 'id',
                        'label': 'ID',
                        'editable': false
                    },
                    {
                        'name': 'ipn',
                        'label': 'IPN',
                        'editable': true,
                        'select': 'id'
                    },
                    {
                        'name': 'description',
                        'label': 'Description',
                        'editable': true
                    },
                    {
                        'name': 'categoryId',
                        'label': 'Category',
                        'editable': true,
                        'choice' : this.categories
                    },
                ]
            }, 
            {
                "id" : null,
                "ipn" : '',
                "description" : '',
                "categoryId": this.categories[0].id
            }
        )

        await this.parts.loadItems();
    }

    async selectPart(partId) {

        if (!partId) {
            this.versions = null;
            return;
        }

        const defaults = {
            "id" : null,
            "name" : '',
            "description" : '',
            "major" : 1,
            "revision" : 1,
            "statusId": this.statusList[0].id,
            "partId" : partId
        };

        if (this.versions == null) {

            this.versions = new ItemContainer(
                '/doku.php?plm=/api/v1/content/versions',
                {
                    "title" : "Part Version",
                    "features" : { 'create' : {}, 'edit' : {}, 'delete' : {}},
                    "fields" : [
                        {
                            "name": "id",
                            "label": "ID",
                            "editable": false,
                        },
                        {
                            "name" : "name",
                            "label" : "Name",
                            "editable" : true,
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
                            "name": "partId",
                            "label": "Part ID",
                            "editable": false,
                            "hidden": true,
                        }

                    ]
                },
                defaults
            );

        } else {
            this.versions.setDefaults(defaults);
        }

        await this.versions.loadFiltered(`partId=${partId}`);
    }
}
