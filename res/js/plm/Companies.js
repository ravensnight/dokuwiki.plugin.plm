class Companies {

    constructor() {
        this.companies = null;
    }

    async setup() {

        const types = await fetch('/doku.php?plm=/api/v1/content/companytypes');
        if (!types.ok) {
            throw new Error(`Failed to load list of company types: ${types.status} - ${types.text}`);
        }

        const companyTypes = await types.json();

        this.companies = new ItemContainer(
            '/doku.php?plm=/api/v1/content/companies',
            {
                'title': 'Company',
                'features' : null,
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
                    },
                    {
                        'name': 'description',
                        'label': 'Description',
                        'editable': true,
                    },
                    {
                        'name': 'typeId',
                        'label': 'Type ID',
                        'editable': true,
                        'choice': companyTypes,
                    }
                ]
            },
            {
                "id" : null,
                "name" : '',
                "description" : '',
                "typeId": companyTypes[0].id
            }
        );

        await this.companies.loadItems();
    } 
}