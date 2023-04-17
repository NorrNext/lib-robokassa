<?php
/**
 * @package    Robokassa
 * @author     Dmitrijs Rekuns <support@norrnext.com>
 * @copyright  (C) 2023 NorrNext. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE
 */

namespace NorrNext\Robokassa;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Robokassa interface.
 *
 * @since  1.0
 */
class Robokassa
{
	/**
	 * Parameters delimiter.
	 *
	 * @var    string
	 * @since  1.0
	 */
	const DELIMITER = ':';

	/**
	 * Operation state path.
	 *
	 * @var    string
	 * @since  1.0
	 */
	const PATH_OPSTATE = '/OpStateExt';

	/**
	 * XML interface path.
	 *
	 * @var    string
	 * @since  1.1
	 */
	const PATH_XML = '/Merchant/WebService/Service.asmx';

	/**
	 * Hashing algorithm.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected string $hashingAlgorithm;

	/**
	 * Shop password#1.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected string $password1;

	/**
	 * Shop password#2.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected string $password2;

	/**
	 * Shop ID.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected string $shopId;

	/**
	 * Interface URL.
	 *
	 * @var    string
	 * @since  1.1
	 */
	private string $interfaceUrl = 'https://auth.robokassa.ru';

	/**
	 * Gets the signature for callback requests.
	 *
	 * @param   string   $type       Signature type: success or result
	 * @param   string   $amount     Payment amount.
	 * @param   int      $invoiceId  Invoice id.
	 * @param   ?array   $shopData   Additional custom parameters.
	 *
	 * @return  string
	 * @throws  InvalidArgumentException
	 *
	 * @since   1.0
	 */
	public function getCallbackSignature(string $type, string $amount, int $invoiceId, array $shopData = null): string
	{
		$type = strtolower($type);
		$allowedTypes = ['result', 'success'];

		if (!in_array($type, $allowedTypes))
		{
			throw new InvalidArgumentException('Allowed types: ' . implode(', ', $allowedTypes));
		}

		$password = '';

		if ($type == 'success')
		{
			$password = $this->getPassword1();
		}
		else if ($type == 'result')
		{
			$password = $this->getPassword2();
		}

		$signature = $amount
			. self::DELIMITER . $invoiceId
			. self::DELIMITER . $password;

		if (!empty($shopData))
		{
			$data = '';

			foreach ($shopData as $key => $value)
			{
				$data .= self::DELIMITER . $key . '=' . $value;
			}

			$signature .= mb_substr($data, 0, 2048);
		}

		return strtoupper(hash($this->getHashingAlgorithm(), $signature));
	}

