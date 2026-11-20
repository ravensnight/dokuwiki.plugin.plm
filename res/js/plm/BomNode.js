class BomNode extends PageFragment {

    constructor(open, control) {
        super('pages/bomnode');        
        this.revision = null;
        this.subnodes = null;
        this.open = open;
        this.control = control;
        this.isComposite = false;
    }

    async acquireSubNodes() {
        if (this.revision == null || !this.isComposite) {
            this.subnodes = null;
            return;
        }

        var bomApi = new Api('content/bom');
        var children = await bomApi.get(this.revision.id);
        
        var nodes = null;
        if (children != null) {
            nodes = [];
            for (var c of children) {
                var n = new BomNode(false, this.control);

                // console.log("Push node to parent: " + this.revision.id + " - " + JSON.stringify(n));
                nodes.push(n);
                n.assign(c);
            }
        } 

        this.subnodes = nodes;
    }

    async load(revisionId) {
        if (revisionId == null) {
            this.revision = null;
            return;
        }

        var revApi = new Api('content/versions');
        var rev = await revApi.get(revisionId); 

        await this.assign(rev);
    }

    async assign(revision) {
        this.revision = revision;
        this.isComposite = await this.control.isComposite(this.revision.part_categoryId);

        if (this.isComposite && this.open) {
            await this.acquireSubNodes();
        }
    }

    async openClose() {
        if (!this.open) {
            await this.acquireSubNodes();
            this.open = true;
        } else {
            this.open = false;
            this.subnodes = null;
        }
    }
}
