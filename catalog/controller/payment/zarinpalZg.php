<?php
class ControllerPaymentZarinpalzg extends Controller {
	protected function index() {
		$this->language->load('payment/zarinpalzg');
    		$this->data['button_confirm'] = $this->language->get('button_confirm');
		
		$this->data['text_wait'] = $this->language->get('text_wait');
		$this->data['text_ersal'] = $this->language->get('text_ersal');
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/zarinpalzg.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/zarinpalzg.tpl';
		} else {
			$this->template = 'default/template/payment/zarinpalzg.tpl';
		}
		
		$this->render();		
	}
	public function confirm() {
		
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		$this->data['Amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$this->data['PIN']=$this->config->get('zarinpalzg_PIN');
		$this->data['ResNum'] = $this->session->data['order_id'];
		$this->data['return'] = $this->url->link('checkout/success', '', 'SSL');
		$this->data['cancel_return'] = $this->url->link('checkout/payment', '', 'SSL');
		$this->data['back'] = $this->url->link('checkout/payment', '', 'SSL');
		
		$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));	
		
		if ((!$client)) {
			$json = array();
			$json['error']= "Can not connect to ZarinPal.<br>";	
		
			$this->response->setOutput(json_encode($json));
		}
	
		$amount = intval($this->data['Amount'])/$order_info['currency_value'];
		if ($this->currency->getCode()=='RLS') {
			$amount = $amount / 10;
		}

		$this->data['order_id'] = $this->session->data['order_id'];
		$callbackUrl  =  $this->url->link('payment/zarinpalzg/callback&order_id=' . $this->data['order_id']);
		
		$result = $client->PaymentRequest(array(
			'MerchantID' 	=> $this->data['PIN'],
			'Amount' 	=> $amount,
			'Description' 	=> 'خريد شماره: ' . $order_info['order_id'],
			'Email' 	=> '',
			'Mobile' 	=> '',
			'CallbackURL' 	=> $callbackUrl
		));
		if ($result->Status == 100) {
			$this->data['action'] = 'https://www.zarinpal.com/pg/StartPay/'. $result->Authority .'/ZarinGate';
			$json = array();
			$json['success']= $this->data['action'];
			$this->response->setOutput(json_encode($json));
		} else {
			$this->CheckState($result->Status);
		}
	}

	public function CheckState($status) {
		$json = array();
		switch ($status) {
			case "-1" :
				$json['error']="اطلاعات ارسالی ناقص می باشند";
				break;
			case "-2" :
				$json['error']="وب سرويس نا معتبر می باشد";
				break;
			case "0" :
			 $json['error']="عمليات پرداخت طی نشده است";
				break;
			case "1" :
				break;
			case "-11" :
				$json['error']="مقدار تراکنش تطابق نمی کند";
				break;
				
			case "-12" :
				$json['error']="زمان پرداخت طی شده و کاربر اقدام به پرداخت صورتحساب ننموده است";
				break;
			
			default :
				$json['error']= "خطای نامشخص. کد خطا: " ;
				break;
		}
		
		$this->response->setOutput(json_encode($json));
	}

	function verify_payment($authority, $amount){
		if ($authority) {
			//$client = new SoapClient("http://www.zarinpalzg.com/WebserviceGateway/wsdl");
			 $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', array('encoding' => 'UTF-8'));
			if ((!$client)) {
				echo  "Error: can not connect to ZarinPal.<br>";
				return false;
			} else {
				$this->data['PIN'] = $this->config->get('zarinpalzg_PIN');
				$result = $client->PaymentVerification(array(
					'MerchantID'	 => $this->data['PIN'],
					'Authority' 	 => $authority,
					'Amount'	 => $amount
				));
			
				$this->CheckState($result);
				
				if ($result->Status==100) {
					return true;
				} else {
					return false;
				}
			}
		} else {
			return false;
		}
	
		return false;
	}

	public function callback() {
		$authority = $this->request->get['Authority'];
		$order_id = $this->request->get['order_id'];
		$MerchantID = $this->config->get('zarinpalzg_PIN');
		$debugmod = false;
		
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);		//echo $this->data['Amount'];
		if ($order_info) {
			if (($this->verify_payment($authority, $amount)) or ($debugmod==true)) {
				$this->model_checkout_order->confirm($order_id, $this->config->get('zarinpalzg_order_status_id'),'شماره رسيد ديجيتالي; Authority: '.$authority);
				
				$this->response->setOutput('<html><head><meta http-equiv="refresh" CONTENT="2; url=' . $this->url->link('checkout/success') . '"></head><body><table border="0" width="100%"><tr><td>&nbsp;</td><td style="border: 1px solid gray; font-family: tahoma; font-size: 14px; direction: rtl; text-align: right;">با تشکر پرداخت تکمیل شد.لطفا چند لحظه صبر کنید و یا  <a href="' . $this->url->link('checkout/success') . '"><b>اینجا کلیک نمایید</b></a></td><td>&nbsp;</td></tr></table></body></html>');
			}
		} else {
			$this->response->setOutput('<html><body><table border="0" width="100%"><tr><td>&nbsp;</td><td style="border: 1px solid gray; font-family: tahoma; font-size: 14px; direction: rtl; text-align: right;">.<br /><br /><a href="' . $this->url->link('checkout/cart').  '"><b>بازگشت به فروشگاه</b></a></td><td>&nbsp;</td></tr></table></body></html>');
		}
	}
	
}
?>
