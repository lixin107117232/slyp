define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'integral/banner/index' + location.search,
                    add_url: 'integral/banner/add',
                    edit_url: 'integral/banner/edit',
                    del_url: 'integral/banner/del',
                    multi_url: 'integral/banner/multi',
                    import_url: 'integral/banner/import',
                    table: 'integral_banner',
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
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
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
            check();
        },
        edit: function () {
            Controller.api.bindevent();
            check();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
function check() {

    $("input[name='row[status]']").click(function () {
        if($(this).val()==1){
            $(".status_view").show();
            $(".status_view1").show();
            $(".status_view2").hide();
            $("#status_id").text("商品");
            $("#c-status_id").attr("data-source","integral/goods/selepage");
            $("#c-status_id_text").data("selectPageObject").option.data = "integral/goods/selepage";
        }else if($(this).val()==2){
            $(".status_view").show();
            $(".status_view2").show();
            $(".status_view1").hide();
            $("#status_id").text("分类");
            $("#c-status_id").attr("data-source","integral/type/index");
            $("#c-status_id_text").data("selectPageObject").option.data = "integral/type/index";
        }else if($(this).val()==3){
            $(".status_view").show();
            $(".status_view2").show();
            $(".status_view1").hide();
            $("#status_id").text("代理商礼包");
            $("#c-status_id").attr("data-source","agent/gift/index");
            $("#c-status_id_text").data("selectPageObject").option.data = "agent/gift/index";
        }
        else{
            $(".status_view").hide();
            $(".status_view1").hide();
            $(".status_view2").hide();
        }

    })
}