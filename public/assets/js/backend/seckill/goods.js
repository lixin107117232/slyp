define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'seckill/goods/index' + location.search,
                    add_url: 'seckill/goods/add',
                    edit_url: 'seckill/goods/edit',
                    del_url: 'seckill/goods/del',
                    multi_url: 'seckill/goods/multi',
                    import_url: 'seckill/goods/import',
                    dragsort_url: '',
                    table: 'seckill_goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                //启用固定列
                fixedColumns: true,
                //固定右侧列数
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        //{field: 'starttime', title: __('Starttime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false,formatter: Table.api.formatter.datetime},
                        {field: 'price', title: __('Price')},
                        {field: 'one_specs_data', title: __('One_specs_data')},
                        {field: 'two_specs_data', title: __('Two_specs_data')},
                        {field: 'specs_data', title: __('Specs_data'), searchList: {"0":__('Specs_data 0'),"1":__('Specs_data 1')}, formatter: Table.api.formatter.normal},
                        {field: 'stock', title: __('Stock'), operate: false},
                        //{field: 'two_stock', title: __('第二场库存'), operate: false},
                        {field: 'sales', title: __('Sales')},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'allgoods.name', title: __('Goods.name'), operate: 'LIKE'},
                        {field: 'allgoods.cover_image', title: __('Goods.cover_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
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
                url: 'seckill/goods/recyclebin' + location.search,
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
                                    url: 'seckill/goods/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'seckill/goods/destroy',
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
            /*$('#c-starttime').on('dp.change', function (e) {
                var form=$("#edit-form");
                url = form.attr("action");
                url = url ? url : location.href;
                console.log(url);return;
                console.log($(this).val());
            });*/
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
        $(".stock_view").show();
    }else{
        $(".stock").show();
        $(".stock_view").hide();
    }

})
function optionArray()
{
    var option_stock = new Array();
    $('.option_stock').each(function (index,item) {
        option_stock.push($(item).val());
    });

    var option_id = new Array();
    $('.option_id').each(function (index,item) {
        option_id.push($(item).val());
    });

    var option_ids = new Array();
    $('.option_ids').each(function (index,item) {
        option_ids.push($(item).val());
    });

    var option_title = new Array();
    $('.option_title').each(function (index,item) {
        option_title.push($(item).val());
    });
    var options = {
        option_stock : option_stock,
        option_id : option_id,
        option_ids : option_ids,
        option_title : option_title,
    };
    return options;
}

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
    var optionArray1 = optionArray();
    url = form.attr("action");
    url = url ? url : location.href;
    Fast.api.ajax({
        url:url,
        type:'POST',
        data:form.serialize() + (Object.keys(params).length > 0 ? '&' + $.param(params) : '')+"&obj="+JSON.stringify( obj )+"&specs_datas="+JSON.stringify( optionArray1 ),
    }, function(data, ret){
        //成功的回调
        layer.msg("操作成功",{
            time: 500 //2秒关闭（默认是3秒）
        },function () {
            Fast.api.close(); // 关闭弹窗
            parent.$(".btn-refresh").trigger("click");        // parent.location.reload();
            return false;
        });
        return true;
    }, function(data, ret){
        //失败的回调
        Fast.api.close(); // 关闭弹窗
        parent.location.reload();
        return false;
    });
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
    var optionArray1 = optionArray();
    url = form.attr("action");
    url = url ? url : location.href;
    Fast.api.ajax({
        url:url,
        type:'POST',
        data:form.serialize() + (Object.keys(params).length > 0 ? '&' + $.param(params) : '')+"&obj="+JSON.stringify( obj )+"&specs_datas="+JSON.stringify( optionArray1 ),
    }, function(data, ret){
        //成功的回调
        layer.msg("操作成功",{
            time: 500 //2秒关闭（默认是3秒）
        },function () {
            Fast.api.close(); // 关闭弹窗
            parent.$(".btn-refresh").trigger("click");        // parent.location.reload();
            return false;
        });
        return false;
    }, function(data, ret){
        //失败的回调
        Fast.api.close();
        parent.location.reload();
        return false;
    });
})