define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'hospital/banner/index' + location.search,
                    add_url: 'hospital/banner/add',
                    edit_url: 'hospital/banner/edit',
                    del_url: 'hospital/banner/del',
                    multi_url: 'hospital/banner/multi',
                    import_url: 'hospital/banner/import',
                    table: 'hospital_banner',
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
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'status_id', title: __('Status_id')},
                        {field: 'weigh', title: __('Weigh'), operate: false},
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


$("input[name='row[status]']").click(function () {
    if($(this).val()==1){
        console.log($("#c-status_id").attr("data-source"))
        $(".status_view").show();
        $(".status_view1").show();
        $(".status_view2").hide();
        $("#c-status_id").attr("data-source","integral/goods/selepage");
    }else if($(this).val()==2){
        console.log($("#c-status_id"))
        $(".status_view").show();
        $(".status_view2").show();
        $(".status_view1").hide();
        $("#c-status_id").attr("data-source","integral/type/index");
    }else{
        $(".status_view").hide();
        $(".status_view1").hide();
        $(".status_view2").hide();
    }



})