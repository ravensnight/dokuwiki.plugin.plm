/**
 * ItemContainer class implementing PageFragment interface
 * Manages container for PLM items with CRUD operations
 */
class ItemContainer extends PageFragment {

    constructor(uri, metadata, defaultData = null) {

        super('/doku.php?plm=/api/v1/pages/ItemContainer');

        this.baseUri = uri;
        this.metadata = metadata;

        if (this.metadata['features'] === null) {
            this.metadata['features'] = {  // NULL assigns the defaults
                'create' : {},
                'edit' : {}, 
                'delete' : {}
            };
        }

        this.filter = null;
        this.current = null;
        this.editing = false;
        this.items = null;

        this.setDefaults(defaultData);
    }

    setDefaults(defaultData) {
        this.defaultData = (defaultData) ? JSON.stringify(defaultData) : null;
    }

    async loadFiltered(filter) {
        if (filter == this.filter) return;

        this.filter = filter;
        this.current = null;
        this.editing = null;

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
        if (!this.current || !this.editing) return;

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
        else if (field['value'] != null) {
            var callback = field['value'];
            var res = callback(item);
            
            return res;
        }
        else {
            return value;            
        }
    }

    async callback(feature, item, field) {
        var f = this.metadata.features[feature];
        if (!f) return;

        var cb = f['callback'];
        if (!cb) return;

        cb(item, field);
    }

    async doDelete(itemId) {

        if (!itemId || !this.metadata.features['delete']) return;

        cb = this.metadata.features.delete['callback'];
        if (cb) {
            cb(itemId);
            return;
        } 
        else {
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
    }

    async doSelect(item, field) {

        /*
        if (item) {
            console.log(`Select called: ${item.id}, ${field}`);
        } else {
            console.log(`Select called: NULL, ${field}`);
        }
        */

        this.editing = false;
        
        if (this.metadata.features['select'] && (item !== null)) {
            this.current = item;
        } else {
            this.current = null;
        }

        this.callback('select', item, field);
    }

    async doEdit(itemId) {

        this.callback('select', null);

        if (itemId == null) {
            if (this.metadata.features['create']) {
                this.current = JSON.parse(this.defaultData);
                this.editing = true;
            } else {
                this.current = null;
                this.editing = false;
            }
        } else {
            if (this.metadata.features['edit']) {
                this.current = await this.load(itemId);
                this.editing = true;
            } else {
                this.current = null;
                this.editing = false;
            }
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