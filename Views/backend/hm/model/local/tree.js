Ext.define('Shopware.apps.Hm.model.local.Tree', {
    extend: 'Ext.data.Model',
    idProperty:'id',
    fields: [
        { name: 'id', type: 'int', useNull: true },
        { name: 'description', type: 'string' },
        { name: 'active', type: 'bool' },
        { name: 'hm_category_id', type: 'int', useNull: true },
        { name: 'hm_category_title', type: 'string' }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller=HmCategories action=getLocalList}',
            update: '{url controller=HmCategories action=updateMap}'
        },
        reader: {
            type: 'json',
            root: 'data'
        }
    },

    unMap: function() {
        this.set('hm_category_id', null);
        this.set('hm_category_title', null);
    }
});
