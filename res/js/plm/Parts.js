class Parts {

    constructor() {
        this.categories = null;
        this.partLoading = false;
        this.partSelected = null;
        this.partList = [];
        this.partVersions = [];

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

            const versions = await fetch(`${this.versionsURI}?partId=${partId}`);
            if (!versions.ok) {
                throw new Error(`Failed to load part versions ${partId}: ${versions.status} - ${versions.text}`);
            }

            this.partVersions = await versions.json();

        } else {
            this.partSelected = {
                'id' : null,
                'ipn' : '',
                'description' : '',
                'categoryId' : '1'
            };      

            this.partVersions = [];
        }

        this.partLoading = false;
    }

    async submitPartItem() {
        console.log(JSON.stringify(this.partSelected));

        var uri = this.partURI;
        if (this.partSelected.id) {
            uri += `/${this.partSelected.id}`;
        }

        const response = await fetch(uri, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(this.partSelected)
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }

        this.partList = await this.getParts();
        this.partSelected = await response.json();
    }
}