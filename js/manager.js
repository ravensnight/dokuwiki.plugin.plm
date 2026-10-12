function plmManager() {
    return {
        currentView: 'parts',
        partsHtml: '',
        versionsHtml: '',
        bomHtml: '',
        productsHtml: '',
        companiesHtml: '',
        masterDataHtml: '',

        init() {
            this.fetchView();
        },

        setView(view) {
            this.currentView = view;
            this.fetchView();
        },

        async fetchView() {
            try {
                const response = await fetch(`doku.php?plmapi=v1/manager&view=${this.currentView}`);
                const data = await response.json();
                
                if (this.currentView === 'parts') this.partsHtml = data.html;
                if (this.currentView === 'versions') this.versionsHtml = data.html;
                if (this.currentView === 'bom') this.bomHtml = data.html;
                if (this.currentView === 'products') this.productsHtml = data.html;
                if (this.currentView === 'companies') this.companiesHtml = data.html;
                if (this.currentView === 'master_data') this.masterDataHtml = data.html;
            } catch (e) {
                console.error("Error fetching view", e);
            }
        },

        masterDataCRUD() {
            return {
                showModal: false,
                modalTitle: '',
                formData: {
                    name: '',
                    description: '',
                    sort_order: 0,
                    active: true
                },
                currentEntity: null,

                openAddModal(type) {
                    this.modalTitle = `Add ${type}`;
                    this.currentEntity = null;
                    this.formData = {
                        name: '',
                        description: '',
                        sort_order: 0,
                        active: true
                    };
                    this.showModal = true;
                },

                openEditModal(entity) {
                    this.modalTitle = `Edit ${entity.type}`;
                    this.currentEntity = entity;
                    this.formData = { ...entity };
                    this.showModal = true;
                },

                submitForm() {
                    const method = this.currentEntity ? 'PUT' : 'POST';
                    const url = this.currentEntity 
                        ? `doku.php?plmapi=v1/master_data&id=${this.currentEntity.id}`
                        : `doku.php?plmapi=v1/master_data`;
                    
                    fetch(url, {
                        method: method,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.formData)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            this.showModal = false;
                            this.fetchView();
                        } else {
                            alert('Error saving data');
                        }
                    })
                    .catch(err => console.error(err));
                },

                confirmDelete(entity) {
                    if (confirm(`Are you sure you want to delete ${entity.name}?`)) {
                        fetch(`doku.php?plmapi=v1/master_data&id=${entity.id}`, {
                            method: 'DELETE'
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                this.fetchView();
                            }
                        })
                        .catch(err => console.error(err));
                    }
                }
            };
        }
    }
}