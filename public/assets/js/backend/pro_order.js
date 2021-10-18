define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'pro_order/index' + location.search,
                    add_url: 'pro_order/add',
                    edit_url: 'pro_order/edit',
                    del_url: 'pro_order/del',
                    multi_url: 'pro_order/multi',
                    import_url: 'pro_order/import',
                    table: 'proOrder',
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
                        {field: 'mainId', title: __('Mainid')},
                        {field: 'imageUrl', title: __('Imageurl'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'skuName', title: __('Skuname'), operate: 'LIKE'},
                        {field: 'skuNum', title: __('Skunum')},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'orderId', title: __('Orderid'), operate: 'LIKE'},
                        {field: 'orderTime', title: __('Ordertime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'finishTime', title: __('Finishtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'modifyTime', title: __('Modifytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'skuId', title: __('Skuid'), operate: 'LIKE'},
                        {field: 'commissionRate', title: __('Commissionrate'), operate: 'LIKE'},
                        {field: 'actualCosPrice', title: __('Actualcosprice'), operate: 'LIKE'},
                        {field: 'actualFee', title: __('Actualfee'), operate:'BETWEEN'},
                        {field: 'estimateFee', title: __('Estimatefee'), operate:'BETWEEN'},
                        {field: 'commission', title: __('Commission'), operate: 'LIKE'},
                        {field: 'subSideRate', title: __('Subsiderate'), operate: 'LIKE'},
                        {field: 'subsidy', title: __('Subsidy')},
                        {field: 'payMonth', title: __('Paymonth'), operate: 'LIKE'},
                        {field: 'estimateCosPrice', title: __('Estimatecosprice'), operate:'BETWEEN'},
                        {field: 'owner', title: __('Owner'), operate: 'LIKE'},
                        {field: 'pid', title: __('Pid'), operate: 'LIKE'},
                        {field: 'positionId', title: __('Positionid'), operate: 'LIKE'},
                        {field: 'validCode', title: __('Validcode'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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