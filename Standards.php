<?php 
/*
Copyright (c) 2016 Jeremy Frank(https://github.com/AskingQuestions)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

This is the official Standard websom module. 
The Standard module is used by modules to provide a interface(functions, objects, tools, ect...) for other modules that follows rules defined by this module.

For more information and standards go to http://www.echorial.com/websom/projects/standard/wiki/
*/

function Standards_Status() {
	Standards::init();
	
	return true;
}

function Standards_Info() {
	return [
		'major' => Standards::$major,
		'minor' => Standards::$minor,
		'description' => 'Standards is used by modules to provide a interface(functions, objects, tools, ect...) for other modules that follows rules defined by this module.',
		'website' => 'http://www.echorial.com/websom/projects/standard/wiki/',
		'author' => 'AskingQuestions(https://github.com/AskingQuestions)',
		'license' => 'https://en.wikipedia.org/wiki/MIT_License'
	];
}

/**
* \defgroup Standard Standard
*/

/**
* \defgroup StandardsList Standards List
*
* This is a list of current official standards.
*/

/**
* \ingroup Standard
* 
* This is the standard interface class, used for getting Standard(s)
*/
class Standards {
	/**
	* The global event interface. See Standard_Event_Interface for info.
	*/
	static public $events;
	
	/**
	* The currently installed Standards version.
	*/
	static public $version = '1.0';
	
	static public $major = '1';
	static public $minor = '0';
	
	/// \cond
	
	static public $eventSystem;
	
	static public function init() {
		self::$standards = [];
		self::$eventSystem = new Standard_Event_System();
		self::$events = new Standard_Event_Interface();
		self::$events->eSystem = self::$eventSystem;
		define(STANDARD_USER_SYSTEM_USERNAME_TAKEN, 2);
		define(STANDARD_USER_SYSTEM_EMAIL_TAKEN, 1);
		
		define(STANDARD_USER_SYSTEM_EMAIL_CHANGE, 3);
		define(STANDARD_USER_SYSTEM_PASSWORD_RESET, 1);
	}
	
	static private $reference = [];
	
	/// \endcond
	
	static private $standards = [];
	
	/**
	* This will register a standard instance with the global Standards.
	* 
	* See the tutorial for making a Standard
	*/
	static public function registerStandard(Standard $standard) {
		if (self::standard($standard->type) === false) {
			$standard->enabled = true;
			self::$reference[$standard->short] = $standard->type;
		}
		self::$standards[] = $standard;
	}
	
	/**
	* This will get a standard if it is installed.
	* 
	* @param string $type The standard type. See StandardsList for a list.
	* 
	* @return The found Standard instance or false if not found.
	*/
	static public function standard($type) {
		foreach (self::$standards as $s) {
			if ($type == $s->type AND $s->enabled)
				return $s;
		}
		return false;
	}
	
	/**
	* This will return a string containing the $errorMsg and any html structure around it.
	* 
	* Use this to standardize the error message look.
	*/
	static public function error($errorMsg) {
		$e = Theme::container("Error<hr>".$errorMsg, "error");
		Theme::tell($e, 4, "error");
		return $e->get();
	}
	
	/**
	* This will try to find the entity associated with the location provided.
	*
	* -- How locations work --
	* Locations are strings that contain a path to a certain piece of information in the database.
	* Example: "Forum.Post.#65"
	* Breakdown:
	* The "Forum" part is looking in the installed Forum systems data tables.
	* The "Post" part is telling Standard to search inside of the posts table.
	* The "#65" part is looking for the post that has an id of 65.
	*
	* You can also use "Forum.Post.<time|-90" This will get 90 forum posts going from newest to oldest.
	*
	* If you are using user input to search use:
	* \code
	* 
	* $ents = Standards::findEntities("ForumSystem.Post", [ "<" => "time", "|%" => ["title", $userInput] ]); //This is faster and safer.
	* //Same results as.
	* $ents = Standards::findEntities("ForumSystem.Post.<time|%title/".$userInput);
	* 
	* \endcode
	*
	* @param string $location The location where a entity is stored.
	*
	* @return An array of found entities or false if the query is invalid.
	*/
	static public function findEntities($location, $data = false) {
		//Parse the location.
		$qData = [];
		$split = explode(".", $location);
		$pd = self::getQueryData($split[0], $split[1]);
		if ($data === false) {	
			$qData = self::parseToken($pd["tokens"], $split[2]);
			if ($qData === false)
				return false;
		}else{
			$qData = $data;
		}
		
		$finder = self::buildFinder($pd["tokens"], $qData);
		
		$found = Data_Select($pd["table"], $finder);
		
		$rtn = [];
		foreach ($found as $key => $value) {
			$path = $pd["standard"]::iShort.".".$pd["entity"]::iShort.".".$value["id"];
			$obj = new $pd["entity"]($value, $pd["table"], $location, $path, $value["id"]);
			array_push($rtn, $obj);
		}
		
		return $rtn;
	}
	
	/**
	* This will create a path from the trail.
	* 
	* @param string $trail The location of an entity.
	* 
	* @return A short string that contains the path to the trail or false if trail is invalid.
	*/
	static function toPath($trail) {
		$td = self::getTrailData($trail);
		
		if ($td === false)
			return false;
		
		return $td["standard"]->short.".".$td["entity"]::iShort.".".$td["id"];
	}

