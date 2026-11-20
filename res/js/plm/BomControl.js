class BomControl extends Application {

    constructor(config) {
        super();

        this.config = config;
        this.loading = false;
        this.error = null;
        this.title = null;
        this.variant = null;
        this.partNode = null;
        this.nodeDetails = null;
        this.nodeApi = new Api('content/versions');
        this.categories = null;
    }

    /** initialize */
    async init() {      
        this.loading = true;

        if (this.categories == null) {
            var cat = new Api('content/categories');
            this.categories = await cat.all();
        }

        var name = this.config['variant'];
        this.title = name != null ? name : "No variant found";

        var api = new Api('content/variants');
        this.variant = await api.all({'variantName' : name});

        if (this.variant != null) {

            this.partNode = new BomNode(true, this);
            await this.partNode.load(this.variant.revisionId);
        }

        this.loading = false;
    }

    async setNodeDetails(node) {
        // this.nodeDetails = await this.nodeApi.get(nodeId);
        this.nodeDetails = node;
    }

    isComposite(categoryId) {
        if (this.categories == null) return false;

        const found = this.categories.find(entry => entry.id == categoryId);
        // console.log("hasChildten: " + found.composite);
        return found.composite;
    }
}
