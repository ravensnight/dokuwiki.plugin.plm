class Parts {

    constructor(appControl, partId) {
        this.appControl = appControl;
        this.categories = null;
        this.statusList = null;
        this.parts = null;
        this.revisions = null;
        this.childList = null;
        this.partId = partId;
        this.tab = 'revisions';
    }

    async setup() {
        if (this.categories == null) {
            var api = new Api('content/categories');
            this.categories = await api.all();
        }

        if (this.statusList == null) {
            var api = new Api('content/status');
            this.statusList = await api.all();
        }

        this.parts = new ItemContainer(
            'content/parts',
            {
                "title": "Part Overview",
                'select': (part, field) => this.selectPart(part, field),
                'actions': null,
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

        if (this.partId != null) {
            await this.parts.loadFiltered({ 'partId' : this.partId });
        } else {
            await this.parts.loadItems(true);
        }
    }

    async setTab(tab) {
        this.tab = tab;
    }

    async selectPart(part, field) {

        this.revisionSelect(null, null);

        if (!part) {
            this.revisions = null;
            return;
        }

        /** Part version defaults */
        const defaults = {
            "id" : null,
            "revisionTag" : '',
            "reference" : '',
            "statusId": this.statusList[0].id,
            "partId" : part.id
        };

        if (this.revisions == null) {

            this.revisions = new ItemContainer(
                'content/versions',
                {
                    "title" : "Part Revisions",
                    'select': (item, field) => this.revisionSelect(item, field),
                    "actions" : null,
                    "fields" : [
                        {
                            "name": "id",
                            "label": "ID",
                            "editable": false,
                        },
                        {
                            "name" : "revisionTag",
                            "label" : "Revision",
                            "editable" : true,
                            'select' : true,
                            "html": (item) => { return `<span>${item.revisionTag}</span><span class='small' >${item.partDescription}</span>` }
                        },
                        {
                            "name": "statusId",
                            "label": "Status",
                            "editable": true,
                            "choice": this.statusList,
                        },
                        {
                            "name": "reference",
                            "label": "Reference",
                            "editable": true,
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
            this.revisions.setDefaults(defaults);
        }

        await this.revisions.loadFiltered({ "partId": part.id });
    }

    async revisionSelect(item, field) {
        if ((item !== null) && (field == 'revisionTag')) {
            this.appControl.loadView('revision', item);
        }
    } 

}
