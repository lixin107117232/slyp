define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'hospital/goods/index' + location.search,
                    add_url: 'hospital/goods/add',
                    edit_url: 'hospital/goods/edit',
                    del_url: 'hospital/goods/del',
                    multi_url: 'hospital/goods/multi',
                    import_url: 'hospital/goods/import',
                    table: 'hospital_goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'goodsdata.name', title: __('GoodsName')},
                        {field: 'typedata.name', title: __('TypeName')},
                        {field: 'shopdata.name', title: __('ShopName')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'num', title: __('Num')},
                        {field: 'specs_data', title: __('Specs_data'), searchList: {"0":__('Specs_data 0'),"1":__('Specs_data 1')}, formatter: Table.api.formatter.normal},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
                url: 'hospital/goods/recyclebin' + location.search,
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
                                    url: 'hospital/goods/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'hospital/goods/destroy',
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
$("input[name='row[specs_data]']").click(function () {
    if($(this).val()==1){
        $(".stock").hide();
        $(".sales").hide();
        $(".stock_view").show();
    }else{
        $(".stock").show();
        $(".sales").show();
        $(".stock_view").hide();
    }

})
$(".edit").click(function () {
    var obj = {};
    var i = 0;
    var first = '';
    var tmp = {};
    $('#lv_table input').each(function (index, e) {
        var name = $(e).attr('name');
        var value = $(e).val();
        symbol = name.split('|')[0];
        key = name.split('|')[1];
        if (index == 0) {
            first = symbol;
            tmp = {symbol: symbol, item_id: 1};
        } else if (first != symbol) {
            first = symbol;
            i++;
            tmp = {symbol: symbol, item_id: 1};
        }
        tmp[key] = value;
        obj[i] = tmp;

    });
    $(".obj").val(  JSON.stringify( obj ));
    var form=$("#edit-form");
    var params = {};
    var multipleList = $("[name$='[]']", form);
    if (multipleList.size() > 0) {
        var postFields = form.serializeArray().map(function (obj) {
            return $(obj).prop("name");
        });
        $.each(multipleList, function (i, j) {
            if (postFields.indexOf($(this).prop("name")) < 0) {
                params[$(this).prop("name")] = '';
            }
        });

    }
    url = form.attr("action");
    url = url ? url : location.href;
    Fast.api.ajax({
        url:url,
        type:'POST',
        data:form.serialize() + (Object.keys(params).length > 0 ? '&' + $.param(params) : '')+"&obj="+JSON.stringify( obj ),
    }, function(data, ret){
        // console.log("ok");
        // console.log(data);
        //成功的回调
        layer.msg("商品修改成功",{
            time: 2000 //2秒关闭（默认是3秒）
        },function () {
            window.location.reload();
            return false;
        })
    }, function(data, ret){
        // console.log("no");
        // console.log(data);
        //失败的回调
        window.location.reload();
        return false;
    });
})

$(".add").click(function () {
    var obj = {};
    var i = 0;
    var first = '';
    var tmp = {};
    $('#lv_table input').each(function (index, e) {
        var name = $(e).attr('name');
        var value = $(e).val();
        symbol = name.split('|')[0];
        key = name.split('|')[1];
        if (index == 0) {
            first = symbol;
            tmp = {symbol: symbol, item_id: 1};
        } else if (first != symbol) {
            first = symbol;
            i++;
            tmp = {symbol: symbol, item_id: 1};
        }
        tmp[key] = value;
        obj[i] = tmp;

    });
    var form=$("#add-form");
    var params = {};
    var multipleList = $("[name$='[]']", form);
    if (multipleList.size() > 0) {
        var postFields = form.serializeArray().map(function (obj) {
            return $(obj).prop("name");
        });
        $.each(multipleList, function (i, j) {
            if (postFields.indexOf($(this).prop("name")) < 0) {
                params[$(this).prop("name")] = '';
            }
        });

    }
    url = form.attr("action");
    url = url ? url : location.href;
    Fast.api.ajax({
        url:url,
        type:'POST',
        data:form.serialize() + (Object.keys(params).length > 0 ? '&' + $.param(params) : '')+"&obj="+JSON.stringify( obj ),
    }, function(data, ret){
        // console.log("ok");
        // console.log(data);
        // console.log(ret);
        //成功的回调
        layer.msg("商品添加成功",{
            time: 2000 //2秒关闭（默认是3秒）
        },function () {
            window.location.reload();
            return false;
        })

    }, function(data, ret){
        console.log("no");
        console.log(data);
        console.log(ret);
        //失败的回调
        // window.location.reload();
        // return false;
    });
})

