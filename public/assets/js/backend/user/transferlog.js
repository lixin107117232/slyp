define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/transferlog/index' + location.search,
                    add_url: 'user/transferlog/add',
                    edit_url: 'user/transferlog/edit',
                    del_url: 'user/transferlog/del',
                    multi_url: 'user/transferlog/multi',
                    import_url: 'user/transferlog/import',
                    table: 'user_withdrawal_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        // {field: 'user_id', title: __('User_id')},
                        {field: 'user.fictitious_id', title: __('User_fictitious_id')},
                        {field: 'user.nickname', title: __('User_nickname')},
                        {field: 'user.mobile', title: __('User_mobile')},
                        {field: 'score', title: __('Score'), operate: 'LIKE'},
                        {field: 'before', title: __('Before')},
                        {field: 'after', title: __('After')},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},

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