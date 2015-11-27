Ext.define('Shopware.apps.Hm.store.Stock', {
    extend: 'Ext.data.Store',
    model : 'Shopware.apps.Hm.model.local.Stock',

    pageSize: 100,

    autoLoad: {
        start: 0,
        limit: 100
    },

    sorters: [
        {
            property: 'ordernumber',
            direction: 'ASC'
        }
    ],

    remoteSort: true,
    remoteFilter: true,

    proxy: {
        type: 'ajax',

        url: '{url controller=HmArticles action=getList}',

        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
