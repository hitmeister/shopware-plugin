Ext.define('Shopware.apps.Hm.model.hm.Export', {
    extend: 'Ext.data.Model',
    idProperty:'id_import_file',
    fields: [
        { name: 'id_import_file', type: 'int', useNull: true },
        { name: 'uri', type: 'string' },
        { name: 'status', type: 'string' },
        { name: 'type', type: 'string' },
        { name: 'note', type: 'string' },
        { name: 'error_count', type: 'int', useNull: false },
        { name: 'total_lines', type: 'int', useNull: false },
        { name: 'current_line', type: 'int', useNull: false },
        { name: 'ts_created', type: 'date', useNull: true },
        { name: 'ts_updated', type: 'date', useNull: true },
        { name: 'ts_last_row_updated', type: 'date', useNull: true },
        { name: 'ts_completed', type: 'date', useNull: true }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller=HmExports action=getList}'
        },
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
