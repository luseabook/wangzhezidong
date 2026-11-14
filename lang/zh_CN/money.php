<?php 
return [
    'labels' => [
        'Money' => '流水明细',
        'money' => '流水明细',
    ],
    'fields' => [
        'oid' => '订单ID',
        'type' => '类型',
        'money' => '金额',
        'before_money' => '操作前金额',
        'after_money' => '操作后金额',
        'content' => '操作',
        'master_id' => '所属达人',
        'master' => [
            'username' => '所属达人',
        ],
        'is_parent' => '是否是抽成',
        'itemId' => '商品ID',
    ],
    'options' => [
    ],
];
