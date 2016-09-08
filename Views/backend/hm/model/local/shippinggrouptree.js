Ext.define('Shopware.apps.Hm.model.local.Shippinggrouptree', {
    extend: 'Shopware.apps.Hm.model.local.Tree',
    idProperty:'id',
    fields: [
        { name: 'id', type: 'int', useNull: true },
        { name: 'description', type: 'string' },
        { name: 'active', type: 'bool' }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller=HmCategories action=getLocalList}'
        },
        reader: {
            type: 'json',
            root: 'data'
        }
    },

    unMap: function() {
    }
});
