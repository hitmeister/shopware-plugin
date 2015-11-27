Ext.define('Shopware.apps.Hm.model.hm.Tree', {
    extend: 'Ext.data.Model',
    idProperty:'id',
    fields: [
        { name: 'id', type: 'int', useNull: true },
        { name: 'title', type: 'string' },
        { name: 'url', type: 'string' }
    ],
    proxy: {
        type: 'ajax',
        timeout: 120000,

        url: '{url controller=HmCategories action=getTree}',

        reader: {
            type: 'json',
            root: 'children'
        }
    }
});
