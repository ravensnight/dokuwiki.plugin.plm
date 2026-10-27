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
    async loadView(view, context) {

        this.loading = true;
        try {
            this.error = '';
            this.view = view;

            // create the controller
            this.control = await this.getViewControl(view, context);            

            // Wait for the HTML to load
            this.html = await this.loadHtml(view);
            
            // initialize the control.
            if ('setup' in this.control && typeof this.control.setup === 'function') {
                await this.control.setup();
            } 

        } catch (error) {
            this.error = `Failed to fetch view: ${error.message}`;
            this.view = null;

        } finally {
            this.loading = false;
        }
    }

    async getViewControl(view, context) {
        var res = null;
        switch (view) {
            case 'parts':
                res = new Parts();
                break;

            case 'details':
                res = new PartDetails(context);
                break;

            case 'products':
                res = new Products(this);
                break;

            case 'masterdata':
                res = new Masterdata();
                break;

            case 'companies':
                res = new Companies();
                break;

            case 'bom' :
                res = new BOMControl(context);
                break;

            default:
                throw new Error("No controller define for view: " + view);

        }

        return res;
    }
}