class Parts {

    constructor() {
        this.categories = null;
        this.statusList = null;
        this.partLoading = false;
        this.partSelected = null;
        this.partList = [];
        this.partVersions = null;

        this.partURI = '/doku.php?plm=/api/v1/content/parts';
        this.versionsURI = '/doku.php?plm=/api/v1/content/versions';

        this.selectPart(null);

    }

    async init() {
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

        this.partList = await this.getParts();
    }

    async getCategories() {
        return this.categories;
    }

    async getCategoryName(catId) {
        const result = this.categories.find(cat => cat.id === catId);
        return result.name;
    }

    async getStatus() {
        return this.statusList;
    }

    async getStatusName(statusId) {
        const result = this.statusList.find(stat => stat.id === statusId);
        return result.name;
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
            this.partVersionEdit = null;

        } else {
            this.partSelected = {
                'id' : null,
                'ipn' : '',
                'description' : '',
                'categoryId' : '1'
            };      

            this.partVersions = null;
            this.partVersionEdit = null;
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

            default: // CREATE: do nothing by default
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
        this.partVersions = null;
        this.partVersionEdit = null;

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
        this.partVersions = null;
        this.partVersionEdit = null;

    }

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
        this.partVersionEdit = null;
    }

    async selectVersion(id) {

        var uri = this.versionsURI + `/${id}`;

        const response = await fetch(uri, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status} - ${await response.text()}`);
        }
        
        var nextMajor = 0;
        var nextRevision = 0;

        for (var row in this.partVersions) {
            if (this.partVersions[row].major > nextMajor) {
                nextMajor = this.partVersions[row].major;
            }
            if (this.partVersions[row].revision > nextRevision) {
                nextRevision = this.partVersions[row].revision;
            }
        }

        this.partVersionEdit = await response.json();
    }
}
