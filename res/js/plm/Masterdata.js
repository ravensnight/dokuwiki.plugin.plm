class Masterdata {

    constructor() {
        this.categories = null;
        this.status = null;
        this.companyTypes = null;
    }

    async setup() {

        const yesNo = [
            { 'id': 0, 'name': 'No', 'description': "No/False" },
            { 'id': 1, 'name': 'Yes', 'description': "Yes/True" }
        ];

        /** Load categories */
        this.categories = new ItemContainer(
            '/doku.php?plm=/api/v1/content/categories',
            {
                'title' : "Category",
                'features': { 'create' : {}, 'edit' : {} },
                'fields' : [
                    {
                        'name': 'id',
                        'label': 'ID',
                        'editable': false,
                    },
                    {
                        'name': 'name',
                        'label': 'Name',
                        'editable': true,
                    },
                    {
                        'name': 'description',
                        'label': 'Description',
                        'editable': true,
                    },
                    {
                        'name': 'sortorder',
                        'label': 'Sort Order',
                        'editable': true,
                    },
                    {
                        'name': 'active',
                        'label': 'Activity Status',
                        'choice': yesNo,
                        'editable': true,
                    },              
                    {
                        'name': 'composite',
                        'label': 'Has Children',
                        'choice': yesNo,
                        'editable': true,
                    },              
                ]
            },
            {
                "id": null,
                "name": '',
                "description": '',
                "sortorder": 0,
                "active": 0,
                "composite": 0
            }
        );

        await this.categories.loadItems();


        const fieldList = [
            {
                'name': 'id',
                'label': 'ID',
                'editable': false,
            },
            {
                'name': 'name',
                'label': 'Name',
                'editable': true,
            },
            {
                'name': 'description',
                'label': 'Description',
                'editable': true,
            },
            {
                'name': 'sortorder',
                'label': 'Sort Order',
                'editable': true,
            },
            {
                'name': 'active',
                'label': 'Activity Status',
                'choice': yesNo,
                'editable': true,
            },
        ];

        const defaults = {
            "id": null,
            "name": '',
            "description": '',
            "sortorder": 0,
            "active": 1
        }

        /** Load status */
        this.status = new ItemContainer(
            '/doku.php?plm=/api/v1/content/status',
            {
                'title': "Status",
                'features': {},
                'fields': fieldList
            },
            defaults
        );

        await this.status.loadItems();

        /** Load company types */
        this.companyTypes = new ItemContainer(
            '/doku.php?plm=/api/v1/content/companytypes',
            {
                'title': "Company Type",
                'features': {},
                'fields': fieldList
            },
            defaults
        );

        await this.companyTypes.loadItems();

    }
}