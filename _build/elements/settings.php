<?php

return [
    'api_url' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'textfield',
		'value' => 'https://pay1time.com/api/payments',
    ],
	'api_key' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'textfield',
		'value' => 'c373fe95043e11236ec4e2f70dec19f7',
	],
	'merchant_id' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'textfield',
		'value' => '000000493',
	],
	'ttl' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'textfield',
		'value' => 24,
	],
	'payWithoutForm' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'combo-boolean',
		'value' => 1,
	],
	'currency' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'textfield',
		'value' => 'RUB',
	],    
	'successID' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'numberfield',
		'value' => '',
	],    
	'failureID' => [
		'area' => 'xpay',
		'namespace' => 'xpay',
		'xtype' => 'numberfield',
		'value' => '',
	],    
];