	/**
	* This will create a trail from the path.
	* 
	* @param string $path The short location of an entity.
	* 
	* @return A long readable string that contains the trail or false if the path is invalid.
	*/
	static function toTrail($path) {
		$pd = self::getPathData($path);
		
		if ($pd === false)
			return false;
		
		return $pd["trail"];
	}
	
	/**
	* This will get an entity from the path.
	* 
	* @param string $path The short location of an entity.
	* 
	* @return An entity loaded with the data found or false if not found.
	*/
	static function fromPath($path) {
		$pd = self::getPathData($path);
		
		if ($pd === false)
			return false;
		
		$finder = new Data_Finder();
		$finder->where("", "id", "=", $pd["id"]);
		
		$found = Data_Select($pd["table"], $finder);
		
		if (count($found) == 0)
			return false;
		
		$ent = new $pd["entity"]($found[0], $pd["table"], $path, $pd["trail"], $pd["id"], $path);
		return $ent;
	}
	
	/// \cond
	
	static private function buildFinder($tokens, $query) {
		$finder = new Data_Finder();
		
		
		foreach ($query as $tok => $v) {
			if (!isset($v))
				throw new Exception("Standards findEntities 2nd param must be an associative array.");
			
			$mixs = [
				"&" => "AND",
				"!" => "NOT",
				"|" => "OR"
			];
			$tkr = $tok;
			$mix = "";
			if (strlen($tok) == 2) {
				$tkr = substr($tok, 1, 2);
				$mix = $mixs[substr($tok, 0, 1)];
			}
			
			call_user_func_array($tokens[$tkr]["find"], [$finder, $v, $mix]);
		}
		
		return $finder;
	}
	
	static private function getPathData($path) {
		$split = explode(".", $path);
		if (count($split) != 3)
			return false;
		$id = intval($split[2]);
		
		$type;
		if (isset(self::$reference[$split[0]])) {
			$type = self::standard(self::$reference[$split[0]]);
		}else{
			return false;
		}
		
		if (!isset($type->entities[$split[1]]))
			return false;
		$ent = $type->entities[$split[1]];
		
		$table = $ent::$globalTable;
		
		$trail = $type->type.".".$ent::iType.".".$id;
		
		return [
			"entity" => $ent,
			"table" => $table,
			"trail" => $trail,
			"id" => $id,
			"standard" => $type
		];
	}
	
	static private function getTrailData($trail) {
		$split = explode(".", $trail);
		if (count($split) != 3)
			return false;
		
		$tStandard = self::standard($split[0]);
		if ($tStandard === false)
			return false;
		
		$className = get_class($tStandard);
		
		if (constant($className."::".$split[1]) === null)
			return false;
		
		return [
			"standard" => $tStandard,
			"entity" => constant($className."::".$split[1]),
			"id" => intval($split[2])
		];
	}
	
	static private function getQueryData($root, $sub) {
		$stand = self::standard($root);
		if ($stand === false)
			return false;
		
		$cons = constant(get_class($stand)."::".$sub);
		if ($cons === null)
			return false;
		
		return [
			"tokens" => $cons::$globalQuery,
			"table" => $cons::$globalTable,
			"entity" => $cons,
			"standard" => $stand
		];
	}
	
	static private function explodr($string) {
		if($matches = preg_split('/[\s|&!]+/i', $string, null, PREG_SPLIT_OFFSET_CAPTURE)){
			$return = array();
			foreach ($matches as $match) {
				$return[] = (($match[1]-1) >= 0) ? substr($string, $match[1]-1, 1).$match[0] : $match[0];
			}
			return $return;
		} else {
			return $string;
		}
	}
	
	static private function parseToken($tokens, $str) {
		$split = self::explodr($str);
		$rtn = [];
		
		foreach ($split as $ind => $st) {
			$tkn = 0;
			if (preg_match("/&|!|\|/i", $st) === 1)
				$tkn = 1;
			
			$token = substr($st, 0, $tkn+1);
			$value = substr($st, 1);
			
			$tok = $tokens[$token];
			
			$compVal;
			if ($tok["type"] == "string") {
				$compVal = $value;
			}else if ($tok["type"] == "int") {
				if (!is_numeric($value))
					return false;
				$compVal = intval($value);
			}else if ($tok["type"] == "float") {
				if (!is_numeric($value))
					return false;
				$compVal = floatval($value);
			}else if ($tok["type"] == "pair") {
				if (strpos($value, "/") === false)
					return false;
				$sp = explode("/", $value);
				$compVal = [$sp[0], $sp[1]];
			}
			
			$rtn[$token] = $compVal;
		}
		return $rtn;
	}
	
	/// \endcond
	
	static public function MarkdownToolkit() {
		return self::standard("MarkdownToolkit");
	}
	
	static public function UserSystem() {
		return self::standard("UserSystem");
	}
	
	static public function ForumSystem() {
		return self::standard("ForumSystem");
	}
	
	static public function NotificationSystem() {
		return self::standard("NotificationSystem");
	}
	
