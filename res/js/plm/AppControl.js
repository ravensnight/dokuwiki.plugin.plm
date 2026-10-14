class AppControl {

    constructor() {
        this.html = '';
        this.view = null;
        this.control = null;
        this.data = null;
        this.loading = false;
        this.error = '';        
    }

    /** Load the default view */
    async init() {
        this.loadView('parts');
    }

    /** Load  */
    async loadView(view) {

        this.loading = true;
        try {
            this.error = '';
            this.view = null;

            // create the controller
            this.control = await this.getViewControl(view);            

            // Wait for the HTML to load
            this.html = await this.loadHtml(view);

            // Wait until Alpine has updated the DOM
            // await new Promise(resolve => this.$nextTick(resolve));

            // Initialize the newly inserted HTML
            // Alpine.initTree(this.$refs.viewContainer);

        } catch (error) {
            this.error = `Failed to fetch view: ${error.message}`;
            this.view = null;

        } finally {
            this.loading = false;
        }
    }

    async loadHtml(view) {

        const response = await fetch(
            `/doku.php?plm=/api/v1/pages/${encodeURIComponent(view)}`
        );

        if (!response.ok) {
            throw new Exception(`Failed to load view: ${response.status} - ${response.text()}`);
        } 

        return response.text();
    }

    async getViewControl(view) {
        var res = null;
        switch (view) {
            case 'parts':
                res = new Parts();
                await res.init();
                return res;

            case 'versions':
                return new Versions();

            case 'products':
                return new Products();

            case 'masterdata':
                return new Masterdata();

            case 'companies':
                return new Companies();

            default:
                throw new Error("No controller define for view: " + view);

        }
    }
}