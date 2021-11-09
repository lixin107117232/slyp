define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/teamdetails/index' + location.search,
                    // add_url: 'user/teamdetails/add',
                    // edit_url: 'user/teamdetails/edit',
                    // del_url: 'user/teamdetails/del',
                    multi_url: 'user/teamdetails/multi',
                    import_url: 'user/teamdetails/import',
                    table: 'user',
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
                        {field: 'fictitious_id', title: __('Fictitious_id')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'level', title: __('Level'), searchList: {"1":__('消费者'),"2":__('代理商'),"3":__('总代理')}, formatter: Table.api.formatter.status},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'user_sum_money', title: __('User_sum_money'), operate: 'LIKE'},
                        {field: 'user_lastmonth_money', title: __('User_lastmonth_money'), operate: 'LIKE'},
                        {field: 'team_sum_money', title: __('Team_sum_money'), operate: 'LIKE'},
                        {field: 'team_lastmonth_money', title: __('Team_lastmonth_money'), operate: 'LIKE'},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                        {
                            field: 'operate',
                            width: "150px",
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'addtabs',
                                    title: __('团队详情'),
                                    classname: 'btn btn-xs btn-warning btn-addtabs',
                                    icon: 'fa fa-hand-pointer-o',
                                    url: 'user/Teamdetails/detail'
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        },
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