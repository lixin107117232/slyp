define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/moneyexamine/index' + location.search,
                    add_url: 'user/moneyexamine/add',
                    edit_url: 'user/moneyexamine/edit',
                    del_url: 'user/moneyexamine/del',
                    multi_url: 'user/moneyexamine/multi',
                    import_url: 'user/moneyexamine/import',
                    table: 'user_money_examine',
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
                        // {field: 'id', title: __('Id')},
                        {field: 'user.fictitious_id', title: __('User_fictitious_id')},
                        {field: 'user.nickname', title: __('User_nickname')},
                        {field: 'user.mobile', title: __('User_mobile')},
                        {field: 'score', title: __('Score')},
                        {field: 'pay_method', title: __('Pay_method')},
                        {field: 'method_image', title: __('Method_data'),operate: false, events: Table.api.events.image,
                            formatter:function (value, row, index) {
                                value = value == null || value.length === 0 ? '' : value.toString();
                                value = value ? value : '/assets/img/blank.gif';
                                var classname = typeof this.classname !== 'undefined' ? this.classname : 'img-sm img-center';
                                if(/^\d+$/.test(value)){
                                    // 如果value为纯数字，则为银行卡号
                                    return value;
                                }
                                return '<a href="javascript:"><img class="' + classname + '" src="' + value + '" /></a>';
                            }
                        },

                        {field: 'zhenshi_name', title: __('Zhenshi_name')},
                        {field: 'zhenshi_phone', title: __('Zhenshi_phone')},
                        {field: 'back_name', title: __('Back_name')},
                        {field: 'back_branch', title: __('Back_branch')},
                        {field: 'back_user_name', title: __('Back_user_name')},
                        {field: 'back_no', title: __('Back_no')},

                        {field: 'status', title: __('Status'),searchList:{"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [{
                                name: 'detail',
                                text: __('Detail'),
                                icon: 'fa fa-list',
                                classname: 'btn btn-info btn-xs btn-detail btn-dialog',
                                url: 'user/Moneyexamine/select_details'
                            }]
                            , events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'user/moneyexamine/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '130px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'user/moneyexamine/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'user/moneyexamine/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
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