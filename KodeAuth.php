<?php

/**
 * PHP lib for handling authentication 
 * @author Hussain Shafeeu
 * @version 2011, 2014
 */

class KodeAuth
{
	protected $field_username	=	'username';
	protected $field_password	=	'password';
	protected $users_table		=	'users';
	protected $sessions_table	=	'user_sessions';
	
	protected $sid				=	false;
	
	public $login_error			=	false;
	public $data				= 	array();
	public $authed				=	false;

	public function __construct(pdo $db)
	{
		// Start session if not started already
		if (!isset($_SESSION))
			session_start();
		
		$this->db = $db;
		if (isset($_COOKIE['sid']))
		{
			$st = $db->prepare("SELECT user_id, COUNT(*) as exist 
				FROM {$this->sessions_table} 
				WHERE id = ?"
			);
			
			$st->execute(array($_COOKIE['sid']));
			$session = $st->fetch();
			
			if ($session['exist'] != 0)
			{	
				// Update session data
				$st = $db->prepare("SELECT * FROM {$this->users_table} WHERE id = ?");		
				$st->execute(array($session['user_id']));
				$this->data = $st->fetch(PDO::FETCH_ASSOC);
				
				// Remove password from data array
				unset($this->data['password']);
				
				// Refresh session last active
				$st = $db->prepare("UPDATE 
					{$this->sessions_table} SET last_active = ". time(). " 
					WHERE id = ?"	
				);
				
				$st->execute(array($_COOKIE['sid']));
				$this->authed = true;
				
				$this->updateCookies();
			}
			
			
		}
		
	}

	
	/**
	 * User login method. Fetch password salt for the given username
	 * and then compare with hashed password. Returns true on success
	 * and false otherwise
	 *
	 * @parm string $username Username 
	 * @parm string $password Password
	*/
	public function login($username, $password)
	{
		// Data validation
		if(empty($username)) throw new Exception('You must enter your username.');
		if(empty($password)) throw new Exception('You must enter your password.');
		
		// Check to see if user exists then get his salt and password
		$st = $this->db->prepare("SELECT *
			FROM {$this->users_table}
			WHERE {$this->field_username} = ?"
		);
		
		// If a user exists hash password and compare
		$st->execute(array($username));
		$user = $st->fetch(PDO::FETCH_ASSOC);
		
		// User not found! throw error
		$not_found_error = 'Incorrect Username or Password';
		if (empty($user)) throw new Exception($not_found_error);
			
		// $this_hash = $this->hash($password, $user[$this->field_salt]);

		// // Compare password and hash
		// if ($this_hash != $user[$this->field_password])
		// 	 throw new Exception($not_found_error);

		// Compare password and hash
		if (!password_verify($password, $user[$this->field_password]))
			throw new Exception($not_found_error);

		// Remove password and populate session data
		unset($user[$this->field_password]);
		$this->data = $user;
		
		$this->login_error = false;
		$this->updateCookies();
		
		return true;
		
	}
	
	
	/**
	* Getter function for the authenticated status
	*/
	public function authed()
	{
		return $this->authed;
	}
	
	
	/**
	 * Creates a record in sessions table and creates new cookies. Before
	 * creating new session check for already existing session for user and
	 * de-activates it.
	*/
	protected function updateCookies()
	{
		settype($this->data['id'], "integer");
		
		// check for orphaned records with no cookies
		if (intval($this->data['id']) <= 0)
		{
			throw new exception('User id not defined');
		}
		
		$st = $this->db->prepare("DELETE 
			FROM {$this->sessions_table} 
			WHERE user_id = ?
			LIMIT 1"
		);
		
		// Create a new record
		$st->execute(array($this->data['id']));
		$st = $this->db->prepare("INSERT INTO {$this->sessions_table} 
			(id, user_id, start, last_active, browser, ip, auto_login) VALUES
			(:id, :user_id, :start, :last, :browser, :ip, :auto)"
		);
		
		$this->sid = $this->generateUniqueId();
		$session_data = array(
			'id'		=>		$this->sid,
			'user_id'	=>		$this->data['id'],
			'start'		=>		time(),
			'last'		=>		time(),
			'browser'	=>		$_SERVER['HTTP_USER_AGENT'],
			'ip'		=>		$_SERVER['REMOTE_ADDR'],
			'auto'		=>		0
		);
		
		//pr($session_data);
		
		// Insert new row and set cookies
		$executed = $st->execute($session_data);
		
		if ($executed){
			$this->sid = $session_data['id'];
			setcookie('sid', $this->sid, strtotime('+1 day'), '/');
			
			$this->authed = true;
		}

	}
		

	/**
	* Deletes cookies and session record from database. Dummy
	* logout. Works for now.
	*/
	public function logOut()
	{
		$this->_authed = false;
		
		// Drop current session from db
		$st = $this->db->prepare("DELETE 
			FROM {$this->sessions_table} 
			WHERE user_id = ?
			LIMIT 1"
		);
		
		$st->execute(array($this->data['id']));
		setcookie('sid', '', strtotime('-2 days'), '/');
	}

	
	public function setFlash($class, $msg)
	{
		$_SESSION['flash'] = "<div data-alert class='alert-box $class'>$msg
		<a href='#' class='close'>&times;</a>
                </div>";
	}
	

	public function flash()
	{
		$flash = false;
		if (!empty($_SESSION['flash']))
		{
			$flash = $_SESSION['flash'];
			unset($_SESSION['flash']);
		}
		
		return $flash;
	}
	

	/**
	* Generates a new unique ID which is as random as pi :p
	*/
	public function generateUniqueId()
	{
		return sha1(md5(microtime()) . uniqid() . sha1(pi() * rand()));
	}


}
