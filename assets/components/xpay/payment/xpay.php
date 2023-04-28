<?php

if (!isset($modx)) {
	define('MODX_API_MODE', true);
	require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

	$modx->getService('error', 'error.modError');
}

$modx->error->message = null;
/** @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');
$XPay = $modx->getService('XPay', 'XPay', $modx->getOption('core_path') . 'components/xpay/model/', []);
if (!class_exists('XPayment')) {
	exit('Error: could not load payment class "XPayment".');
} elseif (!$XPay) {
	exit('Error: could not load XPay class!');
} elseif (empty($_GET['order_id'])) {
	exit('Error: the order id is not specified.');
} elseif (!$invoice = $modx->getObject('XPayOrder', ['order_id' => $_GET['order_id']])) {
	exit('Error: could not get invoice.');
} elseif (!$order = $modx->getObject('msOrder', $invoice->get('order_id'))) {
	exit('Error: could not get order.');
}

/** @var msPaymentInterface|XPayment $handler */
$handler = new XPayment($order);
switch ($_GET['action']) {
	case 'result':
		$handler->processResult();
		break;

	case 'success':
		$response = $handler->receive($order, 2);
		$url = $modx->makeUrl($handler->config['successID'], '', [], 'full');
		$modx->sendRedirect($url);
		break;

	case 'failure':
		$url = $modx->makeUrl($handler->config['failureID'], '', [], 'full');
		$modx->sendRedirect($url);
		break;
}




