define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'robots/index' + location.search,
                    add_url: 'robots/add',
                    edit_url: 'robots/edit',
                    del_url: 'robots/del',
                    multi_url: 'robots/multi',
                    import_url: 'robots/import',
                    table: 'robots',
                }
            }
            );

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
                        {field: 'open_id', title: __('Open_id'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'), operate: 'LIKE'},
                        {field: 'vcNickName', title: __("微信昵称"), operate: 'LIKE'},
                        {field: 'vcHeadImgUrl', title: __("微信头像"), operate: 'LIKE',formatter: Table.api.formatter.image},
                        {field: 'type', title: __('Type'), operate: 'LIKE',searchList:{"user":"用户号","platform":"平台号"},formatter:Table.api.formatter.label},
                        {field: 'status', title: __('Status'),formatter: Table.api.formatter.label,searchList:{0:"掉线",1:"在线"},custom:{1: 'success', 0: 'danger'}},
                        {field: 'flag', title: __('Flag'), formatter: Table.api.formatter.label,searchList:{1:"空闲",2:"忙碌"},custom:{1: 'success', 0: 'danger'}},
                        {field: 'wx_id', title: __('Wx_id'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE'},
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