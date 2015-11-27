//{namespace name=backend/hm/translation}
Ext.define('Shopware.apps.Hm.view.export.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.hm-export-grid',

    initComponent: function () {
        var me = this;

        me.addEvents('export');

        me.viewConfig = {
            getRowClass: function(record) {
                var status = record.get('status'),
                    failed = ['CHECKING_FAILED', 'DOWNLOADING_FAILED', 'IMPORTING_FAILED', 'PREPARING_FAILED', 'PREPROCESSING_FAILED'];
                if (failed.indexOf(status) >= 0) {
                    return 'grid-row-export-status-failed';
                }
                return '';
            }
        };

        me.store = Ext.create('Shopware.apps.Hm.store.Export');
        me.columns = me.getCreateColumns();
        me.dockedItems = [
            me.getCreateToolbar(),
            me.getCreatePaging()
        ];

        me.callParent(arguments);
    },

    getCreateColumns: function() {
        var me = this;

        return [
            {
                text: '{s name=view/export/grid/column/id_title}Id{/s}',
                dataIndex: 'id_import_file',
                menuDisabled: true,
                sortable: false,
                width: 70
            },
            {
                text: '{s name=view/export/grid/column/status_title}Status{/s}',
                dataIndex: 'status',
                menuDisabled: true,
                sortable: false,
                width: 120
            },
            {
                text: '{s name=view/export/grid/column/total_lines_title}Lines{/s}',
                dataIndex: 'total_lines',
                menuDisabled: true,
                sortable: false,
                width: 40
            },
            {
                text: '{s name=view/export/grid/column/error_count_title}Errors{/s}',
                dataIndex: 'error_count',
                menuDisabled: true,
                sortable: false,
                width: 40
            },
            {
                xtype: 'datecolumn',
                text: '{s name=view/export/grid/column/ts_created_date_title}Created date{/s}',
                format: 'Y-m-d H:i:s',
                dataIndex: 'ts_created',
                menuDisabled: true,
                sortable: false,
                width: 120
            },
            {
                xtype: 'datecolumn',
                text: '{s name=view/export/grid/column/ts_completed_title}Completed date{/s}',
                format: 'Y-m-d H:i:s',
                dataIndex: 'ts_completed',
                menuDisabled: true,
                sortable: false,
                width: 120
            },
            {
                text: '{s name=view/export/grid/column/note_title}Note{/s}',
                dataIndex: 'note',
                menuDisabled: true,
                sortable: false,
                flex: 1
            }
        ];
    },

    getCreatePaging: function() {
        var me = this;

        return Ext.create('Ext.toolbar.Paging', {
            store: me.store,
            dock: 'bottom',
            displayInfo: true
        })
    },

    getCreateToolbar: function() {
        var me = this;

        return {
            xtype: 'toolbar',
            ui: 'shopware-ui',
            items: [
                {
                    xtype: 'button',
                    text: '{s name=view/export/grid/toolbar/button_export}Export{/s}',
                    iconCls: 'sprite-plus-circle-frame',
                    handler: function() {
                        me.fireEvent('export')
                    }
                },
            ]
        };
    }
});
