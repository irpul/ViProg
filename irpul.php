<?php
	$pluginData[irpul][type] = 'payment';
	$pluginData[irpul][name] = 'پرداخت آنلاین با ایرپول';
	$pluginData[irpul][uniq] = 'irpul';
	$pluginData[irpul][note] = 'irpul';
	$pluginData[irpul][description] = '';
	$pluginData[irpul][author][name] = 'طراحی و برنامه نویسی mrtaylor.ir';
	$pluginData[irpul][author][url] = 'http://mrtaylor.ir';
	$pluginData[irpul][author][email] = 'mey_reza@yahoo.com';

	$pluginData[irpul][field][config][1][title] = 'لطفا شناسه درگاه ثبت شده خود را در ایرپول در فیلد زیر وارد نمایید';
	$pluginData[irpul][field][config][1][name] = 'pin';
	function gateway__irpul($data)
	{
		global $config,$smarty,$db;
		$api = $data[pin] ;
		$amount = $data[amount];
        $redirect = $data[callback];
		$order_id = $data[invoice_id];
		$parameters = array
		(
			'plugin'		=> 'ViProg',
			'webgate_id' 	=> $api,
			'order_id'		=> $order_id,
			'product'		=> '',
			'payer_name'	=> '',
			'phone' 		=> '',
			'mobile' 		=> '',
			'email' 		=> '',
			'amount' 		=> $amount,
			'callback_url' 	=> $redirect,
			'address' 		=> '',
			'description' 	=> '',
		);
		try {
			$client = new SoapClient('https://irpul.ir/webservice.php?wsdl' , array('soap_version'=>'SOAP_1_2','cache_wsdl'=>WSDL_CACHE_NONE ,'encoding'=>'UTF-8'));
			$result = $client->Payment($parameters);
		}catch (Exception $e) { echo 'Error'. $e->getMessage();  }
		
		if ( $result['res_code']===1  && is_numeric($result['res_code']))
		{
			$update[payment_rand]	= $result['res_code'];
			$sql = $db->prepare("UPDATE payment SET payment_rand = ? WHERE payment_rand = '$order_id' LIMIT 1");
			//$sql = $db->queryUpdate('payment', $update, 'WHERE payment_rand = "'.$order_id.'" LIMIT 1;');
			//$db->execute($sql);
			$sql->execute(array($result['res_code'],$order_id));
			$go = $result['url'];
			redirect_to($go);
		}
		else{
			//-- نمایش خطا
			$data[title] = 'خطای سیستم';
			$data[message] = '<font color="red">در ارتباط با درگاه irpul مشکلی به وجود آمده است.</font> شماره خطا: '. $result['res_code'] . ' ' . $result['status'] .'<br /><a href="index.php" class="button">بازگشت</a>';
			//$smarty->assign('data', $data);
			//$smarty->display('message.tpl');
			return $data;
			exit;

		}
	}

	function callback__irpul($data){
		global $db,$get,$smarty;
		$api = $data[pin];
		
		function url_decrypt($string){
			$counter = 0;
			$data = str_replace(array('-','_','.'),array('+','/','='),$string);
			$mod4 = strlen($data) % 4;
			if ($mod4) {
			$data .= substr('====', $mod4);
			}
			$decrypted = base64_decode($data);
			
			$check = array('tran_id','order_id','amount','refcode','status');
			foreach($check as $str){
				str_replace($str,'',$decrypted,$count);
				if($count > 0){
					$counter++;
				}
			}
			if($counter === 5){
				return array('data'=>$decrypted , 'status'=>true);
			}else{
				return array('data'=>'' , 'status'=>false);
			}
		}
		
		if( isset($_GET['irpul_token']) ){
			$irpul_token 	= $_GET['irpul_token'];
			$decrypted 		= url_decrypt( $irpul_token );
			if($decrypted['status']){
				parse_str($decrypted['data'], $ir_output);
				$tran_id 	= $ir_output['tran_id'];
				$res_num 	= $ir_output['order_id'];
				$amount 	= $ir_output['amount'];
				$refcode	= $ir_output['refcode'];
				$status 	= $ir_output['status'];
				
				if($status == 'paid')	
				{
					$sql = 'SELECT * FROM payment WHERE payment_rand = "'.$res_num.'" LIMIT 1;';
					$payment 	= $db->query($sql)->fetch();
					$amount		= round($payment[payment_amount]);
					$parameters = array(
						'webgate_id'	=> $api,
						'tran_id' 		=> $tran_id,
						'amount'	 	=> $amount,
					);
					try{
						$client = new SoapClient('https://irpul.ir/webservice.php?wsdl' , array('soap_version'=>'SOAP_1_2','cache_wsdl'=>WSDL_CACHE_NONE ,'encoding'=>'UTF-8'));
						$result = $client->PaymentVerification($parameters);
					}catch (Exception $e) { echo 'Error'. $e->getMessage();  }
					$result=intval($result);
					if($result == 1){
						$sql 		= 'SELECT * FROM payment WHERE payment_rand = "'.$res_num.'" LIMIT 1;';
						$payment 	= $db->query($sql)->fetch();
						if ($payment)
						{
							if ($payment[payment_status] == 1)
							{
								$output[status] = 1;
								$output[res_num] = $res_num;
								$output[tran_id] = $tran_id;
								$output[payment_id] = $payment[payment_id];

							}
							else{
								$output[status]	= 0;
								$output[message]= 'چنین سفارشی تعریف نشده است.';
							}
						}
						else{
							$output[status]	= 0;
							$output[message]= 'اطلاعات پرداخت کامل نیست.';
						}
					}
					else{
						$output[status]	= 0;
						$output[message]= 'پرداخت موفقيت آميز نبود';
					}
				}else{
					$output[status]	= 0;
					$output[message]= 'تراکنش پرداخت نشده است !';
				}
			}
			else{
				$output[status]	= 0;
				$output[message]= 'توکن ایرپول صحیح نیست !';
			}
		}else{
			$output[status]	= 0;
			$output[message]= 'توکن ایرپول موجود نیست !';
		}
		return $output;
	}
