class Parts {

    constructor() {
        this.categories = null;
        this.partLoading = false;
        this.partSelected = null;
        this.partList = [];
        this.partVersions = null;

        this.partURI = '/doku.php?plm=/api/v1/content/parts';
        this.versionsURI = '/doku.php?plm=/api/v1/content/versions';

        this.selectPart(null);

    }

    async init() {
        if (this.categories) {
            return;
        }

        const response = await fetch('/doku.php?plm=/api/v1/content/categories');
        if (!response.ok) {
            throw new Error(`Failed to load categories: ${response.status} - ${response.text}`);
        }

        this.categories = await response.json();
        this.partList = await this.getParts();
    }

    async getCategoryName(catId) {
        const result = this.categories.find(cat => cat.id === catId);
        //console.log(JSON.stringify(result));
        return result.name;
    }

    async getCategories() {
        return this.categories;
    }

    /** Load all parts */
    async getParts() {
        const response = await fetch(this.partURI);
        if (!response.ok) {
            throw new Error(`Failed to load view data: ${response.status} - ${response.text}`);
        }

        return await response.json();
    }

    async getVersions(partId) {
        const versions = await fetch(`${this.versionsURI}?partId=${partId}`);
        if (!versions.ok) {
            throw new Error(`Failed to load part versions ${partId}: ${versions.status} - ${versions.text}`);
        }

        return await versions.json();
    }

    async selectPart(partId) {
        this.partLoading = true;

        /**
         * Load the part itself
         */
        if (partId) {
            const response = await fetch(`${this.partURI}/${partId}`);
            if (!response.ok) {
                throw new Error(`Failed to load part ${partId}: ${response.status} - ${response.text}`);
            }

            this.partSelected = await response.json();
            this.partVersions = await this.getVersions(partId);

        } else {
            this.partSelected = {
                'id' : null,
                'ipn' : '',
                'description' : '',
                'categoryId' : '1'
            };      

            this.partVersions = null;
        }

        this.partLoading = false;
    }

    async partSubmit(action) {

        var uri = this.partURI;
        var method = 'POST'; // create

        switch (action) {
            case 'reset': {
                this.selectPart(null);
                return;
            }

            case 'submit': {
                if (this.partSelected.id) {
                    uri += `/${this.partSelected.id}`;
                    method = 'PUT'; // Update
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

            body: JSON.stringify(this.partSelected)
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.partSelected = await response.json();
        this.partList = await this.getParts();
    }

    async deletePartItem(id) {

        var uri = this.partURI + `/${id}`;

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

        this.selectPart(null);
        this.partList = await this.getParts();

    }

    async deleteVersion(id) {

        var uri = this.versionsURI + `/${id}`;

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

        this.partVersions = await this.getVersions(this.partSelected.id);
    }

}