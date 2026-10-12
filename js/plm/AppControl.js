class AppControl {
    constructor(api) {
        this.api = api;
        this.html = '';
        this.data = null;
        this.loading = false;
        this.error = '';        
    }

    async init() {
        this.loadView('parts');
    }

    async loadView(view) {
        this.loading = true;
        this.error = '';

        try {
            // First request: retrieve the rendered page.
            this.html = await this.api.loadView(view);

            // Second request: retrieve the data required by the page.
            this.data = await this.api.loadViewData(view);

        } catch (error) {
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    }
}