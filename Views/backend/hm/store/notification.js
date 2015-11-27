Ext.define('Shopware.apps.Hm.store.Notification', {
    extend: 'Ext.data.Store',
    model : 'Shopware.apps.Hm.model.hm.Notification',

    pageSize: 30,

    autoLoad: {
        start: 0,
        limit: 30
    },

    proxy: {
        type: 'ajax',
        url: '{url controller=HmNotifications action=getList}',
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
