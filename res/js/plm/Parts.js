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
            },
            (partId) => this.selectPart(partId)
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
                defaults, 
                null
            );

        } else {
            this.versions.setDefaults(defaults);
        }

        await this.versions.loadFiltered(`partId=${partId}`);
    }

    /**
    async versionSubmit(action) {
        var uri = this.versionsURI;
        var method = 'POST'; // create

        var ver = null;

        switch (action) {
            case 'submit': 
                method = 'PUT'
                ver = this.partVersionEdit;
                uri = this.versionsURI + `/${ver.id}`;
                break;

            case 'nextRevision': {
                var maxRevision = 1;
                for (var r in this.partVersions) {
                    if (r.major == this.partVersionEdit.major) {
                        if (r.revision > maxRevision) {
                            maxRevision = r.revision;
                        }
                    }
                }

                var ver = {
                    "partId": this.partSelected.id,
                    "major": this.partVersionEdit.major,
                    "revision": maxRevision + 1,
                    "statusId": this.statusList[0].id
                }

                break;

            }

            case 'nextMajor': {
                var naxMajor = 1;
                for (var r in this.partVersions) {
                    if (r.major > naxMajor) {
                        naxMajor = r.major;
                    }
                }

                var ver = {
                    "partId": this.partSelected.id,
                    "major": naxMajor + 1,
                    "revision": 1,
                    "statusId": this.statusList[0].id
                }

                break;
            }

            case 'createDefault': {
                var ver = {
                    "partId": this.partSelected.id,
                    "major": 1,
                    "revision": 1,
                    "statusId": this.statusList[0].id
                }
                break;
            }

            default: // do nothing by default
                return;
        }

        const response = await fetch(uri, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(ver)
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.partVersions = await this.getVersions(this.partSelected.id);
        this.partVersionEdit = null;        
    }
    */
}
