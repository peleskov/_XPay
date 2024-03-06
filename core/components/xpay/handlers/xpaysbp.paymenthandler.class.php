<?php
if (!class_exists('msPaymentInterface')) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/core/components/minishop2/handlers/mspaymenthandler.class.php';
}

class XPaymentSBP extends msPaymentHandler implements msPaymentInterface
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
			'invoice_url' => 'https://pay1time.com/api/invoice',
			'api_key' => $this->modx->getOption('xpay_api_key', null, ''),
			'api_key_sbp' => $this->modx->getOption('xpay_api_key_sbp', null, ''),
			'merchant_id' => $this->modx->getOption('xpay_merchant_id', null, ''),
			'site_url' => $siteUrl,
			'site_name' => $this->modx->getOption('site_name'),
			'ttl' => (int) $this->modx->getOption('xpay_ttl', null, 24),
			'currency' => $this->modx->getOption('xpay_currency', null, 'RUB'),
			'successID' => $this->modx->getOption('xpay_successID', null, '1'),
			'failureID' => $this->modx->getOption('xpay_failureID', null, '1'),
			'payWithoutFormSbp' => 'https://pay1time.com/payWithoutFormSbp',
			'payWithoutFormStatusPaymentSbp' => 'https://pay1time.com/payWithoutFormStatusPaymentSbp',
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
		$qrLink = '';
		$invoice = '';
		if ($invoice = $this->modx->getObject('XPayOrder', ['order_id' => $order->get('id')])) {
			$curlOptions = array(
				CURLOPT_URL => $this->config['payment_url'] . '?order_id=' . $invoice->get('order_id') . '&id=' . $invoice->get('invoice_id'),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'Authorization: Token: ' . $this->config['api_key']
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
					if ($invoice->get('qrLink')) {
						$qrLink = $invoice->get('qrLink');
					}
				}
			}
		}
		if ($qrLink == '') {
			if (!$invoice) {
				$invoice = $this->modx->newObject('XPayOrder');
				$requestParams = array(
					'order_id' => $order->get('id'),
					'amount' => $order->get('cost') * 100,
					'email' => $order->UserProfile->get('email'),
					'return_url' => $this->config['result_url'] . '?action=success&order_id=' . $order->get('id'),
					'callback_url' => $this->config['result_url'] . '?action=result&order_id=' . $order->get('id'),
					'processing_url' => $this->config['result_url'] . '?action=processing&order_id=' . $order->get('id'),
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
					CURLOPT_URL => $this->config['invoice_url'],
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_HTTPHEADER => [
						'Content-Type: application/json',
						'Authorization: Token: ' . $this->config['api_key_sbp']
					],
					CURLOPT_POSTFIELDS => $request,
				);


				$ch = curl_init();
				curl_setopt_array($ch, $curlOptions);

				$response = curl_exec($ch);
				if (curl_errno($ch)) {
					$this->modx->log(1, '[XPay] Error in response API 1: ' . $response);
				} else {
					$result = json_decode($response, true);
				}
				curl_close($ch);

				if (is_array($result) && array_key_exists('guid', $result) && array_key_exists('id', $result)) {
					$invoice->set('order_id', $order->get('id'));
					$invoice->set('invoice_id', $result['id']);
					$invoice->set('guid', $result['guid']);
					if (!$invoice->save()) {
						$this->modx->log(1, '[XPay] Error save invoice # ' . $invoice->get('id'));
					}
				} else {
					$this->modx->log(1, '[XPay] Error in response API 3: ' . $response);
				}
			}


			if (!$invoice['guid_spb']) {
				$curlOptions = array(
					CURLOPT_URL => $this->config['payWithoutFormSbp'] . '/' . $guid,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_HTTPHEADER => [
						'Content-Type: application/json',
						'Authorization: Token: ' . $this->config['api_key_sbp'],
						'visitorId: ' . $order->UserProfile->get('email') . '_' . $order->get('id')
					],
					CURLOPT_POSTFIELDS => '',
				);

				$ch = curl_init();
				curl_setopt_array($ch, $curlOptions);
				$response = curl_exec($ch);
				$this->modx->log(1, 'Делаем платеж 2' . $response);

				if (curl_errno($ch)) {
					$this->modx->log(1, '[XPay] Error in response API 2: ' . $response);
				} else {
					$result = json_decode($response, true);
				}
				curl_close($ch);

				if (is_array($result) && array_key_exists('guid_spb', $result)) {
					$invoice->set('guid_spb', $result['guid_spb']);
					if (!$invoice->save()) {
						$this->modx->log(1, '[XPay] Error save invoice # ' . $invoice->get('id'));
					}
				} else {
					$this->modx->log(1, '[XPay] Error in response API 3: ' . $response);
				}				
			}

			for ($i = 0; $i < 5; $i++) {
				$curlOptions = array(
					CURLOPT_URL => $this->config['payWithoutFormStatusPaymentSbp'] . '/' . $invoice->get('guid_spb'),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HTTPHEADER => [
						'Content-Type: application/json',
					],
				);

				$ch = curl_init();
				curl_setopt_array($ch, $curlOptions);
				$response = curl_exec($ch);
				$this->modx->log(1, $i . '-- ' . $response);
				if (curl_errno($ch)) {
					$this->modx->log(1, '[XPay] Error in response API: ' . $response);
				} else {
					$result = json_decode($response, true);
				}
				curl_close($ch);

				if ($result['status'] != false && $result['qrLink']) {
					$qrLink = $result['qrLink'];
					$invoice->set('qrLink', $qrLink);
					$invoice->set('qrImage', $result['qrImage']);
					if (!$invoice->save()) {
						$this->modx->log(1, '[XPay] Error save invoice # ' . $invoice->get('id'));
					}
					break;
				}

				sleep(3);
			}
		}
		return $qrLink;
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
