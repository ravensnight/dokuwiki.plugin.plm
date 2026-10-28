class Parts {

    constructor(appControl) {
        this.appControl = appControl;
        this.categories = null;
        this.statusList = null;
        this.parts = null;
        this.versions = null;
        this.childList = null;
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
                "title": "Part Overview",
                'features': {
                    'create': {}, 'edit': {}, 'delete': {},
                    'select': { 'callback': (part, field) => this.selectPart(part, field) }
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
                        'select': true
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

    async selectPart(part, field) {

        this.versionSelect(null, null);

        if (!part) {
            this.versions = null;
            return;
        }

        /** Part version defaults */
        const defaults = {
            "id" : null,
            "name" : '',
            "description" : '',
            "major" : 1,
            "revision" : 1,
            "statusId": this.statusList[0].id,
            "partId" : part.id
        };

        if (this.versions == null) {

            this.versions = new ItemContainer(
                '/doku.php?plm=/api/v1/content/versions',
                {
                    "title" : "Part Versions",
                    "features" : { 
                        'create' : {}, 'edit' : {}, 'delete' : {},
                        'select' : { 'callback' : (item, field) => this.versionSelect(item, field) }
                    },
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
                            'select' : true
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

        await this.versions.loadFiltered(`partId=${part.id}`);
    }

    async versionSelect(item, field) {
        if ((item !== null) && (field === 'name')) {
            this.appControl.loadView('details', item);
        }
    } 

}
