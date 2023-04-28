<?php
if (!class_exists('msPaymentInterface')) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/core/components/minishop2/handlers/mspaymenthandler.class.php';
}

class XPayment extends msPaymentHandler implements msPaymentInterface
{

	/** @var XPay $XPay */
	public $xpay;


	function __construct(xPDOObject $object, $config = array())
	{
		parent::__construct($object, $config);

		$this->xpay = $this->modx->getService('XPay', 'XPay', $this->modx->getOption('core_path') . 'components/xpay/model/', []);
		if (!$this->xpay) {
			$this->modx->log(1, 'Could not load XPay class!');
		}
		$siteUrl = $this->modx->getOption('site_url');

		$this->config = array_merge(array(
			'result_url' => $siteUrl . $this->modx->getOption('assets_url') . 'components/xpay/payment/xpay.php',
			'payment_url' => $this->modx->getOption('xpay_api_url', null, ''),
			'api_key' => $this->modx->getOption('xpay_api_key', null, ''),
			'merchant_id' => $this->modx->getOption('xpay_merchant_id', null, ''),
			'site_url' => $siteUrl,
			'site_name' => $this->modx->getOption('site_name'),
			'ttl' => (int) $this->modx->getOption('xpay_ttl', null, 24),
			'currency' => $this->modx->getOption('xpay_currency', null, 'RUB'),
			'successID' => $this->modx->getOption('xpay_successID', null, '1'),
			'failureID' => $this->modx->getOption('xpay_failureID', null, '1'),
			'payWithoutForm' => $this->modx->getOption('xpay_payWithoutForm', null, '1'),
		), $config);
	}


	/**
	 * @param msOrder $order
	 *
	 * @return array|string
	 */
	public function send(msOrder $order)
	{

		if ($order->get('status') > 1) {
			return $this->error('ms2_err_status_wrong');
		}
		if ($http_query = $this->getPaymentLink($order)) {
			return $this->success('', array('redirect' => $http_query));
		}
		return $this->success('', ['msorder' => $order->get('id')]);
	}


	/**
	 * @param msOrder $order
	 *
	 * @return string
	 */
	public function getPaymentLink(msOrder $order)
	{
		$apiKey = $this->config['api_key'];
		$link = '';

		if ($invoice = $this->modx->getObject('XPayOrder', ['order_id' => $order->get('id')])) {
			$curlOptions = array(
				CURLOPT_URL => $this->config['payment_url'] . '?order_id=' . $invoice->get('order_id') . '&id=' . $invoice->get('invoice_id'),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'Authorization: Token: ' . $apiKey
				],
			);

			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);

			$response = curl_exec($ch);
			if (curl_errno($ch)) {
				$result = curl_error($ch);
				$this->modx->log(1, '[XPay] Error in response API: ' . $response);
			} else {
				$result = json_decode($response, true);
			}

