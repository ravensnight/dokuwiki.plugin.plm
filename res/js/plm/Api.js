class Api {

    constructor(uri) { 
        this.baseUri = `/doku.php?plm=/api/v1/${uri}`;
    }

    buildFilter(filter) {
        var res = '';
        if (filter != null) {
            Object.entries(filter).forEach(([key, val]) => {
                if(val == null) return;

                var xval = encodeURIComponent(val);
                if (res.length) {
                    res += `&${key}=${xval}`
                } else {
                    res += `?${key}=${xval}`
                }                
            });
        }

        return res;
    }

    /**
     * Fetch all entries
     */
    async all(filter = null, asJson = true) {
        var f = this.buildFilter(filter);

        var uri = `${this.baseUri}${f}`;
        var resp = await fetch(uri, {
            'method': 'GET',
            'Content-Type': asJson ? 'application/json' : 'text/plain',
            'Accept': asJson ? 'application/json' : 'text/plain'
        });

        if (!resp.ok) {
            throw new Error(`Fetch all request failed: ${resp.status} - ${await resp.text()}`);
        }

        if (asJson) {
            return await resp.json();
        } else {
            return await resp.text();
        }
    }

    /**
     * Fetch an item by id
     * @param {*} id 
     */
    async get(id, filter = null, asJson = true) {

        if (id == null) return null;

        var f = this.buildFilter(filter);
        var uri = `${this.baseUri}/${id}${f}`;
        var resp = await fetch(uri, {
            'method' : 'GET',
            'Content-Type': asJson ? 'application/json' : 'text/plain',
            'Accept': asJson ? 'application/json' : 'text/plain'
        });

        if (!resp.ok) {
            throw new Error(`Fetch item request failed: ${resp.status} - ${await resp.text()}`);
        }

        if (asJson) {
            return await resp.json();
        } else {
            return await resp.text();
        }
    }

    /**
     * Update some object named by <id>
     * @param {*} id 
     * @param {*} data 
     */
    async put(id, data) {
        if (id == null) throw new Error("Cannot put data to NULL");

        var uri = `${this.baseUri}/${id}`;
        const resp = await fetch(uri, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(data)
        });

        if (!resp.ok) {
            throw new Error(`Update item request failed: ${resp.status} - ${await resp.text()}`);
        }

        return await resp.json();
    }

    /**
     * Create some object posted to base uri
     * @param {*} data 
     */
    async post(data) {
        const resp = await fetch(this.baseUri, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(data)
        });

        if (!resp.ok) {
            throw new Error(`Create item request failed: ${resp.status} - ${await resp.text()}`);
        }

        return await resp.json();
    }

    /**
     * Delete some item by id.
     * @param {*} id 
     */
    async delete(id) {
        if (id == null) throw new Error("Cannot delete data with ID=NULL");

        var uri = `${this.baseUri}/${id}`;
        const resp = await fetch(uri, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        if (!resp.ok) {
            throw new Error(`Delete item request failed: ${resp.status} - ${await resp.text()}`);
        }
    }
}