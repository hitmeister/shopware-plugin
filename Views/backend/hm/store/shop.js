Ext.define('Shopware.apps.Hm.store.Shop', {
    extend: 'Ext.data.Store',
    model : 'Shopware.apps.Hm.model.local.Shop',

    fields: [ 'id', 'name', 'category_id' ],

    proxy: {
        type: 'ajax',
        url: '{url controller=Hm action=getActiveShops}',
        reader: {
            type: 'json',
            root: 'data'
        }
    }
});