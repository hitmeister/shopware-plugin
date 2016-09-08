Ext.define('Shopware.apps.Hm.model.hm.Shippinggroup', {
    extend: 'Ext.data.Model',
    idProperty:'name',
    fields: [
        { name: 'name', type: 'string' }
    ],
    proxy: {
        type: 'ajax',
        timeout: 120000,

        url: '{url controller=Hm action=getShippingGroups}',

        reader: {
            type: 'json',
            root: 'data'
        }
    }
});
