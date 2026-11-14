<?php 
return [
    'labels' => [
        'Cash' => '提现列表',
        'cash' => '提现列表',
        'master' => '达人管理',
    ],
    'fields' => [
        'master_id' => '所属达人',
        'amount' => '金额',
        'status' => '状态',
        'remark' => '驳回理由',
        'apply_at' => '申请时间',
        'pay_at' => '到账时间',
        'master' => [
            'username' => '所属达人',
            'type' => '收款方式',
            'realname' => '真实姓名',
            'account' => '收款账户',
            'canCommission' => '可提现余额',
        ],
        'account' => '收款账户',
    ],
    'options' => [
    ],
];
