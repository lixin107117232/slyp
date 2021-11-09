define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/bonus_examine/index' + location.search,
                    add_url: 'user/bonus_examine/add',
                    edit_url: 'user/bonus_examine/edit',
                    del_url: 'user/bonus_examine/del',
                    multi_url: 'user/bonus_examine/multi',
                    import_url: 'user/bonus_examine/import',
                    table: 'user_withdrawal_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                // search: false,//快速搜索
                columns: [
                    [
                        {checkbox: true},
                        {field: 'user.fictitious_id', title: __('User_fictitious_id')},
                        {field: 'user.nickname', title: __('User_nickname')},
                        {field: 'user.mobile', title: __('User_mobile')},
                        {field: 'score', title: __('Score')},
                        {field: 'before', title: __('Before')},
                        {field: 'after', title: __('After')},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'type', title: __('Type')},
                        {field: 'status', title: __('Status'),searchList:{"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});