	static public function CommentSystem() {
		return self::standard("CommentSystem");
	}
}

/**
* \ingroup Standard
* 
* This is the template class for creating Standards.
* 
* See the tutorials for info on how to create one.
*/
class Standard {
	
	/**
	* This is an array of entities with the key as the short and value as the class name.
	* Note: This is automatically filled in.
	*/
	public $entities = [];
	
	/**
	* The is the type of Standard this is.
	*/
	public $type = "";
	
	/**
	* The current version of the standard this is. Do not change this.
	*/
	public $version = "";
	
	/**
	* The short link for paths.
	*/
	public $short = "";
	
	/**
	* The standard(type) version this is compatiple with.
	*/
	public $standardVersion = "1.0";
	
	/**
	* The latest version of standards this is compatible with.
	*/
	public $standardsVerison = "1.0";
	
	/**
	* The detail level of the this standard.
	*/
	public $level = 1;
	
	/**
	* This is whether or not this standard instance is enabled and in use.
	* 
	* Note: Do not change this. It is automatically set by Standards.
	*/
	public $enabled = false;
	
	/**
	* This will get an event based on its path.
	*
	* @param string $path The path to find the event at.
	* 
	* @return Standard_Event_Interface instance if found or false if not.
	*/
	public function event($path) {
		$rtn = new Standard_Event_Interface();
		$rtn->eSystem = Standards::$eventSystem;
		$rtn->event = $this->type.".".$path;
		return $rtn;
	}
	
	/**
	* The class name of this standard.
	*/
	public $className;
	
	final function __construct() {
		$class = get_class($this);
		$this->className = $class;
		$this->type = constant($class."::iType");
		$this->short = constant($class."::iShort");
		$this->version = constant($class."::iVersion");
		$ents = constant($class."::iEntities");
		
		foreach ($ents as $ent) {
			$entClass = constant($class."::".$ent);
			$this->entities[$entClass::iShort] = $entClass;
		}
		
		$this->init();
	}
	
	protected function init() {
		
	}
	
	/**
	* This will return a new empty entity based on the $name.
	* 
	* @param string $name The name of the standard entity.
	* 
	* @return This will return a new entity instance or false if not found.
	*/
	public function entity($name) {
		$cons = constant($this->className."::".$name);
		if ($cons !== null) {
			$rtn = new $cons();
			return $rtn;
		}
		return false;
	}
}

class Standard_Event_Interface {
	/// \cond
	
	public $eSystem;
	public $event = "";
	
	/// \endcond
	
