<?php
/**
 * User mapping table
 * @package		application/models
 * @copyright	Aconcagua Software Factory
 *
 */
class Table_User extends Zend_Db_Table_Abstract {

	protected $_name    = 'User';
	protected $_primary = 'id';

	const NOT_IDENTITY = 'notIdentity';
	const INVALID_CREDENTIAL = 'invalidCredential';
	const INVALID_USER = 'invalidUser';
	const INVALID_LOGIN = 'invalidLogin';
	
	/**
	 * Default validations messages
	 * @var array
	 */
	protected $_messages = array(
			self::NOT_IDENTITY	=> "Not existent identity. A record with the supplied identity could not be found.",
			self::INVALID_CREDENTIAL => "Invalid credential. Supplied credential is invalid.",
			self::INVALID_USER => "Invalid User. Supplied credential is invalid",
			self::INVALID_LOGIN => "Invalid Login. Fields are empty"
	);

	
	/**
	 * Assigns a message to a message key in an associative array of messages.
	 * @param string $messageString
	 * @param string $messageKey	OPTIONAL
	 * @return UserModel
	 * @throws Exception
	*/
	public function setMessage($messageString, $messageKey = null)
	{
		if ($messageKey === null) {
			$keys = array_keys($this->_messages);
			$messageKey = current($keys);
		}
		if (!isset($this->_messages[$messageKey])) {
			throw new Exception("No message exists for key '$messageKey'");
		}
		$this->_messages[$messageKey] = $messageString;
		return $this;
	}
	
	/**
	 * Add an array of messages.
	 * @param array $messages
	 * @return UserModel
	 */
	public function setMessages(array $messages)
	{
		foreach ($messages as $key => $message) {
			$this->setMessage($message, $key);
		}
		return $this;
	}
	
	/**
	 * Authenticates the user
	 * @param string $nick - User's name
	 * @param string $password - User's password
	 * @throws Exception
	 * @return UsuarioModel
	 */
	public function login($nick, $password)
	{
		
		if(!empty($nick) && !empty($password))
		{
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();;
			$autAdapter = new Zend_Auth_Adapter_DbTable(
					$db,
					'UserInfo', // is a view with extra user information
					'usrName',
					'usrPassword',
					'? AND usrActive = 1'
			);			
			$autAdapter->setIdentity($nick)->setCredential( md5($password) );
			
			$aut = Zend_Auth::getInstance();
			
			$result = $aut->authenticate($autAdapter);
			switch ($result->getCode())
			{
				case Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND:
					throw new Exception($this->_messages[self::NOT_IDENTITY]);
					break;
				case Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
					throw new Exception($this->_messages[self::INVALID_CREDENTIAL]);
					break;
				case Zend_Auth_Result::SUCCESS:
					if ($result->isValid()) {
						$data = $autAdapter->getResultRowObject();
						$aut->getStorage()->write($data);
					} else {
						throw new Exception($this->_messages[self::INVALID_USER]);
					}
					break;
				default:
					throw new Exception($this->_messages[self::INVALID_LOGIN]);
					break;
			}
		} else {
			throw new Exception($this->_messages[self::INVALID_LOGIN]);
		}
		return $this;
	}
	/**
	 * Deletes the session, logout the user
	 * @return UsuarioModel
	 */
	public function logout()
	{
		Zend_Auth::getInstance()->clearIdentity();
		$usersNs = new Zend_Session_NameSpace("members");
		$usersNs->unsetAll();
                session_unset();
                unset($_SESSION['Zend_Auth']);
                session_destroy();
                session_write_close();
                setcookie(session_name(),'',0,'/');
                session_regenerate_id(true);
		return $this;
	}
	/**
	 * Gets the user information logged
	 * @return mixed if you are logged you get a Zend_Auth object with user information, otherwise null
	 */
	public static function getIdentity()
	{
		$auth = Zend_Auth::getInstance();
		if ($auth->hasIdentity()) {
			return $auth->getIdentity();
		}
		return null;
	}
	/**
	 * Check if you are logged
	 * @return boolean
	 */
	public static function isLoggedIn()
	{
		return Zend_Auth::getInstance()->hasIdentity();
	}	
	
	/**
	 * You get users to list
	 * @param string $where
	 * @return puntero
	 */
	public function getUsuarios($where = null, $sort=array('columna'=>1, 'direccion'=>'ASC'), $limit = array('limit'=>-1, 'offset'=>0), $sSearch='')
	{
		try {
			//parameters validations
			$where       = is_null($where) ? 'AND 1=1 ' : $where;
	
			if( !is_array($sort) || !is_array($limit) || !is_string($sSearch) || !is_string($where)){ // verifico que los argumentos sean validos
				throw new Exception('Invalid parameters');
			} else {
				if( !array_key_exists('columna', $sort) || !array_key_exists('direccion', $sort) || !array_key_exists('limit', $limit) || !array_key_exists('offset', $limit) ){
					throw new Exception('Invalid parameters');
				}
			}
			$columsSortable = array();
			$columsSortable[1] = "LOWER(perName)";
			$columsSortable[2] = "LOWER(usrName)";
			$columsSortable[3] = "LOWER(case when usrActive = 1 then 'Yes' else 'No' end)";
			
			
	
			if(!empty($sSearch)){
				$implode_array_d1 = implode(',', $columsSortable);
				$implode_array_d2 = implode(',', array_reverse($columsSortable));
				$sSearch = str_replace(' ', '%', $sSearch);
				$sSearch = strtolower($sSearch);
				$concat_ws = "CONCAT_WS(' ',".$implode_array_d1 .','. $implode_array_d2 .") LIKE '%$sSearch%'" ;
				$where .= ' AND '.$concat_ws;
			}
				
			$sSort	= $columsSortable[$sort['columna']] . ' ' . $sort['direccion'];
	
			$sql         = "SELECT r0.id, r0.id, r0.perName, r0.usrName, r0.roleName , CASE WHEN usrActive = 1 THEN 'Yes' ELSE 'No' end usrActive FROM UserInfo r0	WHERE 1=1  $where ";
			
			$cache = Zend_Registry::get('cache');
			if ( ($totalCount = $cache->load('getUsuarios')) === false )
			{
				$sqlCount = "SELECT count(1) as total
									From UserInfo r0
									WHERE 1=1 ";
				$res = $this->getDefaultAdapter()->fetchRow($sqlCount);
				$totalCount = $res['total'];
				$cache->save($totalCount);
			}
	
			$sqlCountWhere = "SELECT count(1) as total
			From UserInfo r0
			WHERE 1=1 $where ";
			$res = $this->getDefaultAdapter()->fetchRow($sqlCountWhere);
			$totalCountWhere = $res['total'];
	
			$pag = $limit['limit'];
			$start = $limit['offset'];
			if($pag == -1 || $pag == '-1' ){
				$sql .= $where. " ORDER BY $sSort";
			} else {
				$sql .= $where. " ORDER BY $sSort LIMIT $pag OFFSET $start";
			}

			$rs = array('cursor'=>$this->getDefaultAdapter()->query($sql) , 'count'=>$totalCount, 'countWhere'=>$totalCountWhere );
			return $rs;
		} catch (Exception $e){
			throw new Exception( $e->getMessage() );
		}
	}	
}