class Masterdata {

    constructor() {
        this.categories = null;
        this.status = null;
        this.companyTypes = null;
    }

    async setup() {

        const activityStatus = [
            { 'id': false, 'name': 'Inactive', 'description': "Inactive" },
            { 'id': true, 'name': 'Active', 'description': "Active" }
        ];

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
                'name': 'status',
                'label': 'Activity Status',
                'choice': activityStatus,
                'editable': true,
            },
        ];

        const defaults = {
            "id" : null,
            "name" : '',
            "description" : '',
            "sortorder" : 0,
            "status" : 1
        }

        /** Load categories */
        this.categories = new ItemContainer(
            '/doku.php?plm=/api/v1/content/categories',
            {
                'title' : "Category",
                'fields' : fieldList
            },
            defaults, 
            null
        );

        await this.categories.loadItems();

        /** Load status */
        this.status = new ItemContainer(
            '/doku.php?plm=/api/v1/content/status',
            {
                'title': "Status",
                'fields': fieldList
            },
            defaults,
            null
        );

        await this.status.loadItems();

        /** Load company types */
        this.companyTypes = new ItemContainer(
            '/doku.php?plm=/api/v1/content/companytypes',
            {
                'title': "Company Type",
                'fields': fieldList
            },
            defaults,
            null
        );

        await this.companyTypes.loadItems();

    }
}