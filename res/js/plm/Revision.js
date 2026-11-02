class Revision  {

    constructor(appControl, revisionId) {
        this.baseuri = `content/bom`;
        this.appControl = appControl;
        this.revisionId = revisionId;
        this.statusList = null;
        this.categories = null;
        this.revision = null;
        this.variantList = null;
        this.selectedChild = null;
    }

    async setup() {

        if (this.statusList == null) {
            var api = new Api('content/status');
            this.statusList = await api.all();
        }

        if (this.categories == null) {
            var api = new Api('content/categories');
            this.categories = await api.all();
        }

        // Assign Context to revision... now statusList is loaded.
        if (this.revisionId) {
            var api = new Api('content/versions');
            this.revision = await api.get(this.revisionId);
        } else {
            this.revision = null;
        }

        // check composite feature
        if (this.revision) {
            if (!this.hasChildren(this.revision.partCategoryId)) {
                this.childList = null;
                return;
            }
        }

        this.childList = new ItemContainer(
            this.baseuri,
            {
                "title": "Part Version Children",
                'select': (item, field) => this.childSelect(item, field),
                'actions': {
                    "edit" : {
                        'icon': 'fa fa-pencil-square-o',
                        'callback' : (item) => this.doEdit(item)
                    } 
                },
                "fields": [
                    {
                        "name": "id",
                        "label": "ID",
                    },
                    {
                        "name": "parentId",
                        "label": "Parent ID",
                        "hidden" : true
                    },
                    {
                        "name": "revisionTag",
                        "label": "Revision",
                        "select": true,
                        "html": (item) => { return `<span>${item.partDescription}</span><span class='small' >${item.revisionTag}</span>` }
                    },
                    {
                        "name": "quantity",
                        "label": "Quantity",
                        "editable" : true
                    },
                    {
                        "name": "variantIDs",
                        "label": "Variants",
                        "editable" : false,
                        "select" : true,
                        "value" : (item) => { return this.getVariantsField(item); }
                    },
                    {
                        "name": "statusId",
                        "label": "Status",
                        "choice": this.statusList,
                    },
                    {
                        "name": "isChild",
                        "label": "Is Child",
                        "select" : true,
                        "value": (item) => { return item.quantity > 0 ? 'true' : 'false'; },
                        "html": (item) => { return (item.quantity > 0) ? "<i class='fa fa-check-square-o' >" : "<i class='fa fa-square-o' >"; }
                    },
                    {
                        "name": "reference",
                        "label": "Reference",
                        "hidden": true
                    },
                    {
                        "name": "designators",
                        "label": "Designators",
                        "hidden": true
                    }
                ]
            },
            null
        );

        if (this.revision !== null) {
            await this.childList.loadFiltered({'parentId': this.revision.id});
        }
        else {
            await this.childList.loadItems(true);            
        }        
    }

    hasChildren(categoryId) {
        if (this.categories == null) return false;

        const found = this.categories.find(entry => entry.id == categoryId);
        // console.log("hasChildten: " + found.composite);
        return found.composite;
    }

    async getCategoryName(categoryId) {
        if (this.categories == null) return categoryId;

        const found = this.categories.find(entry => entry.id == categoryId);
        return found.name;
    }

    async getStatusName(statusId) {
        if (this.statusList == null) return statusId;

        const found = this.statusList.find(entry => entry.id == statusId);
        return found.name;
    }

    async getVariantsField(bomItem) {
        var res = null;

        for (var entry of bomItem.variantIDs) {
            if (res == null) {
                res = `${entry}`;
            } 
            else {
                res = `${res}, ${entry}`;
            }
        }

        if (res == null) res = 'any';
        // console.log(JSON.stringify(bomItem));

        return res;
    }

    async doEdit(childItem) {
        return await this.childList.doEdit(childItem);  
    }

    async openPart(partId) {
        if (partId == null) return;
        await this.appControl.loadView('parts', partId);
    }

    async childSelect(childItem, field) {
        if (childItem != null) {
            switch (field) {
                case 'revisionTag' : 
                    this.appControl.loadView('revision', childItem.id);                    
                    return;

                case 'isChild':
                    this.childAddRemove(childItem);
                    return;

                case 'variantIDs':
                    this.editVariants(childItem);
                    return;

                default:
                    console.log("Selected " + JSON.stringify(childItem));
                    return;
            }
        } else {
            this.editVariants(null);
        }
    }

    async childAddRemove(childItem) {
        if ((this.revision === null) || (this.childList === null)) {
            return;
        } 

        if (childItem.quantity > 0) {
            childItem.quantity = 0;
        } else {
            childItem.quantity = 1;
        }

        var api = new Api(this.baseuri);
        await api.put(childItem.id, {
            'parentId' : this.revision.id,
            'quantity' : childItem.quantity
        })

        this.childList.loadItems(true);
    }

    async editVariants(childItem) {
        if (childItem == null) {
            this.selectedChild = null;
            this.variantList = null;
            return;
        }
 
        if (this.variantList == null) {
            this.variantList = new ItemContainer('content/variants', {
                "title" : "Child Item Variants",
                "actions" : {},
                "select" : (item) => this.selectVariantEntry(item),
                'fields': [
                    {
                        'name': 'id',
                        'label': 'ID',
                    },
                    {
                        'name': 'name',
                        'label': 'Name',
                        'select': true,
                        'editable': true,
                    },
                    {
                        'name': 'description',
                        'label': 'Description',
                        'editable': true,
                    },
                    {
                        'name': 'productId',
                        'label': 'Product ID',
                        'hidden': true
                    },
                    {
                        'name': 'revisionTag',
                        'label': 'Root Part',
                    },
                    {
                        'name' : 'assigned',
                        'label' : 'Assigned to child',
                        'html': (variantItem) => { return this.selectedChild.variantIDs.includes(variantItem.id) ? "<i class='fa fa-check-square-o' >" : "<i class='fa fa-square-o' >"; },
                        'select' : true
                    }
                ]
            },
            null);
        }

        this.selectedChild = childItem;
        await this.variantList.loadItems(true);
    }

    async selectVariantEntry(variantItem) {
        if ((this.childList.current == null) || (variantItem == null)) {
            return;
        }

        var variantId = variantItem.id;
        var childItem = this.childList.current;

        if (childItem.variantIDs.includes(variantId)) {
            // remove variant id
            var index = childItem.variantIDs.indexOf(variantId);
            if (index > -1) {
                childItem.variantIDs.splice(index, 1);
            }
        } 
        else {
            // add variant id
            childItem.variantIDs.push(variantId);
        }

        var api = new Api(this.baseuri);
        await api.put(childItem.id, {
            'parentId' : this.revision.id,
            'variantIDs' : childItem.variantIDs
        });
    }
}