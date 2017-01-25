Ext.define('Shopware.apps.Hm.store.Export', {
    extend: 'Ext.data.Store',
    model : 'Shopware.apps.Hm.model.hm.Export',

    pageSize: 30,

    autoLoad: {
        start: 0,
        limit: 30
    },

    proxy: {
        type: 'ajax',
        url: '{url controller=HmExports action=getList}',
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});