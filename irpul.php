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
	function gateway__irpul($data){
		global $config,$smarty,$db;
		$token = $data[pin] ;
		$amount = $data[amount];
        $redirect = $data[callback];
		$order_id = $data[invoice_id];
		
		$parameters = array(
			'method' 		=> 'payment',
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
		
		$result 	= post_data('https://irpul.ir/ws.php', $parameters, $token );

		if( isset($result['http_code']) ){
			$data =  json_decode($result['data'],true);

			if( isset($data['code']) && $data['code'] === 1){
				
				$update[payment_rand]	= $data['code'];
				$sql = $db->prepare("UPDATE payment SET payment_rand = ? WHERE payment_rand = '$order_id' LIMIT 1");
				//$sql = $db->queryUpdate('payment', $update, 'WHERE payment_rand = "'.$order_id.'" LIMIT 1;');
				//$db->execute($sql);
				$sql->execute(array($data['code'],$order_id));
				$go = $data['url'];
				redirect_to($go);
			}
			else{
				$data[title] = 'خطای سیستم';
				$data[message] = '<font color="red">در ارتباط با درگاه irpul مشکلی به وجود آمده است.</font> شماره خطا: '. $data['code'] . ' ' . $data['status'] .'<br /><a href="index.php" class="button">بازگشت</a>';
				//$smarty->assign('data', $data);
				//$smarty->display('message.tpl');
				return $data;
				exit;
			}
		}else{
			$data[title] = 'خطای سیستم';
			$data[message] = '<font color="red">در ارتباط با درگاه irpul مشکلی به وجود آمده است.</font> پاسخی از سرویس دهنده دریافت نشد. لطفا دوباره تلاش نمائید<br /><a href="index.php" class="button">بازگشت</a>';
			//$smarty->assign('data', $data);
			//$smarty->display('message.tpl');
			return $data;
			exit;
		}
	}

	function callback__irpul($data){
		global $db,$get,$smarty;
		$token = $data[pin];
		
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
				
				if($status == 'paid'){
					$sql = 'SELECT * FROM payment WHERE payment_rand = "'.$res_num.'" LIMIT 1;';
					$payment 	= $db->query($sql)->fetch();
					$amount		= round($payment[payment_amount]);
					$parameters = array(
						'method' 	    => 'verify',
						'trans_id' 		=> $tran_id,
						'amount'	 	=> $amount,
					);
					
					$result =  post_data('https://irpul.ir/ws.php', $parameters, $token );

					if( isset($result['http_code']) ){
						$data =  json_decode($result['data'],true);

						if( isset($data['code']) && $data['code'] === 1){
							//echo "transaction paid. refcode :" .$refcode;
							$sql 		= 'SELECT * FROM payment WHERE payment_rand = "'.$res_num.'" LIMIT 1;';
							$payment 	= $db->query($sql)->fetch();
							if ($payment){
								if ($payment[payment_status] == 1){
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
							$output[message]= 'خطا در پرداخت. کد خطا: ' . $data['code'] . '<br/>' . $data['status'];
						}
						
					}else{
						$output[status]	= 0;
						$output[message]= 'پاسخی از سرویس دهنده دریافت نشد. لطفا دوباره تلاش نمائید';
					}
					
					/*$result=intval($result);
					if($result == 1){*/
						
					/*}
					else{
						$output[status]	= 0;
						$output[message]= 'پرداخت موفقيت آميز نبود';
					}*/
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


	function post_data($url,$params,$token) {
		ini_set('default_socket_timeout', 15);

		$headers = array(
			"Authorization: token= {$token}",
			'Content-type: application/json'
		);

		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($handle, CURLOPT_TIMEOUT, 40);

		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($params) );
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers );

		$response = curl_exec($handle);
		//error_log('curl response1 : '. print_r($response,true));

		$msg='';
		$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));

		$status= true;

		if ($response === false) {
			$curl_errno = curl_errno($handle);
			$curl_error = curl_error($handle);
			$msg .= "Curl error $curl_errno: $curl_error";
			$status = false;
		}

		curl_close($handle);//dont move uppder than curl_errno

		if( $http_code == 200 ){
			$msg .= "Request was successfull";
		}
		else{
			$status = false;
			if ($http_code == 400) {
				$status = true;
			}
			elseif ($http_code == 401) {
				$msg .= "Invalid access token provided";
			}
			elseif ($http_code == 502) {
				$msg .= "Bad Gateway";
			}
			elseif ($http_code >= 500) {// do not wat to DDOS server if something goes wrong
				sleep(2);
			}
		}

		$res['http_code'] 	= $http_code;
		$res['status'] 		= $status;
		$res['msg'] 		= $msg;
		$res['data'] 		= $response;

		if(!$status){
			//error_log(print_r($res,true));
		}
		return $res;
	}