	/**
	 * Gets the invoice state object.
	 *
	 * @param   int  $invoiceId  Invoice Id.
	 *
	 * @return  SimpleXMLElement  SimpleXMLElement on success or false on error
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	public function getOperationState(int $invoiceId): SimpleXMLElement
	{
		$signature = $this->getShopId()
			. self::DELIMITER . $invoiceId
			. self::DELIMITER . $this->getPassword2();

		$url = $this->interfaceUrl . self::PATH_XML . self::PATH_OPSTATE
			. '?MerchantLogin=' . $this->getShopId()
			. '&InvoiceID=' . $invoiceId
			. '&Signature=' . hash($this->getHashingAlgorithm(), $signature);

		return $this->getXML($url);
	}

	/**
	 * Gets the signature (control hash) value.
	 * MerchantLogin:OutSum:InvId:OutSumCurrency:UserIp:Receipt:Password#1:Shp_data
	 *
	 * @param   string   $amount     Amount with two decimal digits.
	 * @param   int      $invoiceId  Invoice id.
	 * @param   ?array   $receipt    Products nomenclature.
	 * @param   ?array   $shopData   Additional custom parameters.
	 * @param   ?string  $currency   Payment currency.
	 * @param   ?string  $userIp     User IP address.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	public function getSignature(string $amount, int $invoiceId = 0, array $receipt = null, array $shopData = null, string $currency = null, string
	$userIp = null): string
	{
		$signature = $this->getShopId() . self::DELIMITER . $amount . self::DELIMITER . $invoiceId;

		if (!empty($currency && in_array(strtoupper($currency), ['USD', 'EUR', 'KZT'])))
		{
			$signature .= self::DELIMITER . strtoupper($currency);
		}

		if (!empty($userIp))
		{
			$signature .= self::DELIMITER . $userIp;
		}

		if (!empty($receipt))
		{
			$signature .= self::DELIMITER . urlencode(json_encode($receipt, JSON_UNESCAPED_UNICODE));
		}

		$signature .= self::DELIMITER . $this->getPassword1();

		if (!empty($shopData))
		{
			$data = '';

			foreach ($shopData as $key => $value)
			{
				$data .= self::DELIMITER . $key . '=' . $value;
			}

			$signature .= mb_substr($data, 0, 2048);
		}

		return hash($this->getHashingAlgorithm(), $signature);
	}

	/**
	 * Sets the country for internal use.
	 * For example is used in the interface URL.
	 *
	 * @param   string  $country  Country code.
	 *
	 * @return  $this
	 *
	 * @since   1.1
	 * @throws  InvalidArgumentException
	 */
	public function setCountry(string $country): Robokassa
	{
		$country = strtolower($country);
		$allowedCountries = ['kz', 'ru'];

		if (!in_array($country, $allowedCountries))
		{
			throw new InvalidArgumentException('Allowed countries: ' . implode(', ', $allowedCountries));
		}

		$this->interfaceUrl = 'https://auth.robokassa.' . $country;

		return $this;
	}

	/**
	 * Sets the hashing algorithm.
	 *
	 * @return  $this
	 *
	 * @since   1.0
	 */
	public function setHashingAlgorithm($algorithm): Robokassa
	{
		$this->hashingAlgorithm = $algorithm;

		return $this;
	}

	/**
	 * Sets the shop password#1.
	 *
	 * @return  $this
	 *
	 * @since   1.0
	 */
	public function setPassword1($password): Robokassa
	{
		$this->password1 = $password;

		return $this;
	}

	/**
	 * Sets the shop password#2.
	 *
	 * @return  $this
	 *
	 * @since   1.0
	 */
	public function setPassword2($password): Robokassa
	{
		$this->password2 = $password;

		return $this;
	}

	/**
	 * Sets the shop ID.
	 *
	 * @return  $this
	 *
	 * @since   1.0
	 */
	public function setShopId($shopId): Robokassa
	{
		$this->shopId = $shopId;

		return $this;
	}

	/**
	 * Gets the hashing algorithm.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getHashingAlgorithm(): string
	{
		return $this->hashingAlgorithm;
	}

	/**
	 * Gets the password#1.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getPassword1(): string
	{
		return $this->password1;
	}

	/**
	 * Gets the password#2.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getPassword2(): string
	{
		return $this->password2;
	}

	/**
	 * Gets the shop ID.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	protected function getShopId(): string
	{
		return $this->shopId;
	}

	/**
	 * Reads XML source.
	 *
	 * @param   string   $data     A well-formed XML string or the path or URL to an XML document if $isUrl is true.
	 * @param   int      $options  Optionally used to specify additional Libxml parameters.
	 * @param   boolean  $isUrl    True to load a file or URL false to load a string.
	 *
	 * @return  SimpleXMLElement  SimpleXMLElement on success or false on error
	 *
	 * @since   1.0
	 * @throws  Exception
	 */
	protected function getXML(string $data, int $options = 0, bool $isUrl = true): SimpleXMLElement
	{
		// Disable libxml errors and allow to fetch error information as needed
		libxml_use_internal_errors(true);

		return new SimpleXMLElement($data, $options, $isUrl);
	}
}
