<?php
// Номер роставьте свой
class Shop_Payment_System_Handler41 extends Shop_Payment_System_Handler 
{
	
	/**
	 * Метод, запускающий выполнение обработчика
	 */
	private $merchant_id='i8349114177';
	private $signature="XFXBKtVRGNKlyappjJUylZGjHFwDQ2zBCK2VH3J";
	private $method='card';

	public $_rub_currency_id = 1;
	private $handler_url = 'http://filo-rosso.ru/shop/cart/';

	function execute()
	{
		parent::execute();

		$this->printNotification();

		return $this;
	}

	/* вычисление суммы товаров заказа */
	public function getSumWithCoeff()
	{
		return Shop_Controller::instance()->round(($this->_rub_currency_id > 0
				&& $this->_shopOrder->shop_currency_id > 0
			? Shop_Controller::instance()->getCurrencyCoefficientInShopCurrency(
				$this->_shopOrder->Shop_Currency,
				Core_Entity::factory('Shop_Currency', $this->_rub_currency_id)
			)
			: 0) * $this->_shopOrder->getAmount() );
	}

	protected function _processOrder()
	{
		parent::_processOrder();

		// Установка XSL-шаблонов в соответствии с настройками в узле структуры
		$this->setXSLs();

		// Отправка писем клиенту и пользователю
		$this->send();

		return $this;
	}

	/* обработка ответа от платёжной системы */
	public function paymentProcessing()
	{	if (isset($_POST['operation_xml'])) {
			$this->ProcessResult();			
			return TRUE;
		}
	}

	function ProcessResult() {
		// Заказ не найден, либо оплачен
		if(is_null($this->_shopOrder) || $this->_shopOrder->paid)
		{
			return FALSE;
		}

		//проверяем есть ли запрос от liqpay
		

		$xml = $_POST['operation_xml'];
		$xml_decoded=base64_decode($xml);

		$res = new SimpleXMLElement($xml_decoded);

		$status=$res->status;


		if ($status=="failure") {
			$this->_shopOrder->system_information = 'покупка отклонена liqpay';
				$this->_shopOrder->save();
				
		}

		if ($status=="wait_secure") {
			$this->_shopOrder->system_information = 'платеж находится на проверке liqpay';
				$this->_shopOrder->save();
				
		}
		$Sum = $this->getSumWithCoeff();
		$xml_old="<request>      
		<version>1.2</version>
		<result_url>$this->handler_url</result_url>
		<server_url>$this->handler_url</server_url>
		<merchant_id>$this->merchant_id</merchant_id>
		<order_id>$this->_shopOrder->invoice</order_id>
		<amount>$Sum</amount>
		<currency>RUR</currency>
		<description>Description</description>
		<default_phone></default_phone>
		<pay_way>$this->method</pay_way> 
		</request>
		";
	
	
		$xml_encoded = base64_encode($xml_old); 
		$lqsignature = base64_encode(sha1($this->signature.$xml_old.$this->signature,1));

		$new_sig = $_POST['signature'];
		$sign=base64_encode(sha1($new_sig.$xml.$new_sig,1)); 
		if ($lqsignature == $sign) {
			$this->_shopOrder->system_information = sprintf("ТОвар оплачен системой liqpay");

					$this->_shopOrder->paid();
					$this->setXSLs();
					$this->send();
		}

		if ($lqsignature != $sign) {
			$this->_shopOrder->system_information = 'Подпись не совпала liqpay';
				$this->_shopOrder->save();
		}
		
	}

	public function getNotification()
	{
		$Sum = $this->getSumWithCoeff();
		$xml="<request>      
		<version>1.2</version>
		<result_url>$this->handler_url</result_url>
		<server_url>$this->handler_url</server_url>
		<merchant_id>$this->merchant_id</merchant_id>
		<order_id>$this->_shopOrder->invoice</order_id>
		<amount>$Sum</amount>
		<currency>RUR</currency>
		<description>Description</description>
		<default_phone></default_phone>
		<pay_way>$this->method</pay_way> 
		</request>
		";
	
	
		$xml_encoded = base64_encode($xml); 
		$lqsignature = base64_encode(sha1($this->signature.$xml.$this->signature,1));
		
		?>
		<h2>Оплата через систему LiqPay</h2>
		
		<form method="POST" action="https://www.liqpay.com/?do=clickNbuy">
		<input type='hidden' name='operation_xml' value='<?php echo $xml_encoded ?>' />
     	<input type='hidden' name='signature' value='<?php echo $lqsignature ?>' />
		<tr>
			<td>
				<table border = "1" cellspacing = "0" width = "400" bgcolor = "#FFFFFF" align = "center" bordercolor = "#000000">
					<tr>
						<td>Сумма, руб.</td>
						<td> <?php echo $Sum?> </td>
					</tr>
					<tr>
						<td>Номер заказа</td>
						<td> <?php echo $this->_shopOrder->invoice?> </td>
					</tr>
				</table>
			</td>
		</tr>
		<table border="0" cellspacing="1" align="center" width="400" bgcolor="#CCCCCC" >
			<tr bgcolor="#FFFFFF">
				<td width="490"></td>
				<td width="48"><input type="submit" name = "BuyButton" value = "Оплатить"></td>
			</tr>
		</table>
		</form>
	<?php
	}

	public function getInvoice()
	{
		return $this->getNotification();
	}
}

