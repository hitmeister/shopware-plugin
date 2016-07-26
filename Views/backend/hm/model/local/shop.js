Ext.define('Shopware.apps.Hm.model.local.Shop', {
    extend: 'Ext.data.Model',
    idProperty:'id',
    fields: [
        { name: 'id', type: 'int', useNull: true },
        { name: 'name', type: 'string' },
    ],
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller=Hm action=getActiveShops}'
        },
        reader: {
            type: 'json',
            root: 'data'
        }
    }
});
