define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'seckill/order/index' + location.search,
                    add_url: 'seckill/order/add',
                    //edit_url: 'seckill/order/edit',
                    del_url: 'seckill/order/del',
                    multi_url: 'seckill/order/multi',
                    import_url: 'seckill/order/import',
                    table: 'seckill_order',
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
                        // {field: 'id', title: __('Id')},
                        //{field: 'seckill_goods_id', title: __('Seckill_goods_id')},
                        {field: 'user.fictitious_id', title: __('虚拟ID')},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate:'operate'},
                        {field: 'user.nickname', title: __('昵称'), operate: 'operate'},
                        {field: 'user.mobile', title: __('手机号'), operate: 'operate'},
                        {field: 'user.avatar', title: __('头像'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4'),"5":__('Status 5'),"6":__('Status 6'),"7":__('Status 7'),"8":__('Status 8')}, formatter: Table.api.formatter.status},
                        {field: 'status_remark', title: __('Status_remark'), operate: 'LIKE'},
                        {field: 'ischoice', title: __('Ischoice'), searchList: {"0":__('Ischoice 0'),"1":__('Ischoice 1')}, formatter: Table.api.formatter.normal},
                        {field: 'pay_data', title: __('Pay_data'), searchList: {"1":__('Pay_data 1'),"2":__('Pay_data 2'),"3":__('Pay_data 3')}, formatter: Table.api.formatter.normal},
                        {field: 'all_money', title: __('All_money'), operate:'BETWEEN'},
                        //{field: 'actual_money', title: __('Actual_money'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'companytime', title: __('Companytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'company_id', title: __('Company_id')},
                        {field: 'company_name', title: __('Company_name'), operate: 'LIKE'},
                        {field: 'company_code', title: __('Company_code'), operate: 'LIKE'},
                        {field: 'numbers', title: __('Numbers'), operate: 'LIKE'},
                        {field: 'specs_name', title: __('商品规格'), operate:false},
                        {field: 'goods_details.name', title: __('商品名称'), operate:false},
                        {field: 'address.name', title: __('购买人联系人'), operate:false},
                        {field: 'address.phone', title: __('购买人联系电话'), operate:false},
                        {field: 'address.address', title: __('购买人地址'), operate:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'see',
                                    title: __('详情'),
                                    classname: 'btn btn-xs btn-detail btn-info  btn-dialog',
                                    text: __('详情'),   icon: 'fa fa-list',
                                    url: 'seckill/order/see'}
                            ],
                            formatter:function (value, row, index){
                                var that = $.extend({}, this);
                                var table = $(that.table).clone(true);
                               /* if(row["ischoice"]!=0)
                                {
                                    $(table).data("operate-see", null);
                                    that.table = table;
                                }*/
                                return Table.api.formatter.operate.call(that, value, row, index);
                            },}
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
                url: 'seckill/order/recyclebin' + location.search,
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
                                    url: 'seckill/order/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'seckill/order/destroy',
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
        degoods:function(){
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
    Fast.api.open("seckill/order/degoods?id="+$("#id").val(), "发货", {
    });
})