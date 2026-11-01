/**
 * ItemContainer class implementing PageFragment interface
 * Manages container for PLM items with CRUD operations
 */
class ItemContainer extends PageFragment {

    constructor(contentUri, metadata, defaultData = null) {

        super('pages/ItemContainer');

        this.api = new Api(contentUri);
        this.metadata = metadata;

        if (this.metadata['actions'] == null) {
            console.log("Add default actions");
            this.metadata['actions'] = {  // NULL assigns the defaults
                'edit' : {
                    'icon': 'fa fa-pencil-square-o',
                    'callback' : (item) => this.doEdit(item)
                }, 
                'delete' : {
                    'icon': 'fa fa-remove',
                    'callback': (item) => this.doDelete(item)
                }
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
            this.items = await this.api.all(this.filter);
        }
    }

    async load(itemId) {
        return await this.api.get(itemId);
    }

    async save() {
        if (!this.current || !this.editing) return;

        var result = null;
        if (this.current.id != null) {
            result = await this.api.put(this.current.id, this.current);
        } 
        else {
            result = await this.api.post(this.current);
        }

        this.loadItems(true);
        return result;
    }

    /**
     * Provide the field html for table rendering
     * @param {*} item 
     * @param {*} field 
     * @returns 
     */
    async fieldHtml(item, field) {
        if (!field || !item) return '';

        if (field['html'] != null) {
            var fnHtml = field['html'];
            return fnHtml(item);
        } else {
            return await this.fieldValue(item, field);
        }
    }

    /**
     * Provide the field value for edting and table (if no html is specified)
     * @param {*} item 
     * @param {*} field 
     * @returns 
     */
    async fieldValue(item, field) {

        if (!field || !item) return null;

        const value = item[field.name];
        if (field['choice'] != null) {
            const choice = field['choice'];
            const found = choice.find(entry => entry.id == value);

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

    async selectCallback(item, field) {
        var cb = this.metadata['select'];
        if (!cb) {
            return;
        }

        await cb(item, field);
    }

    async doSelect(item, field) {
        this.editing = false;
        this.current = item;
        this.selectCallback(item, field);
    }

    async itemAction(item, action) {
        if ((item == null) || (action == null)) return;

        var actionEntry = this.metadata.actions[action];
        if (actionEntry == null) return;

        var cb = actionEntry['callback']
        if (cb == null) return;
        
        this.current = null;
        this.current = await cb(item);

        await this.loadItems(true);
    }

    async doDelete(item) {
        if (!item) return;

        await this.api.delete(item.id);
        return null;
    }

    async doCreate() {
        this.selectCallback(null, null);
        this.editing = true;        
        this.current = JSON.parse(this.defaultData);    
    }

    async doEdit(item) {
        this.selectCallback(null, null);
        this.editing = true;     
        return item;
    }

    async doSubmit(action) {
        switch (action) {
            case 'cancel':
                this.doSelect(null);
                return;

            case 'reset':
            case 'clear':
                this.current = await this.doEdit(null);
                return;

            case 'delete':
                this.current = await this.doDelete(this.current.id);
                return;

            case 'save':
            case 'submit':
                this.current = await this.save();
                return;


            default:
                throw new Error("Unknown action type: " + action);
        }
    }
}