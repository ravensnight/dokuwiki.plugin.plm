class PartDetails  {

    constructor(appControl, context) {
        this.baseuri = `/doku.php?plm=/api/v1/content/bom`;
        this.appControl = appControl;
        this.context = context;
        this.statusList = null;
        this.partVersion = null;
    }

    async setup() {
        console.log('VersionSelect');

        if (this.statusList == null) {
            const response = await fetch('/doku.php?plm=/api/v1/content/status');
            if (!response.ok) {
                throw new Error(`Failed to load statús : ${response.status} - ${response.text}`);
            }

            this.statusList = await response.json();
        }

        // Assign Context to partVersion... now statusList is loaded.
        this.partVersion = this.context;

        // Load child table
        if (this.partVersion !== null) {
            var uri = `${this.baseuri}/${this.partVersion.id}`;
        } 
        else {
            var uri = `${this.baseuri}`;
        }

        this.childList = new ItemContainer(
            uri,
            {
                "title": "Part Version Children",
                "features": {
                    'select': { 'callback': (item, field) => this.childSelect(item, field) }
                },
                "fields": [
                    {
                        "name": "id",
                        "label": "ID",
                    },
                    {
                        "name": "partIPN",
                        "label": "IPN",
                    },
                    {
                        "name": "name",
                        "label": "Name",
                        "select" : true
                    },
                    {
                        "name": "major",
                        "label": "Major Version",
                    },
                    {
                        "name": "revision",
                        "label": "Revision",
                    },
                    {
                        "name": "statusId",
                        "label": "Status",
                        "choice": this.statusList,
                    },
                    {
                        "name": "isChild",
                        "label": "Is Child",
                        "value": (item) => { console.log(JSON.stringify(item)); return (item.isChild === 1) ? 'Yes' : 'No' },
                        "select" : true
                    }
                ]
            },
            null
        );

        await this.childList.loadItems(true);        
    }

    async getStatusName(statusId) {
        if (this.statusList === null) return statusId;

        const found = this.statusList.find(entry => entry.id === statusId);
        return found.name;
    }

    async childSelect(childItem, field) {
        if (childItem != null) {
            switch (field) {
                case 'name' : 
                    this.appControl.loadView('details', childItem);                    
                    return;

                case 'isChild':
                    this.childAddRemove(childItem.id, !childItem.isChild);
                    return;

                default:
                    console.log("Selected " + JSON.stringify(childItem));
                    return;
            }
        }
    }

    async childAddRemove(childId, newState) {
        if ((this.partVersion === null) || (this.childList === null)) {
            return;
        } 

        var body = {
            "command" : newState ? "add" : "remove",
            "childId" : childId
        };

        var uri = `${this.baseuri}/${this.partVersion.id}`;
        var response = await fetch(uri, {
            method: "PUT",
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },

            body: JSON.stringify(body)
        });

        if (!response.ok) {
            throw new Error(`Create/Update Request failed: ${response.status} - ${await response.text()}`);
        }

        this.childList.loadItems(true);
    }
}