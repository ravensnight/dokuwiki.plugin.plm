class ItemContainer {

    constructor(uri, metadata, defaults = null, cbSelect = null) {
        this.baseUri = uri;
        this.cbSelect = cbSelect;
        this.metadata = metadata;        
        this.filter = null;
        this.current = null;
        this.selectedId = null;
        this.items = null;

        this.setDefaults(defaults);
    }

    async html() {

        console.log("Fetch html.");

        const response = await fetch(
            `/doku.php?plm=/api/v1/pages/ItemContainer`
        );

        if (!response.ok) {
            throw new Exception(`Failed to load view: ${response.status} - ${response.text()}`);
        }

        console.log("Done Fetch html.");
        return await response.text();
    }

    setDefaults(defaults) {
        this.defaults = (defaults == null ? '{}' : JSON.stringify(defaults));        
    }

    async loadFiltered(filter) {
        if (filter == this.filter) return;

        this.filter = filter;
        this.current = null;
        this.selectedId = null;

        await this.loadItems(true);
    }

    async loadItems(force = false) {

        if ((this.items == null) || force) {
            const f = this.filter == null ? '' : '?' + this.filter;
            const uri = `${this.baseUri}${f}`;

            const response = await fetch(uri);
            if (!response.ok) {
                throw new Error(`Failed to load item list: ${response.status} - ${response.text}`);
            }

            console.log("Update items.");
            this.items = await response.json();
        }
    }

    async load(itemId) {
        const uri = `${this.baseUri}/${itemId}`;
        const resp = await fetch(uri);
        if (!resp.ok) {
            throw new Error(`Failed to load item data: : ${resp.status} - ${resp.text}`)
        }

        return await resp.json();                
    }

    async save() {
        if (this.current == null) return;

        // settings for create
        var method = 'POST'
        var uri = this.baseUri;

        if (this.current.id != null) {
            // settings to update existing
            uri += `/${this.current.id}`;
            method = 'PUT';
        }

        const response = await fetch(uri, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(this.current)
        });

        if (!response.ok) {
            throw new Error(`Create/Update Request failed: ${response.status} - ${await response.text()}`);
        }

        this.current = await response.json();
        this.loadItems(true);
    }

    async resolve(item, field) {

        if (!field || !item) return null;

        const value = item[field.name];

        if (field['choice'] != null) {
            const choice = field['choice'];
            const found = choice.find(entry => entry.id === value);

            return found.name;
        }
        else {
            return value;
        }
    }

    async doDelete(itemId) {
        if (itemId == null) return;

        var uri = `${this.baseUri}/${itemId}`;
        const response = await fetch(uri, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
        });

        if (!response.ok) {
            throw new Error(`Deletion request failed: ${response.status} - ${await response.text()}`);
        }
        
        await this.loadItems(true);        
    }

    async doSelect(itemId) {

        this.selectedId = itemId;                
        this.current = null;

        if (itemId == null || this.cbSelect == null) return;
        this.cbSelect(itemId);
    }

    async doEdit(itemId) {
        this.selectedId = itemId;
        if (this.cbSelect) {
            this.cbSelect(null);
        }

        if (itemId == null) {
            this.current = JSON.parse(this.defaults);
        } else {
            this.current = await this.load(itemId);
        }
    }

    async doSubmit(action) {
        switch (action) {
            case 'cancel':
                this.doSelect(null);
                return;

            case 'reset':
            case 'clear':
                await this.doEdit(null);
                return;

            case 'save':
            case 'submit':
                await this.save();
                return;

            case 'delete':
                await this.doDelete(this.current.id);
                return;

            default:
                throw new Error("Unknown action type: " + action);
        }
    }
}