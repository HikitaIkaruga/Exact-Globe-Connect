<?php
/**
 * Exact Class
 *
 * To class to send data to the exact server and handle the direct response
 *
 * Requirements:
 * Connection to the Exact server
 *
 * @package    Exact Class
 * @author     Rob de Water <robdewater@gmail.com>
 * @version    1.0
 */

class tscToExact {

	/*
	 * Server IP address
	 * @var string
	 */
    private $serverName;

	/*
	 * Server name, in exact case most likely: EXACT_SERVER01\EXACT
	 * @var string
	 */
    private $sqlName;

	/*
	 * Database number, ex: 999
	 * @var string
	 */
    private $databaseName;

	/*
	 * Connection user name
	 * @var string
	 */
    private $userName;

	/*
	 * Connection password, note that exact uses CURLAUTH_NTLM
	 * @var string
	 */
    private $password;

	/*
	 * Length of the account code field. Should be static
	 * @var int
	 */
	private $accountCodeLength = 20;

	/**
	 * Required data, rendered static as example
	 */
	private $staticCustomerData = array(
			"CompanyCode" => "", //A number, ex 999
			"CompanySize" => "UNKNOWN",
			"ContactGender" => "", // M or F
			"CustomerStatus" => "", //ask your Exact people ex: B
			"CustomerType" => "", //ask your Exact people ex: B
			"JobDescription" => "", //String
			"Rating" => "", //Int
			"SectorCode" => "UNKNOWN",
			"Source" => "", //string
			"SourceDescription" => "", //One of the dropdown options
			"PaymentCondition" => "", //One of the options generated by Exact
			"VatCode" => "", //One of the options generated by Exact
			"DebtorCreditorAccount" => "", //Generated by Exact, make sure it matches JournalAccount and it linked to the payment condition
			"ClassificationID" => "" //String
		);

	private $staticOrderLine = array(
			"Journal" => "", //Int, make sure it matches orderData
			"CurrencyCode" => "EUR", //
			"VATCode" => "", //Make sure it matches CustomerVatCode
			"GLAccount" => "", //Should be linked to Journal
		);

	private $staticOrderData = array(
			"Journal" => "", //Int, make sure it matches orderLine
			"JournalAccount" => "%20%20%20%20%20%20%20%20%20%20%20%20%20", //Matches DebtorCreditorAccount, right aligned field so remove spaces as needed
			"CurrencyCode" => "EUR", //
			"Status" => "", //Order status
			"CostCenter" => "", //Which digital wallet it should go into
			"PaymentCondition" => "", //Should match customer paymentcondition
		);

    public function __construct() {
		$this->serverName = ''; //Ex 127.0.0.1:1234
		$this->sqlName = ''; //SQL server name, unchangeable
		$this->databaseName = "";
		$this->userName = 'username';
		$this->password = 'password';
    }

