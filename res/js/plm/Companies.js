class Companies {

    constructor() {
        this.uri = '/doku.php?plm=/api/v1/content/companies';
        this.uriTypes = '/doku.php?plm=/api/v1/content/companytypes';

        this.companies = null;
        this.companyTypes = null;
        this.companySelected = null;        
    }

    async init() {

        const types = await fetch(this.uriTypes);
        if (!types.ok) {
            throw new Error(`Failed to load list of company types: ${types.status} - ${types.text}`);
        }

        this.companyTypes = await types.json();
        this.companies = await this.getCompanies();
        this.companySelected = null;
    }    

    async getCompanyType(typeId) {
        const result = this.companyTypes.find(type => type.id === typeId);
        return result.name;
    }

    /** Load all parts */
    async getCompanies() {
        const response = await fetch(this.uri);
        if (!response.ok) {
            throw new Error(`Failed to load list of companies: ${response.status} - ${response.text}`);
        }

        return response.json();
    }

    async selectCompany(compId) {
        if (compId == null) {
            this.companySelected = {
                "id" : null,
                "name" : '',
                "description" : '',
                "typeId" : this.companyTypes[0].id
            }

            return;
        }

        const response = await fetch(this.uri + `/${compId}`);
        if (!response.ok) {
            throw new Error(`Failed to load company: ${response.status} - ${response.text}`);
        }

        this.companySelected = await response.json();
    }

    async submitCompany(action) {

        var uri = this.uri;
        var method = 'POST'; // create

        switch (action) {
            case 'reset': {
                this.selectCompany(null);
                return;
            }

            case 'submit': {
                // create or update                
                if (this.companySelected.id) {
                    uri += `/${this.companySelected.id}`;
                    method = 'PUT'; // Update
                }
                break;
            }

            default: // CREATE: do nothing by default
                return;
        }

        const response = await fetch(uri, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(this.companySelected)
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.companySelected = await response.json();
        this.companies = await this.getCompanies();
    }    

    async deleteCompany(id) {

        var uri = this.uri + `/${id}`;

        const response = await fetch(uri, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.selectCompany(null);
        this.companies = this.getCompanies();
    }

}