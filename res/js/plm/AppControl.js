class AppControl extends Page {

    constructor() {
        super()

        this.html = '';
        this.view = null;
        this.control = null;
        this.data = null;
        this.loading = false;
        this.error = '';        
    }

    /** Load the default view */
    async init() {

        console.log("AppControl::init()");
        this.loadView('products');
    }

    /** Load  */
    async loadView(view) {

        this.loading = true;
        try {
            this.error = '';
            this.view = view;

            // create the controller

            console.log("Load control: " + view);
            this.control = await this.getViewControl(view);            

            // Wait for the HTML to load
            console.log("Load html: " + view);
            this.html = await this.loadHtml(view);
            
            // initialize the control.
            if ('setup' in this.control && typeof this.control.setup === 'function') {
                console.log("Call setup: " + view);
                await this.control.setup();
            } 

        } catch (error) {
            this.error = `Failed to fetch view: ${error.message}`;
            this.view = null;

        } finally {
            this.loading = false;
        }
    }

    async getViewControl(view) {
        var res = null;
        switch (view) {
            case 'parts':
                res = new Parts();
                break;

            case 'versions':
                res = new Versions();
                break;

            case 'products':
                res = new Products();
                break;

            case 'masterdata':
                res = new Masterdata();
                break;

            case 'companies':
                res = new Companies();
                break;

            default:
                throw new Error("No controller define for view: " + view);

        }

        return res;
    }
}