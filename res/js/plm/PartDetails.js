class PartDetails  {

    constructor(appControl, context) {
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
        const baseuri = `/doku.php?plm=/api/v1/content/bom`;
        if (this.partVersion !== null) {
            var uri = `${baseuri}/${this.partVersion.id}`;
        } 
        else {
            var uri = `${baseuri}`;
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
                        "value": (item) => { return item.isChild ? 'Yes' : 'No' },
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

                default:
                    console.log("Selected " + JSON.stringify(childItem));
                    return;
            }
        }
    }
}