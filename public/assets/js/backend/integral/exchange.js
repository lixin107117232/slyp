define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'integral/exchange/index' + location.search,
                    add_url: 'integral/exchange/add',
                    //edit_url: 'integral/exchange/edit',
                    del_url: 'integral/exchange/del',
                    multi_url: 'integral/exchange/multi',
                    import_url: 'integral/exchange/import',
                    table: 'integral_exchange',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                //启用固定列
                fixedColumns: true,
                //固定右侧列数
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4'),"5":__('Status 5'),"6":__('Status 6'),"8":__('Status 8')}, formatter: Table.api.formatter.status},
                        {field: 'status_remark', title: __('Status_remark'), operate: 'LIKE'},
                        {field: 'mode', title: __('Mode'), searchList: {"0":__('Mode 0'),"1":__('Mode 1')}, formatter: Table.api.formatter.normal},
                        {field: 'all_money', title: __('All_money'), operate:'BETWEEN'},
                        {field: 'actual_money', title: __('Actual_money'), operate:'BETWEEN'},
                        {field: 'num', title: __('Num'), operate: false},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate:'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'updatetieme', title: __('Updatetieme')},
                        //{field: 'user.username', title: __('User.username'), operate: 'LIKE'},
                        {field: 'user.fictitious_id', title: __('虚拟ID')},
                        {field: 'user.nickname', title: __('User.nickname'), operate: false},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'user.avatar', title: __('User.avatar'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'specs_name', title: __('规格名称'), operate:false},
                        {field: 'goods_details.name', title: __('商品名称'), operate:false},
                        {field: 'address.name', title: __('购买人联系人'), operate:false},
                        {field: 'address.phone', title: __('购买人联系电话'), operate:false},
                        {field: 'address.address', title: __('购买人地址'), operate:false},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'see',
                                    title: __('详情'),
                                    classname: 'btn btn-xs btn-detail btn-info btn-dialog',
                                    text: __('详情'),   icon: 'fa fa-list',
                                    url: 'integral/exchange/see'}
                            ],
                            events: Table.api.events.operate,formatter: Table.api.formatter.operate}
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
                url: 'integral/exchange/recyclebin' + location.search,
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
                                    url: 'integral/exchange/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'integral/exchange/destroy',
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
        degoods: function () {
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
$(".label-success").click(function () {
    Fast.api.open("integral/exchange/degoods?id="+$("#id").val(), "发货", {

    });
})