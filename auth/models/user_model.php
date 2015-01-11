<?php

/**
 * Name:		User_model
 *
 * Description:	The user model contains all methods for interacting and
 *				querying the active user. It also contains functionality for
 *				interfacing with the database with regards user accounts.
 *
 **/

/**
 * OVERLOADING NAILS' MODELS
 *
 * Note the name of this class; done like this to allow apps to extend this class.
 * Read full explanation at the bottom of this file.
 *
 **/

class NAILS_User_model extends NAILS_Model
{
	protected $_me;
	protected $_active_user;
	protected $_remember_cookie;
	protected $_is_remembered;
	protected $_is_logged_in;

	// --------------------------------------------------------------------------

	/**
	 * Construct the user model
	 */
	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------------------------------------

		//	Set defaults
		$this->_remember_cookie = 'nailsrememberme';
		$this->_is_remembered   = null;

		// --------------------------------------------------------------------------

		//	Clear the active_user
		$this->clear_active_user();
	}

	// --------------------------------------------------------------------------

	/**
	 * Initialise the generic user model
	 * @return void
	 */
	public function init()
	{
		//	Refresh user's session
		$this->_refresh_session();

		// --------------------------------------------------------------------------

		//	If no user is logged in, see if there's a remembered user to be logged in
		if (!$this->is_logged_in()) {

			$this->_login_remembered_user();
		}
	}


	// --------------------------------------------------------------------------

	/**
	 * Log in a previously logged in user
	 * @return boolean
	 */
	protected function _login_remembered_user()
	{
		//	Is remember me functionality enabled?
		$this->config->load('auth/auth');

		if (!$this->config->item('auth_enable_remember_me')) {

			return false;
		}

		// --------------------------------------------------------------------------

		//	Get the credentials from the cookie set earlier
		$remember = get_cookie($this->_remember_cookie);

		if ($remember) {

			$remember = explode('|', $remember);
			$email    = isset($remember[0]) ? $remember[0] : null;
			$code     = isset($remember[1]) ? $remember[1] : null;

			if ($email && $code) {

				//	Look up the user so we can cross-check the codes
				$user = $this->get_by_email($email, true);

				if ($user && $code === $user->remember_code) {

					//	User was validated, log them in!
					$this->set_login_data($user->id);
					$this->_me = $user->id;
				}
			}
		}

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Fetches a value from the active user's session data; done this way so
	 * that interfacing with active user data is consistent.
	 *
	 * @access	public
	 * @param	string	$keys		The key to look up in active_user
	 * @param	string	$delimiter	If multiple fields are requested they'll be joined by this string
	 * @return	mixed
	 *
	 **/
	public function active_user($keys = false, $delimiter = ' ')
	{
		//	Only look for a value if we're logged in
		if (!$this->is_logged_in()) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	If $keys is false just return the user object in its entirety
		if ($keys === false) :

			return $this->_active_user;

		endif;

		// --------------------------------------------------------------------------

		//	Only stitch items together if we have more than one key
		if (strpos($keys, ',') === false) :

			$_val = (isset($this->_active_user->{$keys})) ? $this->_active_user->{$keys} : false;

			//	If something is found, then use that
			if ($_val !== false) :

				return $_val;

			else:

				//	Nothing was found, but if $keys matches user_meta_* then attempt an extra table look up
				if (preg_match('/^user_meta_(.*)/', $keys)) :

					//	Look up the extra table
					$_val = $this->extra_table_fetch($keys, null, $this->_active_user->id);

					//	Save it to active_user so that we don't do this lookup twice
					$this->_active_user->{$keys} = $_val;

					//	...and return the data to the user.
					return $_val;

				endif;

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	More than one key
		$keys = explode(',', $keys);
		$_out = array();

		foreach ($keys AS $key) :

			$_val = (isset($this->_active_user->{trim($key)})) ? $this->_active_user->{trim($key)} : false;

			//	If something is found, use that.
			if ($_val !== false) :

				$_out[] = $_val;

			else:

				//	Nothing was found, but if $key matcehs user_meta_* then attempt an extra table look up
				if (preg_match('/^user_meta_(.*)/', $key)) :

					//	Look up the extra table
					$_val = $this->extra_table_fetch($key, null, $this->_active_user->id);

					//	Save it to active_user so that we don't do this lookup twice
					$this->_active_user->{$key} = $_val;

					//	...and return the data to the user.
					//	(Normally doesn't really make sense as this will just return the word Array because
					//	this is being imploded into a concacted string, however if a comma is left in by
					//	accident or the other keys fail to return data then the output will be as normal).

					$_out[] =  $_val;

				endif;

			endif;

		endforeach;

		//	If nothing was found, just return false
		if (empty($_out)) :

			return false;

		endif;

		//	If we have more than 1 element then stitch them together,
		//	if not just return the single element

		return count($_out > 1) ? implode($delimiter, $_out) : $_out[0];
	}


	// --------------------------------------------------------------------------


	public function set_active_user($user)
	{
		$this->_active_user = $user;

		// --------------------------------------------------------------------------

		//	Set the user's date/time formats
		$_format_date = active_user('pref_date_format') ? active_user('pref_date_format') : $this->datetime_model->get_date_format_default_slug();
		$_format_time = active_user('pref_time_format') ? active_user('pref_time_format') : $this->datetime_model->get_time_format_default_slug();

		$this->datetime_model->set_formats($_format_date, $_format_time);
	}


	// --------------------------------------------------------------------------


	public function clear_active_user()
	{
		$this->_active_user = new stdClass();
	}


	// --------------------------------------------------------------------------


	/**
	 * Sets the login data for a user
	 *
	 * @access	public
	 * @param	int		id			The user's id
	 * @param	string	$email		The user's email
	 * @param	int		$group_id	The user's group
	 * @return	void
	 *
	 **/
	public function set_login_data($id, $set_session_data = true)
	{
		//	Valid user?
		if (is_numeric($id)) :

			$_user	= $this->get_by_id($id);
			$_error	= 'Invalid User ID.';

		elseif (is_string($id)) :

			if (valid_email($id)) :

				$_user	= $this->get_by_email($id);
				$_error	= 'Invalid User email.';

			else :

				$this->_set_error('Invalid User email.');
				return false;

			endif;

		else :

			$this->_set_error('Invalid user ID or email.');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test user
		if (!$_user) :

			$this->_set_error($_error);
			return false;

		elseif ($_user->is_suspended) :

			$this->_set_error('User is suspended.');
			return false;

		else :

			//	Set the flag
			$this->_is_logged_in = true;

			//	Set session variables
			if ($set_session_data) :

				$_session = array(
					'id'		=> $_user->id,
					'email'		=> $_user->email,
					'group_id'	=> $_user->group_id,
				);
				$this->session->set_userdata($_session);

			endif;

			//	Set the active user
			$this->set_active_user($_user);

			return true;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Clears the login data for a user
	 *
	 * @access	public
	 * @return	void
	 *
	 **/
	public function clear_login_data()
	{
		//	Clear the session
		$this->session->unset_userdata('id');
		$this->session->unset_userdata('email');
		$this->session->unset_userdata('group_id');

		//	Set the flag
		$this->_is_logged_in = false;

		//	Reset the active_user
		$this->clear_active_user();

		//	Remove any rememebr me cookie
		$this->clear_remember_cookie();
	}


	// --------------------------------------------------------------------------


	/**
	 * Determines whether the active user is logged in or not.
	 *
	 * @access	public
	 * @return	bool
	 *
	 **/
	public function is_logged_in()
	{
		return $this->_is_logged_in;
	}


	// --------------------------------------------------------------------------


	/**
	 * Determines whether the active user is to be remembered
	 *
	 * @access	public
	 * @return	bool
	 *
	 **/
	public function is_remembered()
	{
		//	Deja vu?
		if (null !== $this->_is_remembered) :

			return $this->_is_remembered;

		endif;

		// --------------------------------------------------------------------------

		//	Look for the remember me cookie and explode it, if we're landed with
		//	a 2 part array then it's likely this is a valid cookie - however, this
		//	test is, obviously, not gonna detect a spoof.

		$this->load->helper('cookie');

		$_cookie = get_cookie($this->_remember_cookie);
		$_cookie = explode('|', $_cookie);

		$this->_is_remembered = count($_cookie) == 2 ? true : false;

		return $this->_is_remembered;
	}


	// --------------------------------------------------------------------------


	/**
	 * Determines whether the active user group has admin permissions.
	 *
	 * @access	public
	 * @return	boolean
	 *
	 **/
	public function is_admin($user = null)
	{
		if ($this->is_superuser($user)) :

			return true;

		endif;

		return $this->has_permission('admin', $user);
	}


	// --------------------------------------------------------------------------


	/**
	 * Determines whether the active user is a superuser. Extend this method to
	 * alter it's response.
	 *
	 * @access	public
	 * @return	boolean
	 *
	 **/
	public function is_superuser($user = null)
	{
		return $this->has_permission('superuser', $user);
	}


	// --------------------------------------------------------------------------


	/**
	 * When an admin 'logs in as' another user a hash is added to the session so
	 * the system can log them back in. this method is simply a quick and logical
	 * way of checking if the session variable exists.
	 *
	 * @access	public
	 * @return	boolean
	 *
	 **/
	public function was_admin()
	{
		return (bool) $this->session->userdata('admin_recovery');
	}


	// --------------------------------------------------------------------------


	/**
	 * Determines whether the specified user has a certain ACL permission
	 *
	 * @access	public
	 * @param	string	$permission	The permission to check for, in the format admin.account.view
	 * @param	mixed	$user		The user to check for; if null uses active user, if numeric, fetches suer, if object uses that object
	 * @return	boolean
	 *
	 **/
	public function has_permission($permission, $user = null)
	{
		//	Fetch the correct ACL
		if (is_numeric($user)) :

			$_user = $this->get_by_id($user);

			if (isset($_user->acl)) :

				$_acl = $_user->acl;
				unset($_user);

			else :

				return false;

			endif;

		elseif (isset($user->acl)) :

			$_acl = $user->acl;

		else :

			$_acl = active_user('acl');

		endif;

		if (!$_acl) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Super users can do anything they damn well please
		if (isset($_acl['superuser']) && $_acl['superuser']) :

			return true;

		endif;

		// --------------------------------------------------------------------------

		//	Test ACL, making sure to clean any dangerous data first
		$_permission = preg_replace('/[^a-zA-Z\_\.\:0-9]/', '', $permission);
		$_permission = explode('.', $_permission);
		eval('$has_permission = isset($_acl[\'' . implode('\'][\'', $_permission) .'\']);');
		return $has_permission;
	}


	// --------------------------------------------------------------------------


	/**
	 * Get an array of users from the database, by default only user, group and
	 * meta information is returned, request specific extra meta by specifying
	 * which tables to include as the first parameter; seperate multiple tables
	 * using a comma.
	 *
	 * @access	public
	 * @param	mixed	$extended	Specific extra tables to join, true for all user_meta_*
	 * @return	string
	 *
	 **/
	public function get_all($extended = null, $order = null, $limit = null, $where = null, $search = null)
	{
		//	Write selects
		$this->db->select('u.*');
		$this->db->select('ue.email, ue.code email_verification_code, ue.is_verified email_is_verified, ue.date_verified email_is_verified_on');
		$this->db->select($this->_get_meta_columns('um'));
		$this->db->select('ug.slug group_slug,ug.label group_name,ug.default_homepage group_homepage,ug.acl group_acl');

		// --------------------------------------------------------------------------

		//	Set Order
		if (is_array($order)) :

			$this->db->order_by($order[0], $order[1]);

		endif;

		// --------------------------------------------------------------------------

		//	Set Limit
		if (is_array($limit)) :

			$this->db->limit($limit[0], $limit[1]);

		endif;

		// --------------------------------------------------------------------------

		//	Build conditionals
		$this->_getcount_users_common($where, $search);

		// --------------------------------------------------------------------------

		//	Execute Query
		$q = $this->db->get(NAILS_DB_PREFIX . 'user u');

		if (!$q) :

			return array();

		endif;

		$_user = $q->result();

		// --------------------------------------------------------------------------

		//	Include any extra tables?
		if ($extended) :

			//	Determine which tables we're including
			if ($extended === true) :

				//	If $extended is true we'll just join everything

				//	Pull up a list of the user_meta tables
				$q = $this->db->query("SHOW TABLES LIKE 'user_meta_%'")->result();

				foreach($q AS $key => $table) :

					$_tables[] = current((array) $table);

				endforeach;

			else :

				//	Specific tables defined, use them
				if (strpos($extended, ',')) :

					$_tables = explode(',', $extended);

				else :

					$_tables[] = $extended;

				endif;

			endif;

			// --------------------------------------------------------------------------

			//	Add the result of each extra table to each user in our result set

			if (isset($_tables)) :

				//	Loop for each returned user
				foreach ($_user AS $_u) :

					foreach ($_tables AS $table) :

						$this->db->where('user_id', $_u->id);
						$_u->{trim($table)} = $this->db->get(trim($table))->result();

					endforeach;

				endforeach;

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	Determine the user's ACL
		foreach ($_user AS $user) :

			$user->group_acl = unserialize($user->group_acl);

			//	If the user has an ACL set then we'll need to extract and merge that
			if ($user->user_acl) :

				$user->user_acl	= unserialize($user->user_acl);
				$user->acl		= array();

				foreach($user->group_acl AS $key => $value) :

					if (array_key_exists($key, $user->user_acl)) :

						//	This key DOES exist in the second array, we'll need to analyse it and see if
						//	we need to recursively search. We are overwriting the source value with the
						//	new value, if both source and target

						if (is_array($value) && is_array($user->user_acl[$key])) :

							//	Two arrays, which will need to be merged
							$user->acl[$key] = array_merge($value, $user->user_acl[$key]);

						else :

							//	Simply overwrite the old value with the new one, no recursion required
							$user->acl[$key] = $user->user_acl[$key];

						endif;

					else :

						//	This key isn't in the new array, ignore and just tack this onto the $result
						$user->acl[$key] = $value;

					endif;

				endforeach;

			else :

				$user->acl = $user->group_acl;

			endif;

			// --------------------------------------------------------------------------

			//	Format the user object
			$this->_format_user_object($user);

		endforeach;

		// --------------------------------------------------------------------------

		//	Return the data
		return $_user;
	}


	// --------------------------------------------------------------------------


	/**
	 * Get an array of users from the database, by default only user, group and
	 * meta information is returned, request specific extra meta by specifying
	 * which tables to include as the first parameter; seperate multiple tables
	 * using a comma.
	 *
	 * @access	public
	 * @param	mixed	$extended	Specific extra tables to join, true for all user_meta_*
	 * @return	string
	 *
	 **/
	public function get_all_minimal($order = null, $limit = null, $where = null, $search = null)
	{
		//	Write selects
		$this->db->select('u.id, ue.email, ue.code email_verification_code, ue.is_verified email_is_verified, ue.date_verified email_is_verified_on, u.first_name, u.last_name, u.profile_img, u.gender');

		// --------------------------------------------------------------------------

		//	Set Order
		if (is_array($order)) :

			$this->db->order_by($order[0], $order[1]);

		endif;

		// --------------------------------------------------------------------------

		//	Set Limit
		if (is_array($limit)) :

			$this->db->limit($limit[0], $limit[1]);

		endif;

		// --------------------------------------------------------------------------

		//	Build conditionals
		$this->_getcount_users_common($where, $search);

		// --------------------------------------------------------------------------

		//	Execute Query
		$q		= $this->db->get(NAILS_DB_PREFIX . 'user u');
		$_user	= $q->result();

		// --------------------------------------------------------------------------

		//	Determine the user's ACL
		foreach ($_user AS $user) :

			//	Format the user object
			$this->_format_user_object($user, true);

		endforeach;

		// --------------------------------------------------------------------------

		//	Return the data
		return $_user;
	}


	// --------------------------------------------------------------------------


	/**
	 * Counts the total amount of users for a partricular query/search key. Essentially performs
	 * the same query as $this->get_all() but without limiting.
	 *
	 * @access	public
	 * @param	string	$where	An array of where conditions
	 * @param	mixed	$search	A string containing the search terms
	 * @return	int
	 *
	 **/
	public function count_all($where = null, $search = null)
	{
		$this->_getcount_users_common($where, $search);

		// --------------------------------------------------------------------------

		//	Execute Query
		return $this->db->count_all_results(NAILS_DB_PREFIX . 'user u');
	}


	// --------------------------------------------------------------------------


	/**
	 * Consolidates the extra calls which need to be made (to save having the same calls in get_all and count_all)
	 *
	 * @access	protected
	 * @param	string	$where	An array of where conditions
	 * @param	mixed	$search	A string containing the search terms
	 * @return	int
	 *
	 **/
	protected function _getcount_users_common($where = null, $searchTerm = null)
	{
		$this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'u.id = ue.user_id AND ue.is_primary = 1', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'user_meta um', 'u.id = um.user_id', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'user_group ug', 'u.group_id = ug.id', 'LEFT');

		// --------------------------------------------------------------------------

		//	Set Where
		if ($where) :

			$this->db->where($where);

		endif;

		// --------------------------------------------------------------------------

		//	Set Search
		if ($searchTerm && is_string($searchTerm)) :

			//	Search is a simple string, no columns are being specified to search across
			//	so define a default set to search across

			$search							= array();
			$search['keywords']				= $searchTerm;
			$search['columns']				= array();
			$search['columns']['id']		= 'u.id';
			$search['columns']['email']		= 'ue.email';
			$search['columns']['username']	= 'u.username';
			$search['columns']['name']		= array(' ', 'u.first_name', 'u.last_name');

		endif;

		if (!empty($search[ 'keywords' ])) :

			$search[ 'keywords' ] = $this->db->escape_like_str($search[ 'keywords' ]);

		endif;

		//	If there is a search term to use then build the search query
		if (!empty($search[ 'keywords' ])) :

			//	Parse the keywords, look for specific column searches
			preg_match_all('/\(([a-zA-Z0-9\.\- \_]+)=\"(.+?)\"\)/', $search['keywords'], $_matches);

			if ($_matches[1] && $_matches[2]) :

				$_specifics = array_combine($_matches[1], $_matches[2]);

			else :

				$_specifics = array();

			endif;

			//	Match the specific labels to a column
			if ($_specifics) :

				$_temp = array();

				foreach ($_specifics AS $col => $value) :

					if (isset($search['columns'][ strtolower($col)])) :

						$_temp[] = array(
							'cols'	=> $search['columns'][ strtolower($col)],
							'value'	=> $value
						);

					endif;

				endforeach;

				$_specifics = $_temp;
				unset($_temp);

				// --------------------------------------------------------------------------

				//	Remove controls from search string
				$search['keywords'] = preg_replace('/\(([a-zA-Z0-9\.\- ]+):([a-zA-Z0-9\.\- ]+)\)/', '', $search['keywords']);

			endif;

			if ($_specifics) :

				//	We have some specifics
				foreach($_specifics AS $specific) :

					if (is_array($specific['cols'])) :

						$_separator = array_shift($specific['cols']);
						$this->db->like('CONCAT_WS(\'' . $_separator . '\', ' . implode(',', $specific['cols']) . ')', $specific['value']);

					else :

						$this->db->like($specific['cols'], $specific['value']);

					endif;

				endforeach;

			endif;

			// --------------------------------------------------------------------------

			if ($search['keywords']) :

				$_where  = '(';

				if (!empty($search[ 'columns' ])) :

					//	We have some specifics
					foreach($search[ 'columns' ] AS $col) :

						if (is_array($col)) :

							$_separator = array_shift($col);
							$_where .= 'CONCAT_WS(\'' . $_separator . '\', ' . implode(',', $col) . ') LIKE \'%' . trim($search['keywords']) . '%\' OR ';

						else :

							$_where .= $col . ' LIKE \'%' . trim($search['keywords']) . '%\' OR ';

						endif;

					endforeach;

				endif;

				$this->db->where(substr($_where, 0, -3) . ')');

			endif;

		endif;
	}


	// --------------------------------------------------------------------------


	protected function _get_user_columns()
	{
		$_cols		= array();

		// --------------------------------------------------------------------------

		$_cols[]	= 'group_id';
		$_cols[]	= 'ip_address';
		$_cols[]	= 'last_ip';
		$_cols[]	= 'password';
		$_cols[]	= 'password_md5';
		$_cols[]	= 'password_engine';
		$_cols[]	= 'password_changed';
		$_cols[]	= 'salt';
		$_cols[]	= 'forgotten_password_code';
		$_cols[]	= 'remember_code';
		$_cols[]	= 'created';
		$_cols[]	= 'last_login';
		$_cols[]	= 'last_seen';
		$_cols[]	= 'is_suspended';
		$_cols[]	= 'temp_pw';
		$_cols[]	= 'failed_login_count';
		$_cols[]	= 'failed_login_expires';
		$_cols[]	= 'last_update';
		$_cols[]	= 'user_acl';
		$_cols[]	= 'login_count';
		$_cols[]	= 'admin_data';
		$_cols[]	= 'referral';
		$_cols[]	= 'referred_by';
		$_cols[]	= 'salutation';
		$_cols[]	= 'first_name';
		$_cols[]	= 'last_name';
		$_cols[]	= 'gender';
		$_cols[]	= 'dob';
		$_cols[]	= 'profile_img';
		$_cols[]	= 'timezone';
		$_cols[]	= 'datetime_format_date';
		$_cols[]	= 'datetime_format_time';
		$_cols[]	= 'language';

		// --------------------------------------------------------------------------

		return $_cols;
	}


	// --------------------------------------------------------------------------


	protected function _get_meta_columns($prefix = '', $cols = array())
	{
		//	Module: shop
		if (module_is_enabled('shop')) :

			$cols[] = 'shop_basket';
			$cols[] = 'shop_currency';
			$cols[] = 'shop_recently_viewed';

		endif;

		// --------------------------------------------------------------------------

		//	Clean up
		$cols = array_unique($cols);
		$cols = array_filter($cols);

		// --------------------------------------------------------------------------

		//	Prefix all the values, if needed
		if ($prefix) :

			foreach($cols AS $key => &$value) :

				$value = $prefix . '.' . $value;

			endforeach;

		endif;

		// --------------------------------------------------------------------------

		return $cols;
	}


	// --------------------------------------------------------------------------


	/**
	 * Get a specific user by their ID
	 *
	 * @access	public
	 * @param	string	$userId	The user's ID
	 * @param	mixed	$extended	Specific extra tables to join, true for all user_meta_*
	 * @return	object
	 *
	 **/
	public function get_by_id($userId, $extended = false, $skipCache = false)
	{
		if (!is_numeric($userId)) {

			return false;
		}

		if (!$extended && !$skipCache) {

			$cachedUser = $this->getCacheUser($userId);

			if ($cachedUser)
			 {
			 	return $cachedUser;
			 }
		}

		$this->db->where('u.id', (int) $userId);
		$user = $this->get_all($extended);

		if ($user){

			$this->setCacheUserObj($user[0]);
			return $user[0];

		} else {

			return false;
		}
	}


	// --------------------------------------------------------------------------


	/**
	 * Get a specific user by their IDs
	 *
	 * @access	public
	 * @param	string	$user_id	The user's ID
	 * @param	mixed	$extended	Specific extra tables to join, true for all user_meta_*
	 * @return	object
	 *
	 **/
	public function get_by_ids($userIds, $extended = false)
	{
		if (empty($userIds)) {

			return array();
		}

		$this->db->where_in('u.id', $userIds);
		return $this->get_all($extended);
	}


	// --------------------------------------------------------------------------




	/**
	 * Get a specific user by their email address
	 *
	 * @access	public
	 * @param	string	$email		The user's email address
	 * @param	mixed	$extended	Specific extra tables to join, true for all user_meta_*
	 * @return	object
	 *
	 **/
	public function get_by_email($email, $extended = false)
	{
		if (!is_string($email)) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Look up the email, and if we find an ID then fetch that user
		$this->db->select('user_id');
		$this->db->where('email', trim($email));
		$_id = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

		if ($_id) :

			return $this->get_by_id($_id->user_id);

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Get a specific user by their username
	 *
	 * @access	public
	 * @param	string	$user_id	The user's ID
	 * @param	mixed	$extended	Specific extra tables to join, true for all user_meta_*
	 * @return	object
	 *
	 **/
	public function get_by_username($username, $extended = false)
	{
		if (!is_string($username)) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		$this->db->where('u.username', trim($username));
		$user = $this->get_all($extended);

		return empty($user) ? false : $user[0];
	}


	// --------------------------------------------------------------------------



	/**
	 * Get a specific user by the MD5 hash of their ID and password
	 *
	 * @access	public
	 * @param	string	$_hash_id	The user's id as an MD5 hash
	 * @param	mixed	$_hash_pw	The user's hashed password as an MD5 hash
	 * @return	object
	 *
	 **/
	public function get_by_hashes($_hash_id, $_hash_pw, $extended = false)
	{
		if (empty($_hash_id) || empty($_hash_pw)) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Set wheres
		$this->db->where('u.id_md5',		$_hash_id);
		$this->db->where('u.password_md5',	$_hash_pw);

		// --------------------------------------------------------------------------

		//	Do it
		$this->db->limit(1);
		$q = $this->get_all($extended);

		// --------------------------------------------------------------------------

		return count($q) ? $q[0] : false ;
	}


	// --------------------------------------------------------------------------


	/**
	 * Get a specific user by their referral code
	 *
	 * @access	public
	 * @param	string	$referral_code	The user's referral code
	 * @param	mixed	$extended		Specific extra tables to join, true for all user_meta_*
	 * @return	object
	 *
	 **/
	public function get_by_referral($referral_code, $extended = false)
	{
		$this->db->where('u.referral', $referral_code);
		$user = $this->get_all($extended);

		return empty($user) ? false : $user[0];
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns recent users; ordered by ID, desc
	 *
	 * @access	public
	 * @param	int			$limit		The number of user's to return
	 * @param	boolean		$extended	Whether to include extended data or not
	 * @return	object
	 *
	 **/
	public function get_new_users($limit = 25, $extended = false)
	{
		$this->db->limit($limit);
		$this->db->order_by('u.id', 'desc');
		return $this->get_all($extended);
	}



	// --------------------------------------------------------------------------



	public function get_emails_for_user($id)
	{
		$this->db->where('user_id', $id);
		$this->db->order_by('is_primary', 'DESC');
		$this->db->order_by('email', 'ASC');
		return $this->db->get(NAILS_DB_PREFIX . 'user_email')->result();
	}


	// --------------------------------------------------------------------------


	/**
	 * Update a user, if $user_id is not set method will attempt to update the
	 * active user. If $data is passed then the method will attempt to update
	 * the user and/or user_meta tables
	 *
	 * @access	public
	 * @return	int		$id		The ID of the user to update
	 * @return	array	$data	Any data to be updated
	 *
	 **/
	public function update($user_id = null, $data = null)
	{
		$data = (array) $data;

		//	Get the user ID to update
		if (null !== $user_id && $user_id !== false) :

			$_uid = $user_id;

		elseif (active_user('id')) :

			$_uid = active_user('id');

		else :

			$this->_set_error('No user ID set');
			return false;

		endif;


		// --------------------------------------------------------------------------


		//	If there's some data we'll need to know the columns of `user`
		//	We also want to unset any 'dangerous' items then set it for the query

		if ($data) :

			//	Set the cols in `user` (rather than querying the DB)
			$_cols = $this->_get_user_columns();

			//	Safety first, no updating of user's ID.
			unset($data->id);
			unset($data->id_md5);

			//	If we're updating the user's password we should generate a new hash
			if (array_key_exists('password', $data)) :

				$_hash = $this->user_password_model->generateHash($data['password']);

				if (!$_hash) :

					$this->_set_error($this->user_password_model->last_error());
					return false;

				endif;

				$data['password']			= $_hash->password;
				$data['password_md5']		= $_hash->password_md5;
				$data['password_engine']	= $_hash->engine;
				$data['password_changed']	= date('Y-m-d H:i:s');
				$data['salt']				= $_hash->salt;

				$_password_updated = true;

			else :

				$_password_updated = false;

			endif;

			//	Set the data
			$_data_user						= array();
			$_data_meta						= array();
			$_data_email					= '';
			$_data_username					= '';
			$_data_reset_security_questions	= false;

			foreach ($data AS $key => $val) :

				//	user or user_meta?
				if (array_search($key, $_cols) !== false) :

					//	Careful now, some items cannot be blank and must be null
					switch($key) :

						case 'profile_img' :

							$_data_user[$key] = $val ? $val : null;

						break;

						default :

							$_data_user[$key] = $val;

						break;

					endswitch;

				elseif ($key == 'email') :

					$_data_email = strtolower(trim($val));

				elseif ($key == 'username') :

					$_data_username = strtolower(trim($val));

				elseif ($key == 'reset_security_questions') :

					$_data_reset_security_questions = $val;

				else :

					$_data_meta[$key] = $val;

				endif;

			endforeach;

			// --------------------------------------------------------------------------

			//	If a username has been passed then check if it's available
			if (!empty($_data_username)) {

				//	Check username is valid
				if (!$this->isValidUsername($_data_username, true, $_uid)) {

					return false;

				} else {

					$_data_user['username'] = $_data_username;
				}
			}

			// --------------------------------------------------------------------------

			//	Begin transaction
			$_rollback = false;
			$this->db->trans_begin();

			// --------------------------------------------------------------------------

			//	Resetting security questions?
			$this->config->load('auth/auth');

			if ($this->config->item('auth_two_factor_enable') && $_data_reset_security_questions) :

				$this->db->where('user_id', (int) $_uid);
				if (!$this->db->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_question')) :

					//	Rollback immediately in case there's email or password changes which
					//	might send an email.

					$this->db->trans_rollback();

					$this->_set_error('could not reset user\'s security questions.');

					return false;

				endif;

			endif;

			// --------------------------------------------------------------------------

			//	Update the user table
			$this->db->where('id', (int) $_uid);
			$this->db->set('last_update', 'NOW()', false);

			if ($_data_user) :

				$this->db->set($_data_user);

			endif;

			$this->db->update(NAILS_DB_PREFIX . 'user');

			// --------------------------------------------------------------------------

			//	Update the meta table
			if ($_data_meta) :

				$this->db->where('user_id', (int) $_uid);
				$this->db->set($_data_meta);
				$this->db->update(NAILS_DB_PREFIX . 'user_meta');

			endif;

			// --------------------------------------------------------------------------

			//	If an email has been passed then attempt to update the user's email too
			if ($_data_email) :

				if (valid_email($_data_email)) :

					//	Check if the email is already being used
					$this->db->where('email', $_data_email);
					$_email = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

					if ($_email) :

						//	Email is in use, if it's in use by the ID of this user then
						//	set it as the primary email for this account. If it's in use
						//	by another user then error

						if ($_email->user_id == $_uid) :

							$this->email_make_primary($_email->email);

						else :

							$this->_set_error('Email is already in use.');
							$_rollback = true;

						endif;

					else :

						//	Doesn't appear to be in use, add as a new email address and
						//	make it the primary one

						$this->email_add($_data_email, (int) $_uid, true);

					endif;

				else :

					//	Error, not a valid email; roll back transaction
					$this->_set_error('"' . $_data_email . '" is not a valid email address.');
					$_rollback = true;

				endif;

			endif;

			// --------------------------------------------------------------------------

			//	How'd we get on?
			if (!$_rollback && $this->db->trans_status() !== false) :

				$this->db->trans_commit();

				// --------------------------------------------------------------------------

				//	If the user's password was updated send them a notification
				if ($_password_updated) :

					$_email						= new stdClass();
					$_email->type				= 'password_updated';
					$_email->to_id				= $_uid;
					$_email->data				= array();
					$_email->data['updated_at']	= date('Y-m-d H:i:s');
					$_email->data['updated_by']	= array('id' => active_user('id'), 'name' => active_user('first_name,last_name'));
					$_email->data['ip_address']	= $this->input->ip_address();

					$this->emailer->send($_email, true);

				endif;

			else :

				$this->db->trans_rollback();
				return false;

			endif;

		else :

			//	If there was no data then run an update anyway on just user table. We need to do this
			//	As some methods will use $this->db->set() before calling update(); not sure if this is
			//	a bad design or not... sorry.

			$this->db->set('last_update', 'NOW()', false);
			$this->db->where('id', (int) $_uid);
			$this->db->update(NAILS_DB_PREFIX . 'user');

		endif;

		// --------------------------------------------------------------------------

		//	If we just updated the active user we should probably update their session info
		if ($_uid == active_user('id')) :

			$this->_active_user->last_update = date('Y-m-d H:i:s');

			if ($data) :

				foreach($data AS $key => $val) :

					$this->_active_user->{$key} = $val;

				endforeach;

			endif;

			// --------------------------------------------------------------------------

			//	Do we need to update any timezone/date/time preferences?
			if (isset($data['timezone'])) :

				$this->datetime_model->set_user_timezone($data['timezone']);

			endif;

			if (isset($data['datetime_format_date'])) :

				$this->datetime_model->set_date_format($data['datetime_format_date']);

			endif;

			if (isset($data['datetime_format_time'])) :

				$this->datetime_model->set_time_format($data['datetime_format_time']);

			endif;

			// --------------------------------------------------------------------------

			//	If there's a remember me cookie then update that too, but only if the password
			//	or email address has changed

			if ((isset($data['email']) || !empty($_password_updated)) && $this->is_remembered()) :

				$this->set_remember_cookie();

			endif;

		endif;

		// --------------------------------------------------------------------------

		$this->setCacheUser($_uid);

		// --------------------------------------------------------------------------

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns a user from the cache
	 * @param  int $userId The ID of the user to return
	 * @return mixed
	 */
	public function getCacheUser($userId)
	{
		return $this->_get_cache('user-' . $userId);
	}

	// --------------------------------------------------------------------------


	/**
	 * Saves a user object to the persistent cache
	 * @param int $userId The user ID to cache
	 */
	public function setCacheUser($userId)
	{
		$user = $this->get_by_id($userId, false, true);

		if (empty($user)) {

			return false;
		}

		$this->setCacheUserObj($user);

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Sets the user's object into the cache
	 * @param stdClass $userObj The complete user Object to cache
	 */
	protected function setCacheUserObj($userObj)
	{
		$this->_set_cache('user-' . $userObj->id, $userObj);
	}


	// --------------------------------------------------------------------------


	/**
	 * Removes a user object from the persistent cache
	 * @param int $userId The user ID to remove from cache
	 */
	public function unsetCacheUser($userId)
	{
		$this->_unset_cache('user-' . $userId);

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Adds a new email to the user_email table. Will optionally send the verification email, too.
	 * @param  string  $email       The email address to add
	 * @param  int     $user_id     The ID of the user to add for, defaults to active_user('id')
	 * @param  boolean $is_primary  Whether or not the email address should be the primary email address for the user
	 * @param  boolean $is_verified Whether ot not the email should be marked as verified
	 * @param  boolean $send_email  If unverified, whether or not the verification email should be sent
	 * @return mixed                String containing verification code on success, false on failure
	 */
	public function email_add($email, $user_id = null, $is_primary = false, $is_verified = false, $send_email = true)
	{
		$_user_id	= empty($user_id) ? active_user('id') : $user_id;
		$_email		= trim(strtolower($email));
		$_u			= $this->get_by_id($_user_id);

		if (!$_u) :

			$this->_set_error('Invalid User ID');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Make sure the email is valid
		if (!valid_email($_email)) :

			$this->_set_error('"' . $_email . '" is not a valid email address');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test email, if it's in use and for the same user then return true. If
		//	it's in use by a different user then return an error.

		$this->db->select('id, user_id, is_verified, code');
		$this->db->where('email', $_email);
		$_test = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

		if ($_test) :

			if ($_test->user_id == $_u->id) :

				//	In use, but belongs to the same user - return the code
				//	(imitates behavior of newly added email)

				if ($is_primary) :

					$this->email_make_primary($_test->id);

				endif;

				//	Resend verification email?
				if ($send_email && !$_test->is_verified) :

					$this->email_add_send_verify($_test->id);

				endif;

				//	Recache the user
				$this->setCacheUser($_u->id);

				return $_test->code;

			else :

				//	In use, but belongs to another user
				$this->_set_error('Email in use by another user.');
				return false;

			endif;

		endif;

		// --------------------------------------------------------------------------

		$_code = NAILS_User_password_model::salt();

		$this->db->set('user_id',		$_u->id);
		$this->db->set('email',		$_email);
		$this->db->set('code',			$_code);
		$this->db->set('is_verified',	(bool) $is_verified);
		$this->db->set('date_added',	'NOW()', false);

		if ((bool) $is_verified) {

			$this->db->set('date_verified', 'NOW()', false);
		}

		$this->db->insert(NAILS_DB_PREFIX . 'user_email');

		if ($this->db->affected_rows()) :

			//	Email ID
			$_email_id = $this->db->insert_id();

			//	Make it the primary email address?
			if ($is_primary) :

				$this->email_make_primary($_email_id);

			endif;

			//	Send off the verification email
			if ($send_email && !$is_verified) :

				$this->email_add_send_verify($_email_id);

			endif;

			//	Recache the user
			$this->setCacheUser($_u->id);

			//	Return the code
			return $_code;

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	public function email_add_send_verify($email_id, $user_id = null)
	{
		//	Fetch the email and the user's group
		$this->db->select('ue.id,ue.code,ue.is_verified,ue.user_id,u.group_id');

		if (is_numeric($email_id)) :

			$this->db->where('ue.id', $email_id);

		else :

			$this->db->where('ue.email', $email_id);

		endif;

		if (!empty($user_id)) :

			$this->db->where('ue.user_id', $user_id);

		endif;

		$this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = ue.user_id');

		$_e = $this->db->get(NAILS_DB_PREFIX . 'user_email ue')->row();

		if (!$_e) :

			$this->_set_error('Invalid Email.');
			return false;

		endif;

		if ($_e->is_verified) :

			$this->_set_error('Email is already verified.');
			return false;

		endif;

		// --------------------------------------------------------------------------

		$_email						= new stdClass();
		$_email->type				= 'verify_email_' . $_e->group_id;
		$_email->to_id				= $_e->user_id;
		$_email->data				= array();
		$_email->data['user_id']	= $_e->user_id;
		$_email->data['code']		= $_e->code;

		if (!$this->emailer->send($_email, true)) :

			//	Failed to send using the group email, try using the generic email template
			$_email->type = 'verify_email';

			if (!$this->emailer->send($_email, true)) :

				//	Email failed to send, for now, do nothing.
				$this->_set_error('The verification email failed to send.');
				return false;

			endif;

		endif;

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Deletes a non-primary email from the user_email table, optionally filtering
	 * by $user_id
	 * @param  mixed $email_id The email address, or the ID of the email address to remove
	 * @param  int $user_id    The ID of the user to restrict to
	 * @return bool            true on success, false on failure
	 */
	public function email_delete($email_id, $user_id = null)
	{
		if (is_numeric($email_id)) :

			$this->db->where('id', $email_id);

		else :

			$this->db->where('email', $email_id);

		endif;

		if (!empty($user_id)) :

			$this->db->where('user_id', $user_id);

		endif;

		$this->db->where('is_primary', false);
		$this->db->delete(NAILS_DB_PREFIX . 'user_email');

		if ((bool) $this->db->affected_rows()) {

			$this->setCacheUser($user_id);
			return true;

		} else {

			return false;
		}
	}


	// --------------------------------------------------------------------------


	/**
	 * Verifies whether the supplied $code is valid for the requested user ID or email
	 * address. If it is then the email is marked as verified.
	 * @param  mixed  $id_email The numeric ID of the user, or the email address
	 * @param  string $code     The verification code as generated by email_add()
	 * @return bool             true on successful verification, false on failure
	 */
	public function email_verify($idEmail, $code)
	{
		//	Check user exists
		if (is_numeric($idEmail)) {

			$user = $this->get_by_id($idEmail);

		} else {

			$user = $this->get_by_email($idEmail);
		}

		if (!$user) {

			$this->_set_error('User does not exist.');
			return false;
		}

		// --------------------------------------------------------------------------

		//	Check if email has already been verified
		$this->db->where('user_id', $user->id);
		$this->db->where('is_verified', true);
		$this->db->where('code', $code);

		if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_email')) {

			$this->_set_error('Email has already been verified.');
			return false;
		}

		// --------------------------------------------------------------------------

		//	Go ahead and set as verified
		$this->db->set('is_verified', true);
		$this->db->set('date_verified', 'NOW()', false);
		$this->db->where('user_id', $user->id);
		$this->db->where('is_verified', false);
		$this->db->where('code', $code);

		$this->db->update(NAILS_DB_PREFIX . 'user_email');

		if ((bool) $this->db->affected_rows()) {

			$this->setCacheUser($user->id);
			return true;

		} else {

			return false;
		}
	}


	// --------------------------------------------------------------------------


	/**
	 * Sets an email address as the primary email address for that user.
	 * @param  mixed $id_email The numeric  ID of the email address, or the email address itself
	 * @param  int   $user_id  Specify the user ID which this should apply to
	 * @return bool            true on success, false on failure
	 */
	public function email_make_primary($id_email, $user_id = null)
	{
		//	Fetch email
		$this->db->select('id,user_id,email');

		if (is_numeric($id_email)) :

			$this->db->where('id', $id_email);

		else :

			$this->db->where('email', $id_email);

		endif;

		if (!is_null($user_id)) :

			$this->db->where('user_id', $user_id);

		endif;

		$_email = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

		if (!$_email) :

			return false;

		endif;

		//	Update
		$this->db->trans_begin();

		$this->db->set('is_primary', false);
		$this->db->where('user_id', $_email->user_id);
		$this->db->update(NAILS_DB_PREFIX . 'user_email');

		$this->db->set('is_primary', true);
		$this->db->where('id', $_email->id);
		$this->db->update(NAILS_DB_PREFIX . 'user_email');

		$this->db->trans_complete();

		if ($this->db->trans_status() === false) :

			$this->db->trans_rollback();
			return false;

		else :

			$this->db->trans_commit();
			$this->setCacheUser($_email->user_id);
			return true;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Increase the user's failed login account by 1
	 *
	 * @access	public
	 * @param	int		$user_id	The ID of the user to increment
	 * @param	int		$expires	Time (in seconds) until expiration
	 * @return	void
	 **/
	public function increment_failed_login($user_id, $expires = 300)
	{
		$this->db->set('failed_login_count', '`failed_login_count`+1', false);
		$this->db->set('failed_login_expires', date('Y-m-d H:i:s', time() + $expires));
		$this->update($user_id);
	}


	// --------------------------------------------------------------------------


	/**
	 * Reset a user's failed login
	 *
	 * @access	public
	 * @param	int		$user_id	The ID of the user to reset
	 * @return	void
	 **/
	public function reset_failed_login($user_id)
	{
		$this->db->set('failed_login_count', 0);
		$this->db->set('failed_login_expires', 'null', false);
		$this->update($user_id);
	}


	// --------------------------------------------------------------------------


	/**
	 * Update a user's last login field
	 *
	 * @access	public
	 * @param	int		$user_id	The ID of the user to update
	 * @return	void
	 **/
	public function update_last_login($user_id)
	{
		$this->db->set('last_login', 'NOW()', false);
		$this->db->set('login_count', 'login_count+1', false);
		$this->update($user_id);
	}


	// --------------------------------------------------------------------------


	/**
	 * Set the user's 'remember me' cookie, nom nom nom
	 *
	 * @access	public
	 * @return	void
	 **/
	public function set_remember_cookie($id = null, $password = null, $email = null)
	{
		//	Is remember me functionality enabled?
		$this->config->load('auth/auth');

		if (!$this->config->item('auth_enable_remember_me')) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		if (!$id || !$password || !$email) :

			if (!active_user('id') ||  !active_user('password') || !active_user('email')) :

				return false;

			else :

				$id			= active_user('id');
				$password	= active_user('password');
				$email		= active_user('email');

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	Generate a code to remember the user by and save it to the DB
		$_salt = $this->encrypt->encode(sha1($id . $password . $email . APP_PRIVATE_KEY. time()), APP_PRIVATE_KEY);

		$this->db->set('remember_code', $_salt);
		$this->db->where('id', $id);
		$this->db->update(NAILS_DB_PREFIX . 'user');

		// --------------------------------------------------------------------------

		//	Set the cookie
		$_data				= array();
		$_data['name']		= $this->_remember_cookie;
		$_data['value']		= $email . '|' . $_salt;
		$_data['expire']	= 1209600; //	2 weeks

		set_cookie($_data);

		// --------------------------------------------------------------------------

		//	Update the flag
		$this->_is_remembered = true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Clears the user's remember me cookie
	 *
	 * @access	public
	 * @return	void
	 **/
	public function clear_remember_cookie()
	{
		$this->load->helper('cookie');

		// --------------------------------------------------------------------------

		delete_cookie($this->_remember_cookie);

		// --------------------------------------------------------------------------

		//	Update the flag
		$this->_is_remembered = false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Fetches a random security question for a specific user
	 *
	 * @access	public
	 * @param $user_id int The user's ID
	 * @return	stdClass
	 **/
	public function get_security_question($user_id)
	{
		$this->db->where('user_id', $user_id);
		$this->db->order_by('last_requested', 'DESC');
		$_questions = $this->db->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->result();

		if (!$_questions) :

			$this->_set_error('No security questions available for this user.');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Choose a question to return
		if (count($_questions) == 1) :

			//	No choice, just return the lonely question
			$_out = $_questions[0];

		elseif (count($_questions) > 1) :

			//	Has the most recently asked question been asked in the last 10 minutes?
			//	If so, return that one again (to make harvesting all the user's questions
			//	a little more time consuming). If not randomly choose one.

			if (strtotime($_questions[0]->last_requested) > strtotime('-10 MINS')) :

				$_out = $_questions[0];

			else :

				$_out = $_questions[array_rand($_questions)];

			endif;

		else :

			//	Derp.
			$this->_set_error('Could not determine security question.');
			return false;

		endif;

		//	Decode the question
		$_out->question = $this->encrypt->decode($_out->question, APP_PRIVATE_KEY . $_out->salt);

		$this->db->set('last_requested', 'NOW()', false);
		$this->db->set('last_requested_ip', $this->input->ip_address());
		$this->db->where('id', $_out->id);
		$this->db->update(NAILS_DB_PREFIX . 'user_auth_two_factor_question');

		return $_out;
	}


	// --------------------------------------------------------------------------


	public function validate_security_answer($question_id, $user_id, $answer)
	{
		$this->db->select('answer, salt');
		$this->db->where('id', $question_id);
		$this->db->where('user_id', $user_id);
		$_question = $this->db->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->row();

		if (!$_question) :

			return false;

		endif;

		$_hash = sha1(sha1(strtolower($answer)) . APP_PRIVATE_KEY . $_question->salt);

		return $_hash === $_question->answer;
	}


	// --------------------------------------------------------------------------


	/**
	 * Fetches a random security question for a specific user
	 *
	 * @access	public
	 * @param $user_id int The user's ID
	 * @return	stdClass
	 **/
	public function set_security_questions($user_id, $data, $clear_old = true)
	{
		//	Check input
		foreach ($data AS $d) :

			if (empty($d->question) || empty($d->answer)) :

				$this->_set_error('Malformed question/answer data.');
				return false;

			endif;

		endforeach;

		//	Begin transaction
		$this->db->trans_begin();

		//	Delete old questions?
		if ($clear_old) :

			$this->db->where('user_id', $user_id);
			$this->db->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_question');

		endif;

		$_data		= array();
		$_counter	= 0;

		foreach ($data AS $d) :

			$_data[$_counter]	= array();
			$_data[$_counter]['user_id']	= $user_id;
			$_data[$_counter]['salt']		= NAILS_User_password_model::salt();
			$_data[$_counter]['question']	= $this->encrypt->encode($d->question, APP_PRIVATE_KEY . $_data[$_counter]['salt']);
			$_data[$_counter]['answer']		= sha1(sha1(strtolower($d->answer)) . APP_PRIVATE_KEY . $_data[$_counter]['salt']);
			$_data[$_counter]['created']	= date('Y-m-d H:i:s');

			$_counter++;

		endforeach;

		if ($_data) :

			$this->db->insert_batch(NAILS_DB_PREFIX . 'user_auth_two_factor_question', $_data);

			if ($this->db->trans_status() !== false) :

				$this->db->trans_commit();
				return true;

			else :

				$this->db->trans_rollback();
				return false;

			endif;

		else :

			$this->db->trans_rollback();
			$this->_set_error('No data to save.');
			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Refreshes the user's session from database data
	 *
	 * @access	protected
	 * @return	void
	 *
	 **/
	protected function _refresh_session()
	{
		//	Get the user; be wary of admin's logged in as other people
		if ($this->was_admin()) :

			$_admin = $this->session->userdata('admin_recovery');

			if (!empty($_admin->logged_in_as)) :

				$_me = $_admin->logged_in_as;

			else :

				$_me = $this->session->userdata('id');

			endif;

		else :

			$_me = $this->session->userdata('id');

		endif;

		//	Is anybody home? Hello...?
		if (!$_me) :

			$_me = $this->_me;

			if (!$_me) :

				return false;

			endif;

		endif;

		$_me = $this->get_by_id($_me);

		// --------------------------------------------------------------------------

		//	If the user is isn't found (perhaps deleted) or has been suspended then
		//	obviously don't proceed with the log in

		if (!$_me || !empty($_me->is_suspended)) :

			$this->clear_remember_cookie();
			$this->clear_active_user();
			$this->clear_login_data();

			$this->_is_logged_in = false;

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Store this entire user in memory
		$this->set_active_user($_me);

		// --------------------------------------------------------------------------

		//	Set the user's logged in flag
		$this->_is_logged_in = true;

		// --------------------------------------------------------------------------

		//	Update user's `last_seen` and `last_ip` properties
		$this->db->set('last_seen', 'NOW()', false);
		$this->db->set('last_ip', $this->input->ip_address());
		$this->db->where('id', $_me->id);
		$this->db->update(NAILS_DB_PREFIX . 'user');
	}


	// --------------------------------------------------------------------------


	/**
	 * Create a new row in a user extended table
	 *
	 * @access	public
	 * @param	string	$table		The name of the table to insert to
	 * @param	object	$data		The data to insert
	 * @param	int		$user_id	If not updating the active user specify the user ID
	 * @return	mixed
	 **/
	public function extra_table_insert($table, $data, $user_id = false)
	{
		$_uid = !$user_id ? (int) active_user('id') : $user_id ;

		// --------------------------------------------------------------------------

		//	Unable to determine user ID
		if (!$_uid) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		$data			= (object) $data;
		$data->user_id	= $_uid;

		// --------------------------------------------------------------------------

		$this->db->insert($table, $data);

		// --------------------------------------------------------------------------

		return $this->db->affected_rows() ? $this->db->insert_id() : false ;
	}


	// --------------------------------------------------------------------------


	/**
	 * Update an extra user table
	 *
	 * @access	public
	 * @param	string	$table		The name of the table to fetch from
	 * @param	int		$id			The ID of the row to fetch
	 * @param	int		$user_id	If not fetching for the active user specify the user ID
	 * @return	boolean
	 **/
	public function extra_table_fetch($table, $id = null, $user_id = null)
	{
		$_uid = !$user_id ? (int) active_user('id') : $user_id ;

		// --------------------------------------------------------------------------

		//	Unable to determine user ID
		if (!$_uid) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		$this->db->where('user_id', $_uid);

		// --------------------------------------------------------------------------

		//	Add restriction if necessary
		if ($id) :

			$this->db->where('id', $id);

		endif;

		// --------------------------------------------------------------------------

		$_row = $this->db->get($table);

		return $id ? $_row->row() : $_row->result();
	}


	// --------------------------------------------------------------------------


	/**
	 * Update an extra user table
	 *
	 * @access	public
	 * @param	string	$table		The name of the table to update
	 * @param	object	$data		The data to update
	 * @param	int		$user_id	If not updating the active user specify the user ID
	 * @return	boolean
	 **/
	public function extra_table_update($table, $data, $user_id = false)
	{
		$_uid = !$user_id ? (int) active_user('id') : $user_id ;

		// --------------------------------------------------------------------------

		//	Unable to determine user ID
		if (!$_uid) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		$data = (object) $data;

		// --------------------------------------------------------------------------

		if (!isset($data->id) || empty($data->id)) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		$this->db->where('user_id', $_uid);
		$this->db->where('id', $data->id);
		$this->db->update($table, $data);

		// --------------------------------------------------------------------------

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Delete from an extra user table
	 *
	 * @access	public
	 * @param	string	$table		The name of the table to delete from
	 * @param	int		$id			The ID of the row to delete
	 * @param	int		$user_id	If not updating the active user specify the user ID
	 * @return	boolean
	 **/
	public function extra_table_delete($table, $id, $user_id = false)
	{
		$_uid = !$user_id ? (int) active_user('id') : $user_id ;

		// --------------------------------------------------------------------------

		//	Unable to determine user ID
		if (!$_uid) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		if (!isset($id) || empty($id)) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		$this->db->where('user_id', $_uid);
		$this->db->where('id', $id);
		$this->db->delete($table);

		// --------------------------------------------------------------------------

		return (bool) $this->db->affected_rows();
	}


	// --------------------------------------------------------------------------


	/**
	 * Create a new user
	 *
	 * @access	public
	 * @param	string	$data			An array of data to use for creating the user
	 * @param	boolean	$sendWelcome	Whether or not to send the welcome email or not
	 * @return	boolean
	 **/
	public function create($data = false, $sendWelcome = true)
	{
		//	Has an email or a username been submitted?
		if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

			//	Email defined?
			if (empty($data['email'])) {

				$this->_set_error('An email address must be supplied.');
				return false;
			}

			//	Check email against DB
			$this->db->where('email', $data['email']);
			if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_email')) {

				$this->_set_error('This email is already in use.');
				return false;
			}

		} elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

			//	Username defined?
			if (empty($data['username'])) {

				$this->_set_error('A username must be supplied.');
				return false;
			}

			if (!$this->isValidUsername($data['username'], true)) {

				return false;
			}

		} else {

			//	Both a username and an email must be supplied
			if (empty($data['email']) || empty($data['username'])) {

				$this->_set_error('An email address and a username must be supplied.');
				return false;
			}

			//	Check email against DB
			$this->db->where('email', $data['email']);
			if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_email')) {

				$this->_set_error('This email is already in use.');
				return false;
			}

			//	Check username
			if (!$this->isValidUsername($data['username'], true)) {

				return false;
			}
		}

		// --------------------------------------------------------------------------

		//	All should be ok, go ahead and create the account
		$_user_data = array();

		// --------------------------------------------------------------------------

		/**
		 * If a password has been passed then generate the encrypted strings, otherwise
		 * have a null password - the user won't be able to login and will be informed
		 * that they need to set a password using forgotten password.
		 */

		if (empty($data['password'])) :

			$_password = $this->user_password_model->generateNullHash();

			if (!$_password) :

				$this->_set_error($this->user_password_model->last_error());
				return false;

			endif;

		else :

			$_password = $this->user_password_model->generateHash($data['password']);

			if (!$_password) :

				$this->_set_error($this->user_password_model->last_error());
				return false;

			endif;

		endif;

		//	Do we need to inform the user of their password? This might be set
		//	if an admin created the account, or if the system generated a new password

		$_inform_user_pw = !empty($data['inform_user_pw']) ? true : false;

		// --------------------------------------------------------------------------

		//	Check that we're dealing with a valid group
		if (empty($data['group_id'])) :

			$_user_data['group_id'] = $this->user_group_model->getDefaultGroupId();

		else :

			$_user_data['group_id'] = $data['group_id'];

		endif;

		$_group = $this->user_group_model->get_by_id($_user_data['group_id']);

		if (!$_group) :

			$this->_set_error('Invalid Group ID specified.');
			return false;

		else :

			$_user_data['group_id'] = $_group->id;

		endif;

		// --------------------------------------------------------------------------

		if (!empty($data['username'])) :

			$_user_data['username'] = strtolower($data['username']);

		endif;

		if (!empty($data['email'])) :

			$_email				= $data['email'];
			$_email_is_verified	= !empty($data['email_is_verified']);

		endif;

		$_user_data['password']			= $_password->password;
		$_user_data['password_md5']		= $_password->password_md5;
		$_user_data['password_engine']	= $_password->engine;
		$_user_data['salt']				= $_password->salt;
		$_user_data['ip_address']		= $this->input->ip_address();
		$_user_data['last_ip']			= $_user_data['ip_address'];
		$_user_data['created']			= date('Y-m-d H:i:s');
		$_user_data['last_update']		= date('Y-m-d H:i:s');
		$_user_data['is_suspended']		= !empty($data['is_suspended']);
		$_user_data['temp_pw']			= !empty($data['temp_pw']);

		//	Referral code
		$_user_data['referral']			= $this->_generate_referral();

		//	Other data
		$_user_data['salutation']		= !empty($data['salutation'])	? $data['salutation']	: null ;
		$_user_data['first_name']		= !empty($data['first_name'])	? $data['first_name']	: null ;
		$_user_data['last_name']		= !empty($data['last_name'])		? $data['last_name']	: null ;

		if (isset($data['gender'])) :

			$_user_data['gender'] = $data['gender'];

		endif;

		if (isset($data['timezone'])) :

			$_user_data['timezone'] = $data['timezone'];

		endif;

		if (isset($data['datetime_format_date'])) :

			$_user_data['datetime_format_date'] = $data['datetime_format_date'];

		endif;

		if (isset($data['datetime_format_time'])) :

			$_user_data['datetime_format_time'] = $data['datetime_format_time'];

		endif;

		if (isset($data['language'])) :

			$_user_data['language'] = $data['language'];

		endif;

		// --------------------------------------------------------------------------

		//	Set Meta data
		$_meta_cols = $this->_get_meta_columns();
		$_meta_data	= array();

		foreach($data AS $key => $val) :

			if (array_search($key, $_meta_cols) !== false) :

				$_meta_data[$key] = $val;

			endif;

		endforeach;

		// --------------------------------------------------------------------------

		$this->db->trans_begin();

		$this->db->set($_user_data);

		if (!$this->db->insert(NAILS_DB_PREFIX . 'user')) :

			$this->_set_error('Failed to create base user object.');
			$this->db->trans_rollback();
			return false;

		endif;

		$_id = $this->db->insert_id();

		// --------------------------------------------------------------------------

		//	Update the user table with an MD5 hash of the user ID; a number of functions
		//	make use of looking up this hashed information; this should be quicker.

		$this->db->set('id_md5', md5($_id));
		$this->db->where('id', $_id);

		if (!$this->db->update(NAILS_DB_PREFIX . 'user')) :

			$this->_set_error('Failed to update base user object.');
			$this->db->trans_rollback();
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Create the user_meta record, add any extra data if needed
		$this->db->set('user_id', $_id);

		if ($_meta_data) :

			$this->db->set($_meta_data);

		endif;

		if (!$this->db->insert(NAILS_DB_PREFIX . 'user_meta')) :

			$this->_set_error('Failed to create user meta data object.');
			$this->db->trans_rollback();
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Finally add the email address to the user_email table
		if (!empty($_email)) :

			$_code = $this->email_add($_email, $_id, true, $_email_is_verified, false);

			if (!$_code) :

				//	Error will be set by email_add();
				$this->db->trans_rollback();
				return false;

			endif;

			//	Send the user the welcome email
			if ($sendWelcome) :

				$_email					= new stdClass();
				$_email->type			= 'new_user_' . $_group->id;
				$_email->to_id			= $_id;
				$_email->data			= array();

				//	If this user is created by an admin then take note of that.
				if ($this->is_admin()) :

					$_email->data['admin']				= new stdClass();
					$_email->data['admin']->id			= active_user('id');
					$_email->data['admin']->first_name	= active_user('first_name');
					$_email->data['admin']->last_name	= active_user('last_name');
					$_email->data['admin']->group		= new stdClass();
					$_email->data['admin']->group->id	= $_group->id;
					$_email->data['admin']->group->name	= $_group->label;

				endif;

				if (!empty($data['password']) && !empty($_inform_user_pw)) :

					$_email->data['password'] = $data['password'];

					//	Is this a temp password? We should let them know that too
					if ($_user_data['temp_pw']) :

						$_email->data['temp_pw'] = !empty($_user_data['temp_pw']);

					endif;

				endif;

				//	If the email isn't verified we'll want to include a note asking them to do so
				if (!$_email_is_verified) :

					$_email->data['verification_code']	= $_code;

				endif;

				if (!$this->emailer->send($_email, true)) :

					//	Failed to send using the group email, try using the generic email template
					$_email->type = 'new_user';

					if (!$this->emailer->send($_email, true)) :

						//	Email failed to send, musn't exist, oh well.
						$_error  = 'Failed to send welcome email.';
						$_error .= !empty($_inform_user_pw) ? ' Inform the user their password is <strong>' . $data['password'] . '</strong>' : '';

						$this->_set_error($_error);

					endif;

				endif;

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	commit the transaction and return new user object
		if ($this->db->trans_status() !== false) :

			$this->db->trans_commit();
			return $this->get_by_id($_id);

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Delete a user
	 *
	 * @access	public
	 * @param	int		$userId	The ID of the user to delete
	 * @return	boolean
	 **/
	public function destroy($userId)
	{
		$this->db->where('id', $userId);
		$this->db->delete(NAILS_DB_PREFIX . 'user');

		if ((bool) $this->db->affected_rows()) {

			$this->unsetCacheUser($userId);
			return true;

		} else {

			return false;
		}
	}


	// --------------------------------------------------------------------------


	/**
	 * Alias to destroy()
	 * @param  int $userId The ID of the user to delete
	 * @return boolean
	 */
	public function delete($userId)
	{
		return $this->destroy($userId);
	}


	// --------------------------------------------------------------------------


	/**
	 * Generate a valid referral code
	 *
	 * @access	protected
	 * @param	none
	 * @return	string
	 **/
	protected function _generate_referral()
	{
		$this->load->helper('string');

		// --------------------------------------------------------------------------

		while (1 > 0) :

			$referral = random_string('alnum', 8);
			$q = $this->db->get_where(NAILS_DB_PREFIX . 'user', array('referral' => $referral));
			if ($q->num_rows() == 0)
				break;

		endwhile;

		// --------------------------------------------------------------------------

		return $referral;
	}


	// --------------------------------------------------------------------------


	public function reward_referral($user_id, $referrer_id)
	{
		//	TODO
	}


	// --------------------------------------------------------------------------


	/**
	 * Suspend a user
	 *
	 * @access	public
	 * @param	int		$id	The ID of the user to suspend
	 * @return	boolean
	 **/
	public function suspend($id)
	{
		return $this->update($id, array('is_suspended' => true));
	}


	 // --------------------------------------------------------------------------


	/**
	 * Unsuspend a user
	 *
	 * @access	public
	 * @param	int		$id	The ID of the user to unsuspend
	 * @return	boolean
	 **/
	public function unsuspend($id)
	{
		return $this->update($id, array('is_suspended' => false));
	}


	// --------------------------------------------------------------------------

	public function isValidUsername($username, $checkDb = false, $ignoreUserId = null)
	{
		/**
		 * Check username doesn't contain invalid characters - we're actively looking
		 * for characters which are invalid so we can say "Hey!The following
		 * characters are invalid" rather than making the user guess, y'know, 'cause
		 * we're good guys.
		 */

		$invalidChars = '/[^a-zA-Z0-9\-_\.]/';

		//	Minimum length of the username
		$minLength = 2;

		// --------------------------------------------------------------------------

		//	Check for illegal characters
		$containsInvalidChars = preg_match($invalidChars, $username);

		if ($containsInvalidChars) {

			$msg = 'Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).';

			$this->_set_error($msg);
			return false;
		}

		// --------------------------------------------------------------------------

		//	Check length
		if (strlen($username) < $minLength) {

			$this->_set_error('Usernames msut be at least ' . $minLength . ' characters long.');
			return false;
		}

		// --------------------------------------------------------------------------

		if ($checkDb) {

			$this->db->where('username', $username);

			if (!empty($ignoreUserId)) {

				$this->db->where('id !=', $ignoreUserId);
			}

			if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user')) {

				$this->_set_error('Username is already in use.');
				return false;
			}
		}

		return true;
	}


	// --------------------------------------------------------------------------


	/**
	 * Merges users with id in $mergeIds into $userId
	 * @param  int   $userId   The user ID to keep
	 * @param  array $mergeIds An array of user ID's to merge into $userId
	 * @return boolean
	 */
	public function merge($userId, $mergeIds, $preview = false)
	{
		if (!is_numeric($userId)) {

			$this->_set_error('"userId" must be numeric.');
			return false;
		}

		if (!is_array($mergeIds)) {

			$this->_set_error('"mergeIDs" must be an array.');
			return false;
		}

		for($i=0; $i<count($mergeIds); $i++) {

			if (!is_numeric($mergeIds[$i])) {
				$this->_set_error('"mergeIDs" must contain only numerical values.');
				return false;
			}

			$mergeIds[$i] = $this->db->escape((int) $mergeIds[$i]);
		}

		if (in_array($userId, $mergeIds)) {

			$this->_set_error('"userId" cannot be listed as a merge user.');
			return false;
		}

		// --------------------------------------------------------------------------

		//	Look for tables which contain a user ID column in them.
		$userCols   = array();
		$userCols[] = 'user_id';
		$userCols[] = 'userId';
		$userCols[] = 'created_by';
		$userCols[] = 'createdBy';
		$userCols[] = 'modified_by';
		$userCols[] = 'modifiedBy';
		$userCols[] = 'author_id';
		$userCols[] = 'authorId';

		$userColsStr   = "'" . implode("','", $userCols) . "'";

		$ignoreTables   = array();
		$ignoreTables[] = NAILS_DB_PREFIX . 'user';
		$ignoreTables[] = NAILS_DB_PREFIX . 'user_meta';
		$ignoreTables[] = NAILS_DB_PREFIX . 'user_auth_two_factor_question';
		$ignoreTables[] = NAILS_DB_PREFIX . 'user_auth_two_factor_token';
		$ignoreTables[] = NAILS_DB_PREFIX . 'user_social';

		$ignoreTablesStr   = "'" . implode("','", $ignoreTables) . "'";

		$tables = array();
		$query	= " SELECT COLUMN_NAME,TABLE_NAME
					FROM INFORMATION_SCHEMA.COLUMNS
					WHERE COLUMN_NAME IN (" . $userColsStr . ")
					AND TABLE_NAME NOT IN (" . $ignoreTablesStr . ")
					AND TABLE_SCHEMA='" . DEPLOY_DB_DATABASE . "';";

		$result = $this->db->query($query);

		while($table = $result->_fetch_object()) {

			if (!isset($tables[$table->TABLE_NAME])) {

				$tables[$table->TABLE_NAME] = new stdClass();
				$tables[$table->TABLE_NAME]->name = $table->TABLE_NAME;
				$tables[$table->TABLE_NAME]->columns = array();
			}

			$tables[$table->TABLE_NAME]->columns[] = $table->COLUMN_NAME;
		}

		$tables = array_values($tables);

		//	Grab a count of the number of rows which will be affected
		for ($i=0; $i<count($tables); $i++) {

			$columnConditional = array();
			foreach ($tables[$i]->columns as $column) {

				$columnConditional[] = $column . ' IN (' . implode(',', $mergeIds) . ')';
			}

			$query	= 'SELECT COUNT(*) as numrows FROM  ' . $tables[$i]->name . ' WHERE ' . implode(' OR ', $columnConditional);
			$result = $this->db->query($query)->row();

			if (empty($result->numrows)) {

				$tables[$i] = null;

			} else {

				$tables[$i]->numRows = $result->numrows;
			}
		}

		$tables = array_values(array_filter($tables));

		// --------------------------------------------------------------------------

		if ($preview) {

			$out = new stdClass;
			$out->user = $this->get_by_id($userId);
			$out->merge = array();

			foreach($mergeIds AS $mergeUserId) {

				$out->merge[] = $this->get_by_id($mergeUserId);
			}

			$out->tables = $tables;
			$out->ignoreTables = $ignoreTables;

		} else {

			$this->db->trans_begin();

			//	For each table update the user columns
			for ($i=0; $i<count($tables); $i++) {

				foreach($tables[$i]->columns as $column) {

					//	Additional updates for certain tables
					switch($tables[$i]->name) {

						case NAILS_DB_PREFIX . 'user_email':

							$this->db->set('is_primary', false);
							break;
					}

					$this->db->set($column, $userId);
					$this->db->where_in($column, $mergeIds);
					if (!$this->db->update($tables[$i]->name)) {

						$errMsg = 'Failed to migrate column "' . $column . '" in table "' . $tables[$i]->name . '"';
						$this->_set_error($errMsg);
						$this->db->trans_rollback();
						return false;
					}
				}
			}

			//	Now delete each user
			for ($i=0; $i<count($mergeIds); $i++) {

				if (!$this->destroy($mergeIds[$i])) {

					$errMsg = 'Failed to delete user "' . $mergeIds[$i] . '" ';
					$this->_set_error($errMsg);
					$this->db->trans_rollback();
					return false;
				}
			}

			if ($this->db->trans_status() === false)
			{
				$this->db->trans_rollback();
				$out = false;

			} else {

				$this->db->trans_commit();
				$out = true;
			}
		}

		return $out;
	}


	// --------------------------------------------------------------------------


	/**
	 * Format a user object
	 *
	 * @access	protected
	 * @param	object	$user	The user object to format
	 * @param	array	$data	The data to use in the update
	 * @return	void
	 **/
	protected function _format_user_object(&$user, $minimal = false)
	{
		if ($minimal) :

			$user->id = (int) $user->id;

		else :

			//	Ints
			$user->id					= (int) $user->id;
			$user->group_id				= (int) $user->group_id;
			$user->login_count			= (int) $user->login_count;
			$user->referred_by			= (int) $user->referred_by;
			$user->failed_login_count	= (int) $user->failed_login_count;

			//	Bools
			$user->temp_pw				= (bool) $user->temp_pw;
			$user->is_suspended			= (bool) $user->is_suspended;
			$user->email_is_verified	= (bool) $user->email_is_verified;

			// --------------------------------------------------------------------------

			//	Tidy User meta
			unset($user->user_id);

		endif;
	}
}


// --------------------------------------------------------------------------


/**
 * OVERLOADING NAILS' MODELS
 *
 * The following block of code makes it simple to extend one of the core
 * models. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION_USER_MODEL')) :

	class User_model extends NAILS_User_model
	{
	}

endif;

/* End of file user_model.php */
/* Location: ./system/application/models/user_model.php */