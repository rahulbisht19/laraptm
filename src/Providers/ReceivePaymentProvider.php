<?php

namespace Anand\LaravelPaytmWallet\Providers;
use Anand\LaravelPaytmWallet\Facades\PaytmWallet;
use Anand\LaravelPaytmWallet\Traits\HasTransactionStatus;
use Illuminate\Http\Request;

class ReceivePaymentProvider extends PaytmWalletProvider{
	use HasTransactionStatus;
	
	private $parameters = null;
	private $view = 'paytmwallet::transact';

    public function prepare($params = array()){
		$defaults = [
			'order' => NULL,
			'user' => NULL,
			'amount' => NULL,
            'callback_url' => NULL,
            'email' => NULL,
            'mobile_number' => NULL,
		];

		$_p = array_merge($defaults, $params);
		foreach ($_p as $key => $value) {

			if ($value == NULL) {
				
				throw new \Exception(' \''.$key.'\' parameter not specified in array passed in prepare() method');
				
				return false;
			}
		}
		$this->parameters = $_p;
		return $this;
	}

	public function receive(){
		if ($this->parameters == null) {
			throw new \Exception("prepare() method not called");
		}
		return $this->beginTransaction();
	}

	public function view($view) {
		if($view) {
			$this->view = $view;
		}
		return $this;
	}

	private function beginTransaction(){

		$apiURL = getPaytmURL($this->inititate_transaction_url, $this->environment) . '?mid='.$this->merchant_id.'&orderId='.$this->parameters['order'];
		$paytmParams = array();

		$paytmParams["body"] = array(
			"requestType"   => "Payment",
			"mid"           => $this->merchant_id,
			"websiteName"   => $this->merchant_website,
			"orderId"       => $this->parameters['order'],
			"callbackUrl"   => $this->parameters['callback_url'],
			"txnAmount"     => array(
				"value"     => $this->parameters['amount'],
				"currency"  => "INR",
			),
			"userInfo"      => array(
				"custId"    => $this->parameters['email'],
			),
		);
		/*
		* Generate checksum by parameters we have in body
		* Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
		*/
		$checksum = generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $this->merchant_key);
		
		$paytmParams["head"] = array(
			"signature"	=> $checksum
		);

		$postData = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

		$response = executecUrl($apiURL, $postData);
		$data = array('orderId' => $this->parameters['order'], 'amount' => $this->parameters['amount']);
		if(!empty($response['body']['txnToken'])){
			$data['txnToken'] = $response['body']['txnToken'];
		}else{
			$data['txnToken'] = '';
			$data['message'] = "Something went wrong";
		}
		$data['apiurl'] = $apiURL;
		//echo "<pre>";print_r($data);die;
		$checkout_url = str_replace('MID',$this->merchant_id, getPaytmURL($this->checkout_js_url,$this->environment));
			
		echo  '<style type="text/css">
					#paytm-pg-spinner {margin: 20% auto 0;width: 70px;text-align: center;z-index: 999999;position: relative;}

					#paytm-pg-spinner > div {width: 10px;height: 10px;background-color: #012b71;border-radius: 100%;display: inline-block;-webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;animation: sk-bouncedelay 1.4s infinite ease-in-out both;}

					#paytm-pg-spinner .bounce1 {-webkit-animation-delay: -0.64s;animation-delay: -0.64s;}

					#paytm-pg-spinner .bounce2 {-webkit-animation-delay: -0.48s;animation-delay: -0.48s;}
					#paytm-pg-spinner .bounce3 {-webkit-animation-delay: -0.32s;animation-delay: -0.32s;}

					#paytm-pg-spinner .bounce4 {-webkit-animation-delay: -0.16s;animation-delay: -0.16s;}
					#paytm-pg-spinner .bounce4, #paytm-pg-spinner .bounce5{background-color: #48baf5;} 
					@-webkit-keyframes sk-bouncedelay {0%, 80%, 100% { -webkit-transform: scale(0) }40% { -webkit-transform: scale(1.0) }}

					@keyframes sk-bouncedelay { 0%, 80%, 100% { -webkit-transform: scale(0);transform: scale(0); } 40% { 
					    -webkit-transform: scale(1.0); transform: scale(1.0);}}
					.paytm-overlay{width: 100%;position: fixed;top: 0px;opacity: .4;height: 100%;background: #000;}

					</style><div id="paytm-pg-spinner" class="paytm-pg-loader">
					  <div class="bounce1"></div>
					  <div class="bounce2"></div>
					  <div class="bounce3"></div>
					  <div class="bounce4"></div>
					  <div class="bounce5"></div>
					</div>
					<div class="paytm-overlay paytm-pg-loader"></div><script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script><script type="application/javascript" crossorigin="anonymous" src="'.$checkout_url.'" ></script><script type="text/javascript">
				function invokeBlinkCheckoutPopup(){
				window.Paytm.CheckoutJS.init({
					"root": "",
					"flow": "DEFAULT",
					"data": {
						"orderId": "'.$this->parameters['order'].'",
						"token": "'.$data['txnToken'].'",
						"tokenType": "TXN_TOKEN",
						"amount": "'.$this->parameters['amount'].'",
					},
					handler:{
							transactionStatus:function(data){
						} , 
						notifyMerchant:function notifyMerchant(eventName,data){
							if(eventName=="APP_CLOSED")
							{
								jQuery(".loading-paytm").hide();
								jQuery(".refresh-payment").show();
							}
							console.log("notify merchant about the payment state");
						} 
						}
				}).then(function(){
					window.Paytm.CheckoutJS.invoke();
				});
				}
				jQuery(function(){
					setTimeout(function(){invokeBlinkCheckoutPopup()},2000);
				});
				</script>
				';die;


		$params = [
			'REQUEST_TYPE' => 'DEFAULT',
			'MID' => $this->merchant_id,
			'ORDER_ID' => $this->parameters['order'],
			'CUST_ID' => $this->parameters['user'],
			'INDUSTRY_TYPE_ID' => $this->industry_type,
			'CHANNEL_ID' => $this->channel,
			'TXN_AMOUNT' => $this->parameters['amount'],
			'WEBSITE' => $this->merchant_website,
            'CALLBACK_URL' => $this->parameters['callback_url'],
            'MOBILE_NO' => $this->parameters['mobile_number'],
            'EMAIL' => $this->parameters['email'],
		];
		return view('paytmwallet::form')->with('view', $this->view)->with('params', $params)->with('txn_url', $this->paytm_txn_url)->with('checkSum', getChecksumFromArray($params, $this->merchant_key));
	}

    public function getOrderId(){
        return $this->response()['ORDERID'];
    }
    public function getTransactionId(){
        return $this->response()['TXNID'];
    }

}
