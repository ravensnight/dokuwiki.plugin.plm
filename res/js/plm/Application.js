class Application {

    constructor() {        
    }

    async loadHtml(viewPart) {
        var api = new Api(`pages/${encodeURIComponent(viewPart)}`);        
        return await api.all(null, false);
    }

    async init() {
        // do nothing. this function can be overwritten        
    }
}
