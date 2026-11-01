class Page {

    async loadHtml(viewPart) {
        var api = new Api(`pages/${encodeURIComponent(viewPart)}`);        
        return await api.all(null, false);
    }

}