	public $officialEvents = [
		"ForumSystem.thread.create" => [
			"description" => "When a forum thread is about to be created",
			"params" => [
				"forum" => "A forum entity that the thread was posted under.",
				"threadData" => "An associative array containing the thread title, body, user, ect..."
			],
			"admin" => false,
			"cancel" => true
		],
		"ForumSystem.thread.created" => [
			"description" => "When a forum thread was created.",
			"params" => [
				"forum" => "A forum entity that the thread was posted under.",
				"thread" => "The ForumSystem::Thread entity that was created."
			],
			"admin" => false,
			"cancel" => false
		],
		"UserSystem.user.register" => [
			"description" => "When a user is about to be created.",
			"params" => [
				"userData" => "An associative array of userData."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.registered" => [
			"description" => "When a user is created.",
			"params" => [
				"user" => "The UserSystem::User entity that was created"
			],
			"admin" => true,
			"cancel" => false
		],
		"UserSystem.user.emailChange" => [
			"description" => "When a user requests an email change. Before the email confirmation is sent.",
			"params" => [
				"user" => "The UserSystem::User entity that is requesting an email change.",
				"email" => "A string containg the new email."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.emailChanged" => [
			"description" => "When the user email is set to the new email they requested. After the email is confirmed.",
			"params" => [
				"user" => "The UserSystem::User entity that changed their email."
			],
			"admin" => true,
			"cancel" => false
		],
		"UserSystem.user.passwordChange" => [
			"description" => "When a user password is about to be changed.",
			"params" => [
				"user" => "The UserSystem::User entity.",
				"newPassword" => "The new password."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.passwordResetRequest" => [
			"description" => "When a user that is not signed in is requesting a password reset sent to an account email.",
			"params" => [
				"user" => "The UserSystem::User entity that was found."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.passwordReset" => [
			"description" => "Before a user sets their password from a password reset request.",
			"params" => [
				"user" => "The UserSystem::User entity that is requesting a password reset.",
				"newPassword" => "The new password."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.loginGot" => [
			"description" => "Before login info is checked against the database.",
			"params" => [
				"login" => "The username/email used to login",
				"password" => "A string containg the user's password."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.loginCheck" => [
			"description" => "Before the user is logged in.",
			"params" => [
				"userData" => "The raw user data."
			],
			"admin" => true,
			"cancel" => true
		],
		"UserSystem.user.logout" => [
			"description" => "When a user is logging out.",
			"params" => [
				"user" => "The user entity that is logging out."
			],
			"admin" => true,
			"cancel" => true
		]
		
		
	];
	
	/**
	* This will listen on this event where the $params match.
	* 
	* 
	* Example:
	* \code
	* Standards::ForumSystem()->event("thread.create")->on(//Listen to the thread.create event for ForumSystem
	* 		["forum" => function ($eventName, $forumEntity) { //Check the "forum" param with a function that looks for a "News" forum
	* 			if ($forumEntity->getName() == "News")
	* 				return true;
	* 			return false;
	* 		}],
	* 		function ($eventName, $params) {
	* 			//This code will be called when the event and params are a match.
	* 			return false; //This wll cancel this event.
	* 		}
	*  );
	* \endcode
	*/
	public function on($params = [], callable $call) {
		$this->eSystem->addListener($this->event, $params, $call);
	}
	
	/**
	* This will invoke this event with the given $params
	* 
	* @return true if the event should be canceled or false if not.
	*/
	public function invoke($params = []) {
		return $this->eSystem->invoke($this->event, $params);
	}
}

/// \cond

class Standard_Event_System {
	
	public $listeners = [];
	
	public function invoke($event, $params) {
		$split = explode(".", "main.".$event);
		if (!isset($this->listeners["subs"]))
			return false;
		
		$calls = self::findEvents($this->listeners["subs"], $split);
		$shouldCancel = false;
		
		foreach ($calls as $c) {
			if (self::check($event, $params, $c[0]))
				if (call_user_func_array($c[1], [$event, $params]) === false)
					$shouldCancel = true;
		}
		
		return $shouldCancel;
	}
	
	static public function findEvents(&$currentLevel, $events, $i = 0) {
		$rtn = [];
		
		foreach ($currentLevel[$events[$i]]["listeners"]["all"] as $cur) {
			$rtn[] = $cur;
		}
		
		if ($i+1 == count($events)) {
			foreach ($currentLevel[$events[$i]]["listeners"]["this"] as $cur) {
				$rtn[] = $cur;
			}
			return $rtn;
		}
		
		if (isset($currentLevel[$events[$i]])) {
			$rtn = array_merge($rtn, self::findEvents($currentLevel[$events[$i]]["subs"], $events, $i+1));
		}
		
		return $rtn;
	}
	
	static public function eventTree(&$currentLevel, $events, $params, $call, $i = 0) {
		$currentKey = $events[$i];
		$c = count($events);
		if ($currentKey != "*" AND !isset($currentLevel["subs"][$currentKey])) {
			
			$currentLevel["subs"][$currentKey] = ["subs" => [], "listeners" => ["all" => [], "this" => []]];
		}
		
		if ($i+1 == $c) {
			$typ = "this";
			if ($currentKey == "*") {
				$typ = "all";
				$currentLevel["listeners"][$typ][] = [$params, $call];
			}else{
				$currentLevel["subs"][$currentKey]["listeners"][$typ][] = [$params, $call];
			}
		}else{
			self::eventTree($currentLevel["subs"][$currentKey], $events, $params, $call, $i+1);
		}
	}
	
	public function addListener($event, $params, $call) {
		$split = explode(".", "main.".$event);
		self::eventTree($this->listeners, $split, $params, $call);
	}
	
	static private function check($event, $params, $against) {
		$rtn = true;
		foreach ($params as $p => $v) {
			if (isset($against[$p])) {
				if (call_user_func_array($against[$p], [$event, $v]) === false)
					$rtn = false;
			}
		}
		return $rtn;
	}
}

/// \endcond

/**
* \ingroup Standard
* 
* This class is extended by views that represent an entity.
*/
class Entity_View extends View {
	/// Use this to inject into the view instance.
	public $inject;
	
	/// This is the view level.
	public $level;
	
	/*function __construct(Injections $overrideInjections = null, $level = 1) {
		$this->inject = $overrideInjections;
		if ($this->inject === null) {
			$this->inject = new Injections();
		}
		$this->level = $level;
	}*/
}

/**
* \ingroup Standard
* 
* Events: 
*  - "inject"(Injections $inject(The view injections)): Use this to inject into the entity view.
*/
class Entity extends Hookable {
	/// \cond
	protected $_old_data;
	/// \endcond
	
	/**
	* The type of entity this is. Do not change this.
	*/
	public $type;
	
	/**
	* The short link path. Do not change this.
	*/
	public $short;
	
	/**
	* The standard this entity is following. Do not change this.
	*/
	public $standard;
	
	/**
	* The level this entity follows. Do not change this.
	*/
	public $level;
	
	/**
	* The current raw entity data taken from the database.
	*/
	protected $_data;
	
	/**
	* The standard query used to find this entity.
	*/
	protected $_location;
	
	/**
	* The table this entity is stored in.
	*/
	protected $_table;
	
	/**
	* If this entity has no database location yet.
	*/
	protected $_is_anonymous = true;
	
	/**
	* The table index that this entity is stored at.
	*/
	protected $_id;
	
	/**
	* The smallest link to this entity;
	*/
	protected $_path;
	
	/**
	* Override this.
	* 
	* This is the raw data template. 
	* 
	* It is used by the entity when creating new default entities.
	* Example:
	* \code
	* static public $template = [ "id" => -1, "name" => "Default name", "body" => "Put body text here" ];
	* \endcode
	*/
	static public $template = [];
	
	/**
	* Override this to set the object values.
	* 
	* You can use $this.
	*/
	public function builtTemplate() {
		
	}
	
	/**
	* Override Entity::init() if you wish to use the constructor.
	*/
	final function __construct($data = false, $table = false, $location = false, $path = false, $id = false) {
		$class = get_class($this);
		if ($data === false AND $table === false AND $location === false AND $path === false AND $id === false) {
			$this->_is_anonymous = true;
			$this->_data = $class::$template;
			$this->_table = $class::$globalTable;
			$this->builtTemplate();
		}else{
			$this->_is_anonymous = false;
			$this->_data = $data;
			$this->_table = $table;
			$this->_location = $location;
			$this->_path = $path;
			$this->_id = $id;
		}
		
		$this->_old_data = $this->_data;
		
		
		$this->type = constant($class."::iType");
		$this->short = constant($class."::iShort");
		$this->standard = constant($class."::iParent");
		$this->init();
	}
	
	public function getPath() {
		return $this->_path;
	}
	
	/**
	* This is called after the constructor.
	* You can override this.
	*/
	protected function init() {
		
	}
	
	/**
	* This will save the contents of the entity to its location in the database.
	*
	* @param bool $force If all values(including unchanged) of the entity should be updated in the database.
	*/
	public function save($force = false) {
		$builder = new Data_Builder();
		
		if ($this->_is_anonymous) {
			foreach ($this->_data as $key => $value) {
				$builder->add($key, $value);
			}
			
			$id = Data_Insert($this->_table, $builder);
			if ($id !== false) {
				$this->_is_anonymous = false;
				$this->_id = $id;
				$par = $this::iParent;
				$this->_path = $par::iShort.".".$this::iShort.".".$id;
				return true;
			}
			return false;
		}
		
		$changed = false;
		foreach ($this->_data as $key => $value) {
			if ($value != $this->_old_data[$key] || $force) {
				$builder->add($key, $value);
				$changed = true;
			}
		}
		
		if (!$changed) return false;
		
		Data_Update($this->_table, $builder, Quick_Find([["id", "=", $this->_id]]));
		
		$this->_old_data = $this->_data;
		
		$this->event("save", []);
		
		return true;
	}
	
	/**
	* This will get a view instance for this entity.
	* @return A view instance for this entity.
	*/
	public function view(Injections $overrideInjections = null, $level = 1) {
		$injections;
		if ($overrideInjections === null) {
			$injections = new Injections();
		}else{
			$injections = $overrideInjections;
		}
		
		$this->event("inject", [$injections]);
		$v = $this->getRawView($injections, $level);
		return $v;
	}
	
	/**
	* Get this entity's html.
	* 
	* @param Injections $overrideInjections Set this to an injections instance if you wish to inject things into the view.
	* 
	* @return A string containing the entity display.
	*/
	public function get(Injections $overrideInjections = null, $level = 1) {
		$v = $this->view($overrideInjections, $level);
		return $v->buildSub($this->_data);
	}
}

/*--------------------------------UserSystem--------------------------------*/ 

const STANDARD_USER_SYSTEM_EMAIL_CHANGE = 2;
const STANDARD_USER_SYSTEM_EMAIL_TAKEN = 4;
const STANDARD_USER_SYSTEM_USERNAME_TAKEN = 8;
const STANDARD_USER_SYSTEM_PASSWORD_RESET = 16;
const STANDARD_USER_SYSTEM_ACCOUNT_NOT_CONFIRMED = 32;

/**
* \defgroup UserSystem User System
* 
* Standard id: UserSystem
* 
* This is a simple user management standard that features permissions, users, and more.
*/

/**
* \ingroup UserSystem
* 
* \brief The global UserSystem instance.
* 
* Global constants:
* 	- STANDARD_USER_SYSTEM_EMAIL_TAKEN
* 	- STANDARD_USER_SYSTEM_USERNAME_TAKEN
* 	- STANDARD_USER_SYSTEM_PASSWORD_RESET
* 	- STANDARD_USER_SYSTEM_EMAIL_CHANGE
* 
* Base config:
* 	- "int maxUsernameLength"(Default: 24): Maximum number of characters a username can have.
* 	- "int minUsernameLength"(Default: 2): Minimum number of characters a username can have.
* 	- "int maxPasswordLength"(Default: 256): Maximum number of characters a password can have.
* 	- "int minPasswordLength"(Default: 8): Minimum number of characters a password can have.
*/
interface iUserSystem {
	const iType = "UserSystem";
	const iShort = "us";
	const iVersion = "1.0";
	
	/**
	* Must have User constant and $User static member.
	*/
	const iEntities = [
		"User"
	];
	
	/**
	* Must have all constants and $[Widget name] variables.
	*/
	const iWidgets = [
		"Login",
		"Logout",
		"Register",
		"Group",
		"Perms",
		"ChangePassword",
		"ChangeEmail",
		"ResetPassword"
	];
	
	/**
	* @return true if a client is logged into a user, or false if not.
	*/
	public function isLoggedIn();
	
	/**
	* @return The current logged in user entity or false if not.
	*/
	public function getLoggedIn();
	
	/**
	* This is a fast(compute) way to check if a password is correct.
	* 
	* @return False if password is wrong or raw user data if success.
	*/
	public function checkPassword($handle, $password);
	
	/**
	* This will check if the handle and password match if so it will log the user in
	* 
	* @return True if correct or false if not.
	*/
	public function login($handle, $password);
	
	/**
	* This will logout the currently logged in user.
	*/
	public function logout();
	
	/**
	* Tries to register a new user with the given information.
	* 
	* @return True, false, or an error id deppending on if the user was created.
	* 
	* Error ids:
	* 	- STANDARD_USER_SYSTEM_USERNAME_TAKEN: If the user name was taken.
	* 	- STANDARD_USER_SYSTEM_EMAIL_TAKEN: If the email is already used.
	*/
	public function registerUser($username, $email, $password, $needsConfirm = true);
	
	/**
	* Creates a group with the groupName and the permissions
	* 
	* @return True on success or false on failure.
	*/
	public function createGroup($groupName, $permissions);
	
	/**
	* Removes users in the groupName group and then removes the group.
	* 
	* @return True on success or false on failure.
	*/
	public function removeGroup($groupName);
	
	/**
	* Sets the permissions to the groupName.
	* 
	* @return True on success or false on failure.
	*/
	public function setGroup($groupName, $permissions);
	
	/**
	* Gets the groupName permissions.
	* 
	* @return Array of permissions or false on failure.
	*/
	public function getGroup($groupName);

}
/**
* \ingroup UserSystem
* 
* \brief A user entity for the UserSystem standard.
* 
* Tokens:
*	- "<": Sorts from higest to lowest or newest to oldest.
* 		- Allowed values:
* 			- string time
*	- ">": Sorts from lowest to higest or oldest to newest.
* 		- Allowed values:
* 			- string time
*	- ";": Not true checker.
* 		- Allowed values:
* 			- string confirmed.
*	- "~": Is true checker.
* 		- Allowed values:
* 			- string confirmed.
*	- "#": Search for an id.
* 		- Allowed values:
* 			- int Any int id(1, 6236, ect.).
*	- "=": pair([array of 2]) Checks if the column(first value) value is = to the second value.
* 
*/
interface iUserSystem_User {
	const iType = "User";
	const iShort = "u";
	const iParent = "iUserSystem";
	
	/**
	* Sets the users username to the newUsername.
	*/
	public function changeUsername($newUsername);
	
	public function getUsername();
	
	public function getRegisterTime();
	
	public function getEmail();
	
	public function getId();
	
	public function isConfirmed();
	
	/**
	* @return True if the $plainPassword matches the current  password, or false if not.
	*/
	public function checkPassword($plainPassword);
	
	/**
	* Checks if the user has the permissionPath permission.
	* 
	* See iUserSystem for more info on Permission paths.
	* 
	* @return True if the user has the permission or false if not.
	*/
	public function checkPermission($permissionPath);
	
	/**
	* Adds this user to the groupName.
	* 
	* @return True on success or false on failure.
	*/
	public function addToGroup($groupName);
	
	/**
	* Removes this user from the groupName.
	* 
	* @return True on success or false on failure.
	*/
	public function removeFromGroup($group);
	
	/**
	* Checks if the user is in the groupName group.
	* 
	* @return True if this user is in the groupName or false if not.
	*/
	public function inGroup($groupName);
	
	/**
	* This will set the password to the $newPassword
	*/
	public function setPassword($newPassword);
	
	/**
	* This will use the installed iNotificationSystem standard to notify the user.
	* 
	* If an iNotificationSystem standard is not installed this will send an email with the $title being the subject.
	*/
	public function notify($title, $body, $label = "Notification", $sendEmail = true);
	
	/**
	* @return View object with the $injections injected.
	* 
	* Levels:
	* - 1 small:
	*  - Rows: 
	*    - "username": The username of this user.
	*  - Injections: 
	*    - "action": An area for actions(buttons) to go.
	* 
	* Example: 
	* \code
	* static protected function getRawView($inj, $level = 1) {
	* 	$view = new MyView($inj, $level); //This will create our new view and override the injections with $inj.
	* 	return $view;
	* }
	* \endcode
	*/
	static function getRawView($injections, $level = 1);
}

/*--------------------------------ForumSystem--------------------------------*/ 
interface iForumSystem {
	const iType = "ForumSystem";
	const iShort = "fs";
	const iVersion = "1.0";
	
	const iEntities = [
		"Forum"
	];
	
	const iViews = [
		"Post" => "The view that's used for forum posts, the class should have a static bodyFilter that can be overrided with a function name that will be called with the body as a param and return the filtered body"
	];
	
	public function getForum($nameOrId);
	
	const iControls = [
		"PostBody" => "The post body control class, the class should have a static inputType property that defines the input it uses."
	];
}

interface iForumSystem_Forum {
	const iType = "Forum";
	const iShort = "f";
	const iParent = "iForumSystem";
	
	function setName($name);
	function getName();

	function setDescription($desc);
	function getDescription();
	
	function setLocked($locked = true);
	function isLocked();
	
	/**
	* Returns a string containing the date this was created in mysql timestamp format.
	*/
	function when();
	
	/**
	* @return View object with the $injections injected.
	* 
	* Levels:
	* - 1 small:
	*  - Rows: 
	*    - "name": The forum name.
	*  - Injections: 
	*    - "action": An area for actions(buttons) to go.
	* 
	* Example: 
	* \code
	* static protected function getRawView($inj, $level = 1) {
	* 	$view = new MyView($inj, $level); //This will create our new view and override the injections with $inj.
	* 	return $view;
	* }
	* \endcode
	*/
	static function getRawView($injections, $level = 1);
}

interface iForumSystem_Post {
	const iType = "Post";
	const iShort = "p";
	const iParent = "iForumSystem";
	
	function setForum($forumId);
	/**
	* Returns an entity of the forum this post/thread is in.
	*/
	function getForum();
	
	/**
	* If this post is the thread starter.
	*/
	function isHost();
	
	function setBody($body);
	function getBody();
	
	function setTitle($title);
	function getTitle();
	
	/**
	* Returns a UserSystem::User entity instance of the user who created this post/thread
	*/
	function getUser();
	
	function setLocked($locked = true);
	function isLocked();
	
	/**
	* Returns a string containing the date this was created in mysql timestamp format.
	*/
	function when();
	
	/**
	* Returns a string containing the date this was last edited in mysql timestamp format.
	*/
	function lastEdit();
	
	/**
	* @return View object with the $injections injected.
	* 
	* Levels:
	* - 1 normal:
	*  - Rows: 
	*    - "title": The post title if any.
	*    - "body": The post body.
	*    - "created": The post create date.
	*    - "lastEdit": When the post was last edited.
	*    - "locked": If the post is locked.
	*    - "user": The id of the user who created this post.
	*  - Injections: 
	*    - "action": An area for actions(buttons) to go.
	* 
	* Example: 
	* \code
	* static protected function getRawView($inj, $level = 1) {
	* 	$view = new MyView($inj, $level); //This will create our new view and override the injections with $inj.
	* 	return $view;
	* }
	* \endcode
	*/
	static function getRawView($injections, $level = 1);
}

/*--------------------------------NotificationSystem--------------------------------*/ 

/**
* \defgroup NotificationSystem Notification System
* 
* Standard id: NotificationSystem
* 
* Requires: UserSystem
* 
* This is is a simple Notification system for notifying users about events and alerts.
* 
* To create a notification use this:
* \code
* $ns = Standards::NotificationSystem();
* $noti = new $ns->Notification(); //Create the object.
* $noti->setUser(6032); //Send this to the user with an id of 6032
* $noti->setTitle("Hello");
* $noti->setBody("world!!!");
* $noti->email(); //Email the user with notification.
* $noti->save(); //Save the notification in the database so that the user can see it.
* \endcode
* 
*/

interface iNotificationSystem {
	const iType = "NotificationSystem";
	const iShort = "ns";
	const iVersion = "1.0";
	
	const iEntities = [
		"Notification"
	];
	
	/**
	* Must have all constants and $[Widget name] variables.
	*/
	const iWidgets = [
		"Notification(user id, single notification injections)" => "A list of notifications for the user id."
	];
}


/**
* \ingroup NotificationSystem
* 
* \brief A notification entity for the NotificationSystem standard.
* 
* Linker:
*  - "Notification"(id): This should be a page where a user can view their notifications and should focus in on the notification with the id = to the get variable "id".
* 
* Tokens:
*	- "<": Sorts from higest to lowest or newest to oldest.
* 		- Allowed values:
* 			- string time
*	- ">": Sorts from lowest to higest or oldest to newest.
* 		- Allowed values:
* 			- string time
*	- ";": Not true checker.
* 		- Allowed values:
* 			- string viewed
* 			- string dismissed
*	- "~": Is true checker.
* 		- Allowed values:
* 			- string viewed
* 			- string dismissed
*	- "#": Search for an id.
* 		- Allowed values:
* 			- int Any int id(1, 6236, ect.).
*	- "=": pair([array of 2]) Checks if the column(first value) value is = to the second value.
* 
*/
interface iNotificationSystem_Notification {
	const iType = "Notification";
	const iShort = "n";
	const iParent = "iNotificationSystem";
	
	public function setTitle($title);
	public function getTitle();
	
	public function setBody($content);
	public function getBody();
		
	public function setLabel($label);
	public function getLabel();
	
	/**
	* @param boolean $unDismiss If this should set this notification to not dismissed.
	*/
	public function dismiss($unDismiss = false);
	
	/**
	* @return true if the notification is dismissed or false if not.
	*/
	public function isDismissed();
	
	/**
	* This will set the notification to viewed.
	*/
	public function hide();
	
	/**
	* This will set the notification to not viewed.
	*/
	public function show();
	
	public function isViewed();
	
	public function setUser($userId);
	
	/**
	* Use this to send the user an email about this notification.
	*/
	public function email();
	
	/**
	* @return User id
	*/
	public function getUser();
	
	/**
	* @return View object with the $injections injected.
	* 
	* Levels:
	* - 1 small: A small quick view of the notification.
	*  - Rows: 
	*    - "title"
	*    - "body"
	*    - "dismissed"
	*    - "viewed"
	*    - "date"
	*    - "user"
	*    - "label"
	*  - Injections: 
	*    - "action": An area for actions(buttons) to go.
	* - 2 full: The full scale message
	*  - Rows: 
	*    - "title"
	*    - "body"
	*    - "dismissed"
	*    - "viewed"
	*    - "date"
	*    - "user"
	*    - "label"
	*  - Injections: 
	*    - "action": An area for actions(buttons) to go.
	*	
	*/
	static function getRawView($injections, $level = 1);
}


/*--------------------------------CommentSystem--------------------------------*/ 

/**
* \defgroup CommentSystem Comment System
* 
* Standard id: CommentSystem
* 
* Requires: UserSystem
* 
* A very small comment system that lets you attach a comment widget to any entity including other comments.
*/

interface iCommentSystem {
	const iType = "CommentSystem";
	const iShort = "cs";
	const iVersion = "1.0";
	
	const iEntities = [
		"Comment"
	];
	
	/**
	* Must have all constants and $[Widget name] variables.
	*/
	const iWidgets = [
		"CommentList(string entityPath)" => "A comment list that lets users post comments and edit their own comments."
	];
}


/**
* \ingroup CommentSystem
* 
* \brief The comment entity for CommentSystem.
* 
* Tokens:
*	- "<": Sorts from higest to lowest or newest to oldest.
* 		- Allowed values:
* 			- string time
*	- ">": Sorts from lowest to higest or oldest to newest.
* 		- Allowed values:
* 			- string time
*	- ";": Not true checker.
* 		- Allowed values:
* 			- string viewed
* 			- string dismissed
*	- "~": Is true checker.
* 		- Allowed values:
* 			- string viewed
* 			- string dismissed
*	- "#": Search for an id.
* 		- Allowed values:
* 			- int Any int id(1, 6236, ect.).
*	- "=": pair([array of 2]) Checks if the column(first value) value is = to the second value.
* 
*/
interface iCommentSystem_Comment {
	const iType = "Comment";
	const iShort = "c";
	const iParent = "iCommentSystem";
	
	public function setBody($content);
	public function getBody();
	
	public function getEntity();
	public function setEntity($ent);
	
	public function getDatePosted();
	
	public function setUser($userId);
	
	/**
	* @return User id
	*/
	public function getUser();
	
	/**
	* @return View object with the $injections injected.
	* 
	* Levels:
	* - 1 normal: The normal horizontal comment display
	*  - Rows: 
	*    - "body"
	*    - "date"
	*    - "user"
	*  - Injections: 
	*    - "action": An area for actions(buttons) to go.
	*    - "user": Under the user block.
	*	
	*/
	static function getRawView($injections, $level = 1);
}
/*--------------------------------RateSystem--------------------------------*/ 

/**
* \defgroup RateSystem Rate System
* 
* Standard id: RateSystem
* 
* Requires: UserSystem
* 
* Easy to use rating system that contains multiple types of ratings thumbs, stars, ect.
*/

interface iRateSystem {
	const iType = "RateSystem";
	const iShort = "rs";
	const iVersion = "1.0";
	
	/**
	* Must have all constants and $[Widget name] variables.
	*/
	const iWidgets = [
		"Thumbs(string entityPath, bool locked = false)" => "A like/dislike widget thats small and shows the amount of likes minus the dislikes.",
		"Stars(string entityPath, bool locked = false)" => "Five star rating that shows the average star rating. Solid stars."
	];
	
	/**
	* Call this to add a thumbs up true, or a thumbs down false.
	* 
	* @param string $entityPath The path of the entity to add the rating to.
	* @param int $userId The user id to associate the rating with. Use -1 for system.
	* @param boolean $upOrDown True for up, false for down.
	*/
	public function addThumbs($entityPath, $userId, $upOrDown);
	
	/**
	* Call this to add a star rating.
	* 
	* @param string $entityPath The path of the entity to add the rating to.
	* @param int $userId The user id to associate the rating with. Use -1 for system.
	* @param int $numberOfStars 1-5.
	*/
	public function addStars($entityPath, $userId, $numberOfStars);
	
	
	public function removeThumbs($entityPath, $userId);
	
	public function removeStars($entityPath, $userId);
}

/*--------------------------------MarkdownToolkit--------------------------------*/ 

/**
* \defgroup MarkdownToolkit Markdown Toolkit
* 
* Standard id: MarkdownToolkit
* 
* A very small markdown parser and editor.
* 
* \note In the future this will become a TextStructureToolkit, containg more than just markdown.
*/

interface iMarkdownToolkit {
	const iType = "MarkdownToolkit";
	const iShort = "mt";
	const iVersion = "1.0";
	
	const iEntities = [
	];
	
	/**
	* Must have all constants and $[Widget name] variables.
	*/
	const iWidgets = [
		"Markdown(string body)" => "This will return an html string with compiled markdown or raw markdown that will be compiled on the client."
	];
	
	/**
	* The input needed.
	*
	* Must have all constants and $[Input name] variables.
	*/
	const iInputs = [
		"MarkdownEditor" => "A multiline Input::Text derived input that has a built in editor."
	];
}






 ?>