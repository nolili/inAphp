<?php
/**
* A simple implementation of Server Product Model for inApp Purchases
* @author Kaloyan K. Tsvetkov <kaloyan@kaloyan.info>
*/

///////////////////////////////////////////////////////////////////////////////

/**
* This is the configuration for the script. Depending on what type of 
* environment you are working with and what type of storage, you need to adjust
* the configuration for the script
*/
Class inAphp_Config {

	/**
	* Whether to use the production environment or the sandbox one
	*/
	Const debug = true;

	// -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
	
	/** Class for handling the MySQL-based storage */
	Const storage_mysql = 'inAphp_storage_mysql';

	/** Class for handling the JSON-based storage */
	Const storage_json = 'inAphp_storage_json';

	/** By default the MySQL storage is used */
	Const storage = self::storage_mysql;
	
	// -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --
	
	/**
	* The connection details for the MySQL, change any details required
	*/
	Const mysql_dsn = 'username=root&password=root&database=inaphp';
	
	/**
	* The file used by the JSON storage
	*/
	Const json_file = './requests.json';

	// -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- --

	/**
	* Live URL for verifying the receipts; use it on production environment
	*/
	Const verifyReceipt_live = 'https://buy.itunes.apple.com/verifyReceipt';

	/**
	* Sandbox URL for verifying the receipts; use it on testing environment
	*/
	Const verifyReceipt_sandbox = 'https://sandbox.itunes.apple.com/verifyReceipt';
	
	////--end-of-class--
	}
	
///////////////////////////////////////////////////////////////////////////////

/**
* The script itself, where the two routines that it performs are put inside
* {@link inApp::check()} and {@link inApp::verify()}
*/
Final Class inAphp {

	/**
	* var inAphp_storage
	*/
	protected $storage;

	/**
	* var string
	*/	
	protected $url;

	/**
	* Constructor: creates the storage and assigns the URL which to use
	*/
	Public Function __construct() {
		
		if (class_exists($storage = inAphp_Config::storage, false)) {
			$this->storage = new $storage;
			}

		$this->url = inAphp_Config::debug
			? inAphp_Config::verifyReceipt_sandbox
			: inAphp_Config::verifyReceipt_live;
		}

	/**
	* Checks against the requests whether the product is active or not
	* (e.g. whether status=1 for the provided productid and udid)
	* @return string either 'YES' or 'NO'
	*/
	Public Function check() {
		
		// storage initialized failed, return negative result
		//
		if (!$this->storage || !$this->storage->open()) {
			return 'NO';
			}
		
		$P = $_POST + array(
			'productid' => null,
			'udid' => null,
			);
		
		$result = $this->storage->active($P['productid'], $P['udid']);
		$this->storage->close();
		
		return $result
			? 'YES'
			: 'NO';
		}

	/**
	* Verify against Apple the data from the receipt
	* @return string either 'YES' or 'NO'
	*/
	Public Function verify() {
		
		$P = $_POST + array(
			'receiptdata' => '',
			);

		$http = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'content' => json_encode(
 					array('receipt-data' => $P['receiptdata'])
 					),
 				'header' => "User-Agent: inAphp",
				)
			));
		
		// unable to place the call
		//
		if (!$fp = @fopen($this->url, 'rb', false, $http)) {
			return 'NO';
			}

		// nothing fetched
		//
		$r = @stream_get_contents($fp);
		fclose($fp);
		if (!$r) {
			return 'NO';
			}
		
		$result = json_decode($r);
		return $result->status == '0'
			? 'YES' 
			: 'NO';
		}

	////--end-of-class--
	}

///////////////////////////////////////////////////////////////////////////////

/**
* Base class for any of the storages
*/
Abstract Class inAphp_storage {

	/** Open\initialize the storage */	
	Abstract Public Function open();
	
	/** Close the storage */	
	Abstract Public Function close();

	/**
	* Checks whether the product (identified by $productid, $udid is active)
	* @param string $productid
	* @param string $udid
	* @return boolean
	*/	
	Abstract Public Function active($productid, $udid);

	////--end-of-class--
	}

///////////////////////////////////////////////////////////////////////////////

/**
* MySQL-based storage; to initialize it run the "mysql.sql" SQL dump
*/
Class inAphp_storage_mysql Extends inAphp_storage {

	/**
	* var resource MySQL connection handler
	*/
	protected $db;

	/** Open\initialize the storage */	
	Public Function open() {
		
		// mysql not enabled ?
		//
		if (!function_exists('mysql_connect')) {
			return false;
			}
		
		$d = array();
		parse_str(inAphp_Config::mysql_dsn, $d);
		$d += array(
			'host' => 'localhost',
			'port' => 3306,
			'username' => '',
			'password' => '',
			'database' => '',
			);
		
		// failed to connect ?
		//
		if (!$this->db = mysql_connect($d['host'], $d['username'], $d['password'])) {
			return false;
			}
			
		// failed to select database ?
		//
		if (!$d['database'] || !mysql_select_db($d['database'], $this->db)) {
			return false;
			}
		
		return true;
		}

	/** Close the storage */	
	Public Function close() {
		mysql_close($this->db);
		return true;
		}

	/**
	* Checks whether the product (identified by $productid, $udid is active)
	* @param string $productid
	* @param string $udid
	* @return boolean
	*/	
	Public Function active($productid, $udid) {
		
		$r = mysql_query($x = 'SELECT `status` FROM `requests` WHERE `productid` = "'
			. mysql_real_escape_string($productid, $this->db) . '" AND `udid` = "'
			. mysql_real_escape_string($udid, $this->db) . '" AND `status` = 1', 
			$this->db);
		
		return mysql_num_rows($r);
		}
	
	////--end-of-class--
	}
	
///////////////////////////////////////////////////////////////////////////////

/**
* JSON-based storage; to initialize it make sure the "requests.json" file is writable and readable.
*/
Class inAphp_storage_json Extends inAphp_storage {
	
	/** @var array */
	protected $data;

	/** Open\initialize the storage */	
	Public Function open() {
		
		// not able to open file
		//
		if (!$fp = fopen(inAphp_Config::json_file, 'r')) {
			return false;
			}
			
		$s = '';
		while ($a = fread($fp, 1024)) {
			$s .= $a;
			}
		fclose($fp);
		
		$this->data = json_decode($s, true);
		return true;
		}

	/** Close the storage */	
	Public Function close() {
		return true;
		}

	/**
	* Checks whether the product (identified by $productid, $udid is active)
	* @param string $productid
	* @param string $udid
	* @return boolean
	*/	
	Public Function active($productid, $udid) {
		
		// element or its status not found ?
		//
		if (!isset($this->data[$productid][$udid]['status'])) {
			return false;
			}

		return $this->data[$productid][$udid]['status'] == 1;
		}

	////--end-of-class--
	}