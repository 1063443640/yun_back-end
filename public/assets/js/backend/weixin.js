define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'weixin/index' + location.search,
                    add_url: 'weixin/add',
                    edit_url: 'weixin/edit',
                    del_url: 'weixin/del',
                    multi_url: 'weixin/multi',
                    import_url: 'weixin/import',
                    table: 'weixin',
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
                        {field: 'open_id', title: __('Open_id'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'number', title: __('Number'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        {field: 'flag', title: __('是否为顶级代理'),formatter: Table.api.formatter.label,searchList:{0:"否",1:"是"},custom:{1: 'warning', 0: 'success'}},
                        {field: 'invitation_code', title: __('Invitation_code'), operate: 'LIKE'},
                        {field: 'superior_invitation_code', title: __('Superior_invitation_code'), operate: 'LIKE'},
                        {field: 'month_my_profit', title: __('Month_my_profit'), operate:'BETWEEN',sortable:true ,width:"120"},
                        {field: 'month_team_profit', title: __('Month_team_profit'), operate:'BETWEEN',sortable:true,width:"120"},
                        {field: 'month_profit', title: __('Month_profit'), operate:'BETWEEN',sortable:true,width:"100"},
                        {field: 'all_profit', title: __('All_profit'), operate:'BETWEEN',sortable:true,width:"100"},
                        {field: 'withdrawal_amount', title: __('Withdrawal_amount'),sortable:true,width:"100"},
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