	/**
	 * Send a CURL request to the Exact server
	 *
	 * @param string $serviceLink The URL that forms the query
	 * @param json_encoded string $payload Optional The data to add/update
	 * @param boolean $update Optional Set to true to update
	 * @param boolean $json Optional Set to false to recieve reponse in XML
	 *
	 * @return object on succes, null on failure
	 */
    public function sendToExact($serviceLink, $payload = null, $update = false) {
		$jsonAccept = "Accept: application/json";

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->serverName . $serviceLink);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERPWD, $this->userName . ":" . $this->password);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);

		if ($payload !== null && $update == false) {
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				"ServerName: " . $this->sqlName,
				"DatabaseName: " . $this->databaseName,
				$jsonAccept,
				"Content-Type: application/json"
			));
		} else if ($payload !== null && $update == true) {
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "MERGE");
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				"ServerName: " . $this->sqlName,
				"DatabaseName: " . $this->databaseName,
				$jsonAccept,
				"Content-Type: application/json"
			));
		} else {
			curl_setopt($curl, CURLOPT_HTTPHEADER, array("ServerName: " . $this->sqlName, "DatabaseName: " . $this->databaseName, $jsonAccept));
		}

		$curlResult = json_decode(curl_exec($curl));
        curl_close($curl);

		if ($curlResult === false || $curlResult === null || !is_object($curlResult)) {
			return null;
		}
		if(isset($curlResult->error)) {
			$errorText = "CURL Error. Text given: " . $curlResult->error->message->value;
			if (isset($curlResult->error->innererror->message)) {
				$errorText.= " Type: " . $curlResult->error->innererror->message;
				$errorText.= " Stack error: " . $curlResult->error->innererror->internalexception->message;
			}
			if ($payload !== null) {
				$errorText.= " Payload: " . $payload;
			}
			var_dump($errorText);
			error_log($errorText);
			return null;
		}

		return $curlResult;
    }

	/*
	 * Get customer by account ID
	 *
	 * @param string GUID
	 * @return array on succes or null on failure
	 */
	public function getCustomerByID($ID) {
		$resultRaw = $this->sendToExact('/Services/Exact.Entity.REST.EG/Account(guid\'' . $ID . '\')');

		if ($resultRaw == null) {
			return null;
		}
		if (is_null($resultRaw->d)) {
			return null;
		}
		$result = $resultRaw->d;
		return $result;
	}

	/*
	 * Add a customer to the Exact database
	 *
	 * @param array $customerData Customerdata array. Check documentation for required fields.
	 * @return string ExactGUID Empty string on failure
	 */
    public function addCustomer($customerData) {
		$allData = array_merge($customerData, $this->staticCustomerData);

		$payload = json_encode($allData);

		$resultRaw = $this->sendToExact('/Services/Exact.Entity.REST.EG/Account/', $payload);
		$result = $resultRaw->d;

		if (isset($result->ID)) {
			return $result->ID;
		}
		return "";
    }

	/*
	 * Update the customer data
	 *
	 * @param string Exact GUID
	 * @param array Customer data that needs to be updated
	 * @return boolean true on success, false on failure
	 */
    public function updateCustomer($ID, $customerData) {
		$payload = json_encode($customerData);
		$response = $this->sendToExact('/Services/Exact.Entity.REST.EG/Account(guid\'' . $ID . '\')', $payload, true);

		if(is_null($response)) {
			return true;
		} else {
			return false;
		}
    }

	/*
	 * Adds the customer lines in preperation for inserting the order
	 *
	 * @param array
			"VATBasis" Base amount of products, no taxes
			"VATAmount" Tax amount
			"Description" Link to your other system
			"CostCenter" Can be used to diffrinate between shops or such
	 * @return array with key and entrylines required for the header
	 */
	public function addCustomerOrderLines($orderLines) {
		$resultRaw = $this->sendToExact('/Services/Exact.Entity.REST.EG/FinancialLine/?$top=1&$orderby=ID%20desc');

		if ($resultRaw === null) {
			return array();
		}

		$latestIDNumber = $resultRaw->d->results[0]->ID;

		$entryIds = array();

		$prefix = substr($orderLines["Description"], 0, 3);

		$orderLines["Amount"] = $orderLines["VATBasis"];
		$orderLines["LineNumber"] = "1";
		$orderLines["ID"] = ($latestIDNumber + 1);

		$firstPayload = json_encode(array_merge($orderLines, $this->staticOrderLine));

		$resultRaw = $this->sendToExact('/Services/Exact.Entity.REST.EG/FinancialLine/', $firstPayload);
		$entryIds[] = $resultRaw->d->ID;
		$transactionKey = $resultRaw->d->TransactionKey;

		$returnArray = array(
			"TransactionKey" => $transactionKey,
			"EntryLines" => $this->createEntryLines($entryIds)
		);

		return $returnArray;
	}

	/*
	 * Adds the customer header and actually inserts the order into Exact
	 *
	 * @param array
			"YourReference" Speaks for itself
			"Description" Further description if needed
			"Amount" Total price (products + taxes)
			"DebtorNumber" Debtornumber from Exact Account
			"CostCenter" Can be used to diffrinate between shops or such
			"TransactionKey" Generated by addCustomerOrderLines
			"EntryLines" Generated by addCustomerOrderLines
	 * @return Exact order data or null on failure
	 */
    public function addCustomerOrderHeader($orderData) {
		if (isset($orderData["latestEntry"])) {
			return null;
		}
		$allData = array_merge($orderData, $this->staticOrderData);

		$payload = json_encode($allData);

		$resultRaw = $this->sendToExact('/Services/Exact.Entity.REST.EG/FinancialHeader/', $payload);

		return $resultRaw;
    }

	/*
	 * Get the order in exact by your reference
	 *
	 * @param string your reference
	 * @return array or null on failure
	 */
    public function getCustomerOrder($orderNumber) {
		$resultRaw = $this->sendToExact('/Services/Exact.Entity.REST.EG/FinancialHeader/?$top=1&$filter=YourReference%20eq%20\'' . $orderNumber . '\'');

		if ($resultRaw == null) {
			return null;
		}
		if (is_null($resultRaw->d) || count($resultRaw->d->results) === 0 ) {
			return null;
		}

		return $resultRaw->d->results[0];
    }

	/*
	 * Since there can be only the sql form of '=' be used and
	 * Exact fills certain fields with spaces to the max string length
	 * these spaces lead. This function mimicks that for searching.
	 *
	 * @param string $fieldData
	 * @param int $expectedLength
	 * @return string
	 */
	public function addLeadingSpaces($fieldData, $expectedLength) {
		$leadingSpaces = "";
		for($i = strlen($fieldData); $i < $expectedLength; $i++) {
			$leadingSpaces.= "%20";
		}
		return $leadingSpaces . $fieldData;
	}

	/*
	 * Create the XML like structure for the entry ID's
	 *
	 * @param array IDs
	 * @return string
	 */
	public function createEntryLines($entryIds) {
		$returnString = "<entrylines>";
		foreach($entryIds as $id) {
			$returnString.= "<id>" . $id . "</id>";
		}
		$returnString.= "</entrylines>";
		return $returnString;
	}

	/*
	 * Gets all people with the given payment type
	 * @param string paymenttype
	 * @return array or null on failure
	 */
	public function getAllDebitorsByPaymentType($paymentType) {
		$debitor = array();
		$result = $this->sendToExact('/Services/Exact.Entity.REST.EG/Account/?$filter=PaymentCondition%20eq%20\'' . $paymentType . '\'');

		return $result;
	}
}