			curl_close($ch);
			if (is_array($result) && array_key_exists('status', $result)) {
				if ($result['status'] == 'STATUS_INIT') {
					if ($invoice->get('3ds_link') && ($invoice->get('3ds_created') - time()) < 180) {
						$link = $invoice->get('3ds_link');
					} else {
						$link = $invoice->get('link');
					}
				}
			}
		}
		if ($link == '') {
			if (!$invoice) $invoice = $this->modx->newObject('XPayOrder');
			$requestParams = array(
				'order_id' => $order->get('id'),
				'amount' => $order->get('cost') * 100,
				'email' => $order->UserProfile->get('email'),
				'return_url' => $this->config['result_url'] . '?action=success&order_id=' . $order->get('id'),
				'callback_url' => $this->config['result_url'] . '?action=result&order_id=' . $order->get('id'),
				'fail_url' => $this->config['result_url'] . '?action=failure&order_id=' . $order->get('id'),
				'payer_name' => $order->UserProfile->get('fullname'),
				'payer_phone' => $order->UserProfile->get('mobilephone'),
				'payer_email' => $order->UserProfile->get('email'),
				'ttl' => $this->config['ttl'],
				'currency' => $this->config['currency'],
				'merchant' => array(
					'id' => $this->config['merchant_id'],
					'name' => $this->config['site_name'],
					'url' => $this->config['site_url'],
				),
			);


			$request = json_encode($requestParams);
			$curlOptions = array(
				CURLOPT_URL => $this->config['payment_url'],
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'Authorization: Token: ' . $apiKey
				],
				CURLOPT_POSTFIELDS => $request,
			);


			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);

			$response = curl_exec($ch);
			if (curl_errno($ch)) {
				$result = curl_error($ch);
				$this->modx->log(1, '[XPay] Error in response API: ' . $response);
			} else {
				$result = json_decode($response, true);
			}

			curl_close($ch);
			$invoice_id = '';
			if (is_array($result) && array_key_exists('url', $result) && array_key_exists('id', $result)) {
				$link = $result['url'];
				$invoice_id = $result['id'];

				$invoice->set('order_id', $order->get('id'));
				$invoice->set('invoice_id', $invoice_id);
				$invoice->set('link', $link);
				if (!$invoice->save()) {
					$this->modx->log(1, '[XPay] Error save invoice # ' . $invoice->get('id'));
				}
				/*
				$cardParams = array(
					'cardNumber' => !empty($_POST['cc_num'])? preg_replace("/[^0-9]/", '', $_POST['cc_num']):'',
					'cardHolder' => !empty($_POST['cc_holder'])? $_POST['cc_holder']:'',
					'expireMonth' => !empty($_POST['cc_month'])? preg_replace("/[^0-9]/", '', $_POST['cc_month']):'',
					'expireYear' => !empty($_POST['cc_year'])? preg_replace("/[^0-9]/", '', $_POST['cc_year']):'',
					'cvv' => !empty($_POST['cc_cvc'])? preg_replace("/[^0-9]/", '', $_POST['cc_cvc']):'',
				);	
	
				if ($this->config['payWithoutForm'] == 1 && 
					!empty($cardParams['cardNumber']) &&
					!empty($cardParams['cardHolder']) &&
					!empty($cardParams['expireMonth']) &&
					!empty($cardParams['expireYear']) &&
					!empty($cardParams['cvv'])) {
					

					$request = json_encode($cardParams);
					$curlOptions = array(
						CURLOPT_URL => 'https://pay1time.com/payWithoutForm/' . $invoice->get('invoice_id'),
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_POST => true,
						CURLOPT_HTTPHEADER => [
							'Content-Type: application/json',
							'Authorization: Token: ' . $apiKey
						],
						CURLOPT_POSTFIELDS => $request,
					);
		
		
					$ch = curl_init();
					curl_setopt_array($ch, $curlOptions);
					$response = curl_exec($ch);

					if (curl_errno($ch)) {
						$result = curl_error($ch);
						$this->modx->log(1, '[XPay] Error in response API: ' . $response);
					} else {
						$result = json_decode($response, true);
					}

					curl_close($ch);
					$this->modx->log(1, print_r($curlOptions, 1));
					$this->modx->log(1, $response);
					$this->modx->log(1, print_r($result, 1));

					if (is_array($result) && array_key_exists('url', $result)) {
						$link = $result['url'];
						$invoice->set('3ds_link', $link);
						$invoice->set('3ds_created', time());
						if (!$invoice->save()) {
							$this->modx->log(1, '[XPay] Error save invoice # ' . $invoice->get('id'));
						}						
					}

				}
				*/
			} else {
				$this->modx->log(1, '[XPay] Error in response API: ' . $response);
			}
		}
		return $link;
	}


	/**
	 * @param msOrder $order
	 * @param int $status
	 *
	 * @return bool
	 */
	public function receive(msOrder $order, $status = 0)
	{
		if (!empty($status) && $status == 2) {
			$apiKey = $this->config['api_key'];
			$invoice_status = false;
			if ($invoice = $this->modx->getObject('XPayOrder', ['order_id' => $order->get('id')])) {
				$curlOptions = array(
					CURLOPT_URL => $this->config['payment_url'] . '?order_id=' . $invoice->get('order_id') . '&id=' . $invoice->get('invoice_id'),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HTTPHEADER => [
						'Content-Type: application/json',
						'Authorization: Token: ' . $apiKey
					],
				);

				$ch = curl_init();
				curl_setopt_array($ch, $curlOptions);

				$response = curl_exec($ch);
				if (curl_errno($ch)) {
					$result = curl_error($ch);
					$this->modx->log(1, '[XPay] Error in response API: ' . $response);
				} else {
					$result = json_decode($response, true);
				}

				curl_close($ch);
				if (is_array($result) && array_key_exists('status', $result)) {
					if ($result['status'] == 'STATUS_PAID') {
						$invoice_status = true;
					}
				}
			}
			if ($invoice_status) {
				$response = $this->ms2->changeOrderStatus($order->get('id'), $status);
				if ($response !== true) {
					$this->modx->log(modX::LOG_LEVEL_ERROR, "[XPay] Error on change status of order #{$order->num} to \"{$status}\": {$response}");
				}
				return $response;
			}
		}

		return false;
	}


	/**
	 * Process response from service
	 *
	 * @return array/boolean
	 */
	public function processResult()
	{
		return true;
	}
}
