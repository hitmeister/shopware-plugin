Ext.define('Shopware.apps.Hm.model.local.Stock', {
    extend: 'Ext.data.Model',
    idProperty:'id',
    fields: [
        { name: 'id', type: 'int', useNull: true },
        { name: 'ordernumber', type: 'string' },
        { name: 'ean', type: 'string' },
        { name: 'instock', type: 'int', useNull: true },
        { name: 'name', type: 'string' },
        { name: 'hm_unit_id', type: 'string', useNull: true },
        { name: 'hm_last_access_date', type: 'date', useNull: true },
        { name: 'hm_status', type: 'string', useNull: false }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller=HmArticles action=getList}'
        },
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
