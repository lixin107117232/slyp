define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'hospital/order/index' + location.search,
                    add_url: 'hospital/order/add',
                    edit_url: 'hospital/order/edit',
                    del_url: 'hospital/order/del',
                    multi_url: 'hospital/order/multi',
                    import_url: 'hospital/order/import',
                    table: 'hospital_order',
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
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'userdata.username', title: __('User_name')},
                        {field: 'hospital_goods_name', title: __('Hospital_goods_name'), operate: 'LIKE'},
                        {field: 'sku_str', title: __('Sku_str'), operate: 'LIKE'},
                        {field: 'all_money', title: __('All_money'), operate:'BETWEEN'},
                        {field: 'actual_money', title: __('Actual_money'), operate:'BETWEEN'},
                        {field: 'num', title: __('Num')},
                        {field: 'status', title: __('Status')},
                        {field: 'pay_status', title: __('Pay_status')},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate',
                            title: __('Operate'),
                            table: table,
                            buttons: [
                                {
                                    name: 'write_off',
                                    title: "核销",
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-circle-o',
                                    confirm: "是否对该用户进行核销？",
                                    url: "hospital/order/write_off?",
                                    disable:function (row) {
                                        if(row['updatetime'] == null){
                                            return false;
                                        }else{
                                            return true;
                                        }
                                    },
                                    success: function (data, ret) {
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        // console.log("1111");
                                        // console.log(data, ret);
                                        layer.alert(ret.msg,{icon:1});
                                        //如果需要阻止成功提示，则必须使用return false;
                                        table.bootstrapTable('refresh', {});
                                        return false;
                                    },
                                    error: function (data, ret) {
                                        // console.log("2222");
                                        // console.log(data, ret);
                                        layer.alert(ret.msg,{icon:7});
                                        table.bootstrapTable('refresh', {});
                                        return false;
                                    }
                                }
                            ],
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
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
                url: 'hospital/order/recyclebin' + location.search,
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
                                    url: 'hospital/order/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'hospital/order/destroy',
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
