<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_auth.php';

/**
 * User login facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Login extends NAILS_Auth_Controller
{
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------------------------------------

		//	Load libraries
		$this->load->library('form_validation');
		$this->load->library('auth/social_signon');

		// --------------------------------------------------------------------------

		//	Where are we returning user to?
		$_return_to = $this->input->get('return_to');

		if ($_return_to) :

			$_return_to = preg_match('#^(http|https)\://#', $_return_to) ? $_return_to : site_url($_return_to);
			$_return_to = parse_url($_return_to);

			//	urlencode the query if there is one
			if (!empty($_return_to['query'])) :

				//	Break it apart and glue it together (urlencoded)
				$_query = parse_str($_return_to['query'], $_query_ar);
				$_return_to['query'] = http_build_query($_query_ar);

			endif;

			$this->data['return_to']  = '';
			$this->data['return_to'] .= !empty($_return_to['scheme'])	? $_return_to['scheme'] . '://'	: 'http://';
			$this->data['return_to'] .= !empty($_return_to['host'])		? $_return_to['host']			: site_url();
			$this->data['return_to'] .= !empty($_return_to['path'])		? $_return_to['path']			: '';
			$this->data['return_to'] .= !empty($_return_to['query'])		? '?' . $_return_to['query']	: '';

		else :

			$this->data['return_to'] = '';

		endif;

		// --------------------------------------------------------------------------

		//	Specify a default title for this page
		$this->data['page']->title = lang('auth_title_login');
	}


	// --------------------------------------------------------------------------


	/**
	 * Validate data and log the user in.
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function index()
	{
		//	If you're logged in you shouldn't be accessing this method
		if ($this->user_model->is_logged_in()) :

			$this->session->set_flashdata('error', lang('auth_no_access_already_logged_in', active_user('email')));
			redirect($this->data['return_to']);

		endif;

		// --------------------------------------------------------------------------

		//	If there's POST data attempt to log user in
		if ($this->input->post()) :

			//	Validate input

			//	The rules vary depending on what login methods are enabled.
			switch(APP_NATIVE_LOGIN_USING) :

				case 'EMAIL' :

					$this->form_validation->set_rules('identifier',	'Email',	'required|xss_clean|trim|valid_email');

				break;

				// --------------------------------------------------------------------------

				case 'USERNAME' :

					$this->form_validation->set_rules('identifier',	'Username',	'required|xss_clean|trim');

				break;

				// --------------------------------------------------------------------------

				default:

					$this->form_validation->set_rules('identifier',	'Username or Email',	'xss_clean|trim');

				break;

			endswitch;

			//	Password is always required, obviously.
			$this->form_validation->set_rules('password',	'Password',	'required|xss_clean');
			$this->form_validation->set_message('required',	lang('fv_required'));
			$this->form_validation->set_message('valid_email',	lang('fv_valid_email'));

			if ($this->form_validation->run()) :

				//	Attempt the log in
				$_identifier	= $this->input->post('identifier');
				$_password		= $this->input->post('password');
				$_remember		= (bool) $this->input->post('remember');

				$_user = $this->auth_model->login($_identifier, $_password, $_remember);

				if ($_user) :

					$this->_login($_user, $_remember);

				else :

					//	Login failed
					$this->data['error'] = $this->auth_model->last_error();

				endif;

			else :

				$this->data['error'] = lang('fv_there_were_errors');

			endif;

		endif;

		// --------------------------------------------------------------------------

		$this->data['social_signon_enabled']	= $this->social_signon->is_enabled();
		$this->data['social_signon_providers']	= $this->social_signon->get_providers('ENABLED');

		// --------------------------------------------------------------------------

		//	Load the views
		$this->load->view('structure/header',	$this->data);
		$this->load->view('auth/login/form',	$this->data);
		$this->load->view('structure/footer',	$this->data);
	}


	// --------------------------------------------------------------------------


	protected function _login($user, $remember = FALSE, $provider = 'native')
	{
		if ($user->is_suspended) :

			$this->data['error'] = lang('auth_login_fail_suspended');
			return FALSE;

		elseif (!empty($user->temp_pw)) :

			/**
			 * Temporary password detected, log user out and redirect to
			 * temp password reset page.
			 *
			 * temp_pw will be an array containing the user's ID and hash
			 *
			 **/

			$_query	= array();

			if ($this->data['return_to']) :

				$_query['return_to'] = $this->data['return_to'];

			endif;

			//	Log the user out and remove the 'remember me' cookie - if we don't do this then the password reset
			//	page will see a logged in user and go nuts (i.e error).

			if ($remember) :

				$_query['remember'] = TRUE;

			endif;

			$_query = $_query ? '?' . http_build_query($_query) : '';

			$this->auth_model->logout();

			redirect('auth/reset_password/' . $user->id . '/' . md5($user->salt) . $_query);

		elseif ($this->config->item('authTwoFactorMode')) :

			//	Generate token
			$twoFactorToken = $this->auth_model->mfaTokenGenerate($user->id);

			if (!$twoFactorToken) {

				$subject = 'Failed to generate two-factor auth token';
				$message = 'A user tried to login and the system failed to generate a two-factor auth token.';
				showFatalError($subject, $message);
			}

			//	Is there any query data?
			$query	= array();

			if ($this->data['return_to']) {

				$query['return_to'] = $this->data['return_to'];
			}

			if ($remember) {

				$query['remember'] = true;
			}

			$query = $query ? '?' . http_build_query($query) : '';

			//	Where we sending the user?
			switch ($this->config->item('authTwoFactorMode')) {

				case 'QUESTION':

					$controller = 'mfa_question';
					break;

				case 'DEVICE':

					$controller = 'mfa_device';
					break;
			}

			//	Compile the URL
			$url = array(
				'auth',
				$controller,
				$user->id,
				$twoFactorToken['salt'],
				$twoFactorToken['token']
			);

			$url = implode($url, '/') . $query;

			//	Login was successful, redirect to the appropriate MFA page
			redirect($url);

		else :

			//	Finally! Send this user on their merry way...
			if ($user->last_login) :

				$this->load->helper('date');

				$_last_login = $this->config->item('auth_show_nicetime_on_login') ? nice_time(strtotime($user->last_login)) : user_datetime($user->last_login);

				if ($this->config->item('auth_show_last_ip_on_login')) :

					$this->session->set_flashdata('message', lang('auth_login_ok_welcome_with_ip', array($user->first_name, $_last_login, $user->last_ip)));

				else :

					$this->session->set_flashdata('message', lang('auth_login_ok_welcome', array($user->first_name, $_last_login)));

				endif;

			else :

				$this->session->set_flashdata('message', lang('auth_login_ok_welcome_notime', array($user->first_name)));

			endif;

			$_redirect = $this->data['return_to'] ? $this->data['return_to'] : $user->group_homepage;

			// --------------------------------------------------------------------------

			//	Generate an event for this log in
			create_event('did_log_in', array('provider' => $provider), $user->id);

			// --------------------------------------------------------------------------

			redirect($_redirect);

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Log a user in using hashes of their user ID and password; easy way of
	 * automatically logging a user in from the likes of an email.
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function with_hashes()
	{
		if (!$this->config->item('auth_enable_hashed_login')) :

			show_404();

		endif;

		// --------------------------------------------------------------------------

		$_hash['id']	= $this->uri->segment(4);
		$_hash['pw']	= $this->uri->segment(5);

		if (empty($_hash['id']) || empty($_hash['pw'])) :

			show_error($lang['auth_with_hashes_incomplete_creds']);

		endif;

		// --------------------------------------------------------------------------

		/**
		 * If the user is already logged in we need to check to see if we check to see if they are
		 * attempting to login as themselves, if so we redirect, otherwise we log them out and try
		 * again using the hashes.
		 *
		 **/
		if ($this->user_model->is_logged_in()) :

			if (md5(active_user('id')) == $_hash['id']) :

				//	We are attempting to log in as who we're already logged in as, redirect normally
				if ($this->data['return_to']) :

					redirect($this->data['return_to']);

				else :

					//	Nowhere to go? Send them to their default homepage
					redirect(active_user('group_homepage'));

				endif;

			else :

				//	We are logging in as someone else, log the current user out and try again
				$this->auth_model->logout();

				redirect(preg_replace('/^\//', '', $_SERVER['REQUEST_URI']));

			endif;

		endif;

		// --------------------------------------------------------------------------

		/**
		 * The active user is a guest, we must look up the hashed user and log them in
		 * if all is ok otherwise we report an error.
		 *
		 **/

		$_user = $this->user_model->get_by_hashes($_hash['id'], $_hash['pw']);

		// --------------------------------------------------------------------------

		if ($_user) :

			//	User was verified, log the user in
			$this->user_model->set_login_data($_user->id);

			// --------------------------------------------------------------------------

			//	Say hello
			if ($_user->last_login) :

				$this->load->helper('date');

				$_last_login = $this->config->item('auth_show_nicetime_on_login') ? nice_time(strtotime($_user->last_login)) : user_datetime($_user->last_login);

				if ($this->config->item('auth_show_last_ip_on_login')) :

					$this->session->set_flashdata('message', lang('auth_login_ok_welcome_with_ip', array($_user->first_name, $_last_login, $_user->last_ip)));

				else :

					$this->session->set_flashdata('message', lang('auth_login_ok_welcome', array($_user->first_name, $_user->last_login)));

				endif;

			else :

				$this->session->set_flashdata('message', lang('auth_login_ok_welcome_notime', array($_user->first_name)));

			endif;

			// --------------------------------------------------------------------------

			//	Update their last login
			$this->user_model->update_last_login($_user->id);

			// --------------------------------------------------------------------------

			//	Redirect user
			if ($this->data['return_to'] != site_url()) :

				//	We have somewhere we want to go
				redirect($this->data['return_to']);

			else :

				//	Nowhere to go? Send them to their default homepage
				redirect($_user->group_homepage);

			endif;

		else :

			//	Bad lookup, invalid hash.
			$this->session->set_flashdata('error', lang('auth_with_hashes_autologin_fail'));
			redirect($this->data['return_to']);

		endif;
	}


	// --------------------------------------------------------------------------


	protected function _social_signon($provider)
	{
		//	Get the adapter, HybridAuth will handle the redirect
		$_adapter	= $this->social_signon->authenticate($provider);
		$_provider	= $this->social_signon->get_provider($provider);

		// --------------------------------------------------------------------------

		//	Fetch the user's social profile and, if one exists, the local profile.
		try
		{
			$_social_user = $_adapter->getUserProfile();
		}
		catch(Exception $e)
		{
			//	Failed to fetch from the provider, something must have gone wrong
			log_message('error', 'HybridAuth failed to fetch data from provider.');
			log_message('error', 'Error Code: ' . $e->getCode());
			log_message('error', 'Error Message: ' . $e->getMessage());

			if (empty($_provider)) :

				$this->session->set_flashdata('error', '<strong>Sorry,</strong> there was a problem communicating with the network.');

			else :

				$this->session->set_flashdata('error', '<strong>Sorry,</strong> there was a problem communicating with ' . $_provider['label'] . '.');

			endif;

			if ($this->uri->segment(4) == 'register') :

				$_redirect = 'auth/register';

			else :

				$_redirect = 'auth/login';

			endif;

			if ($this->data['return_to']) :

				$_redirect .= '?return_to=' . urlencode($this->data['return_to']);

			endif;

			redirect($_redirect);
		}

		$_user = $this->social_signon->get_user_by_provider_identifier($provider, $_social_user->identifier);

		// --------------------------------------------------------------------------

		/**
		 * See if we already know about this user, react accordingly.
		 * If a user already exists for this provder/identifier then it's logical
		 * to spok them in, I mean, log them in - provided of course they aren't
		 * already logged in, if they are then silly user. If no user is recognised
		 * then we need to register them, providing, of course that registration is
		 * enabled and that no one else on the system has their email address.
		 * On that note, we need to respect APP_NATIVE_LOGIN_USING; if the provider
		 * cannot satisfy this then we'll need to interrupt registration and ask them
		 * for either a username or an email (or both).
		 **/

		if ($_user) :

			if ($this->user_model->is_logged_in() && active_user('id') == $_user->id) :

				//	Logged in user is already logged in and is the social user.
				//	Silly user, just redirect them to where they need to go.

				$this->session->set_flashdata('message', lang('auth_social_already_linked', $_provider['label']));

				if ($this->data['return_to']) :

					redirect($this->data['return_to']);

				else :

					redirect($_user->group_homepage);

				endif;

			elseif ($this->user_model->is_logged_in() && active_user('id') != $_user->id) :

				//	Hmm, a user was found for this Provider ID, but it's not the
				//	actively logged in user. This means that this provider account
				//	is already registered with us

				$this->session->set_flashdata('error', lang('auth_social_account_in_use', array($_provider['label'], APP_NAME)));

				if ($this->data['return_to']) :

					redirect($this->data['return_to']);

				else :

					redirect($_user->group_homepage);

				endif;

			else :

				//	Fab, user exists, try to log them in
				$this->user_model->set_login_data($_user->id);
				$this->social_signon->save_session($_user->id);

				if (!$this->_login($_user)) :

					$this->session->set_flashdata('error', $this->data['error']);

					$_redirect = 'auth/login';

					if ($this->data['return_to']) :

						$_redirect .= '?return_to=' . urlencode($this->data['return_to']);

					endif;

					redirect($_redirect);

				endif;

			endif;

		elseif ($this->user->is_logged_in()) :

			//	User is logged in and it look's like the provider isn't being used by anyone
			//	else. Go ahead and link the two accounts together.

			if ($this->social_signon->save_session(active_user('id'), $provider)) :

				create_event('did_link_provider',array('provider' => $provider));
				$this->session->set_flashdata('success', lang('auth_social_linked_ok', $_provider['label']));

			else :

				$this->session->set_flashdata('error', lang('auth_social_linked_fail', $_provider['label']));

			endif;

			redirect($this->data['return_to']);

		else :

			/**
			 * Didn't find a user and the active user isn't logged in, assume they want
			 * to regster an account. I mean, who wouldn't, this site is AwEsOmE.
			 */

			if (app_setting('user_registration_enabled', 'app')) :

				$_required_data = array();
				$_optional_data = array();

				//	Fetch required data
				switch(APP_NATIVE_LOGIN_USING) :

					case 'EMAIL' :

						$_required_data['email'] = trim($_social_user->email);

					break;

					case 'USERNAME' :

						$_required_data['username'] = !empty($_social_user->username) ? trim($_social_user->username) : '';

					break;

					default :

						$_required_data['email']	= trim($_social_user->email);
						$_required_data['username'] = !empty($_social_user->username) ? trim($_social_user->username) : '';

					break;

				endswitch;

				$_required_data['first_name']	= trim($_social_user->firstName);
				$_required_data['last_name']	= trim($_social_user->lastName);

				//	And any optional data
				if (checkdate($_social_user->birthMonth, $_social_user->birthDay, $_social_user->birthYear)) :

					$_optional_data['dob']			= array();
					$_optional_data['dob']['year']	= trim($_social_user->birthYear);
					$_optional_data['dob']['month']	= str_pad(trim($_social_user->birthMonth), 2, 0, STR_PAD_LEFT);
					$_optional_data['dob']['day']	= str_pad(trim($_social_user->birthDay), 2, 0, STR_PAD_LEFT);
					$_optional_data['dob']			= implode('-', $_optional_data['dob']);

				endif;

				switch($_social_user->gender) :

					case 'male' :

						$_optional_data['gender'] = 'MALE';

					break;

					case 'female' :

						$_optional_data['gender'] = 'FEMALE';

					break;

				endswitch;

				// --------------------------------------------------------------------------

				//	If any required fields are missing then we need to interrupt the
				//	registration flow and ask for them

				if (count($_required_data) !== count(array_filter($_required_data))) :

					//	TODO: One day work out a way of doing this so that we don't need to
					//	call the API again etc, uses unnessecary calls. Then again, maybe it
					//	*is* necessary.

					$this->_request_data($_required_data, $provider);

				endif;

				//	We have everything we need to create the user account
				//	However, first we need to make sure that our data is valid
				//	and not in use.At this point it's not the user's fault so
				//	don't throw an error.

				//	Check email
				if (isset($_required_data['email'])) :

					$_check = $this->user_model->get_by_email($_required_data['email']);

					if ($_check) :

						$_required_data['email'] = '';
						$_request_data			= TRUE;

					endif;

				endif;

				// --------------------------------------------------------------------------

				if (isset($_required_data['username'])) :

					//	Username was set using provider provided username, check it's valid
					//	if not, then request one. At this point it's not the user's fault so
					//	don't throw an error.

					$_check = $this->user_model->get_by_username($_required_data['username']);

					if ($_check) :

						$_required_data['username']	= '';
						$_request_data				= TRUE;

					endif;

				else :

					//	No username, make one up for them, try to use the social_user
					//	username (as it might not have been set above), failing that
					//	use the user's name, failing THAT use a random string

					if (!empty($_social_user->username)) :

						$_username = $_social_user->username;

					elseif($_required_data['first_name'] || $_required_data['last_name']) :

						$_username = $_required_data['first_name'] . ' ' . $_required_data['last_name'];

					else :

						$_username = 'user' . date('YmdHis');

					endif;

					$_basename = url_title($_username, '-', TRUE);
					$_required_data['username'] = $_basename;

					$_user = $this->user_model->get_by_username($_required_data['username']);

					while ($_user) :

						$_required_data['username']  = increment_string($_basename, '');
						$_user = $this->user_model->get_by_username($_required_data['username']);

					endwhile;

				endif;

				// --------------------------------------------------------------------------

				//	Request data?
				if (!empty($_request_data)) :

					$this->_request_data($_required_data, $provider);

				endif;

				// --------------------------------------------------------------------------

				//	Handle referrals
				if ($this->session->userdata('referred_by')) :

					$_optional_data['referred_by'] = $this->session->userdata('referred_by');

				endif;

				// --------------------------------------------------------------------------

				//	Merge data arrays
				$_data = array_merge($_required_data, $_optional_data);

				// --------------------------------------------------------------------------

				//	Create user
				$_new_user = $this->user_model->create($_data);

				if ($_new_user) :

					//	Welcome aboard, matey
					//	Save provider details
					//	Upload profile image if available

					$this->social_signon->save_session($_new_user->id, $provider);

					if (!empty($_social_user->photoURL)) :

						//	Has profile image
						$_img_url = $_social_user->photoURL;

					elseif (!empty($_new_user->email)) :

						//	Attempt gravatar
						$_img_url = 'http://www.gravatar.com/avatar/' . md5($_new_user->email) . '?d=404&s=2048&r=pg';

					endif;

					if (!empty($_img_url)) :

						//	Fetch the image
						$_ch = curl_init();
						curl_setopt($_ch, CURLOPT_RETURNTRANSFER, TRUE);
						curl_setopt($_ch, CURLOPT_FOLLOWLOCATION, TRUE);
						curl_setopt($_ch, CURLOPT_URL, $_img_url);
						$_img_data = curl_exec($_ch);

						if (curl_getinfo($_ch, CURLINFO_HTTP_CODE) === 200) :

							//	Attempt upload
							$this->load->library('cdn/cdn');

							//	Save file to cache
							$_cache_file = DEPLOY_CACHE_DIR . 'new-user-profile-image-' . $_new_user->id;

							if (@file_put_contents($_cache_file, $_img_data)) :

								$_upload = $this->cdn->object_create($_cache_file, 'profile-images', array());

								if ($_upload) :

									$_data					= array();
									$_data['profile_img']	= $_upload->id;

									$this->user_model->update($_new_user->id, $_data);

								else :

									log_message('debug', 'Failed to uload user\'s profile image');
									log_message('debug', $this->cdn->last_error());

								endif;

							endif;

						endif;

					endif;

					// --------------------------------------------------------------------------

					//	Aint that swell, all registered!Redirect!
					$this->user_model->set_login_data($_new_user->id);

					// --------------------------------------------------------------------------

					//	Create an event for this event
					create_event('did_register', array('method' => $provider),$_new_user->id);

					// --------------------------------------------------------------------------

					//	Redirect
					$this->session->set_flashdata('success', lang('auth_social_register_ok', $_new_user->first_name));

					//	Registrations will be forced to the registration redirect, regardless of
					//	what else has been set

					$_group		= $this->user_group_model->get_by_id($_new_user->group_id);
					$_redirect	= $_group->registration_redirect ? $_group->registration_redirect : $_group->default_homepage;

					redirect($_redirect);

				else :

					//	Oh dear, something went wrong
					$this->session->set_flashdata('error', '<strong>Sorry,</strong> something went wrong and your account could not be created.');

					$_redirect = 'auth/login';

					if ($this->data['return_to']) :

						$_redirect .= '?return_to=' . urlencode($this->data['return_to']);

					endif;

					redirect($_redirect);

				endif;

			else :

				//	How unfortunate, registration is disabled. Redrect back to the login page
				$this->session->set_flashdata('error', lang('auth_social_register_disabled'));

				$_redirect = 'auth/login';

				if ($this->data['return_to']) :

					$_redirect .= '?return_to=' . urlencode($this->data['return_to']);

				endif;

				redirect($_redirect);

			endif;

		endif;

	}


	// --------------------------------------------------------------------------


	protected function _request_data(&$required_data, $provider)
	{
		if ($this->input->post()) :

			if (isset($required_data['email'])) :

				$this->form_validation->set_rules('email', 'email', 'xss_clean|trim|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]');

			endif;

			if (isset($required_data['username'])) :

				$this->form_validation->set_rules('username', 'username', 'xss_clean|trim|required|is_unique[' . NAILS_DB_PREFIX . 'user.username]');

			endif;

			if (empty($required_data['first_name'])) :

				$this->form_validation->set_rules('first_name', '', 'xss_clean|trim|required');

			endif;

			if (empty($required_data['last_name'])) :

				$this->form_validation->set_rules('last_name', '', 'xss_clean|trim|required');

			endif;

			$this->form_validation->set_message('required',	lang('fv_required'));
			$this->form_validation->set_message('valid_email',	lang('fv_valid_email'));

			if (APP_NATIVE_LOGIN_USING == 'EMAIL') :

				$this->form_validation->set_message('is_unique',	lang('fv_email_already_registered', site_url('auth/forgotten_password')));

			elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') :

				$this->form_validation->set_message('is_unique',	lang('fv_username_already_registered', site_url('auth/forgotten_password')));

			else :

				$this->form_validation->set_message('is_unique',	lang('fv_identity_already_registered', site_url('auth/forgotten_password')));

			endif;

			$this->load->library('form_validation');

			if ($this->form_validation->run()) :

				//	Valid!Ensure required data is set correctly then allow system to move on.
				if (isset($required_data['email'])) :

					$required_data['email'] = $this->input->post('email');

				endif;

				if (isset($required_data['username'])) :

					$required_data['username'] = $this->input->post('username');

				endif;

				if (empty($required_data['first_name'])) :

					$required_data['first_name'] = $this->input->post('first_name');

				endif;

				if (empty($required_data['last_name'])) :

					$required_data['last_name'] = $this->input->post('last_name');

				endif;

			else :

				$this->data['error'] = lang('fv_there_were_errors');
				$this->_required_data_form($required_data, $provider);

			endif;

		else :

			$this->_required_data_form($required_data, $provider);

		endif;
	}


	// --------------------------------------------------------------------------


	protected function _required_data_form(&$required_data, $provider)
	{
		$this->data['required_data']	= $required_data;
		$this->data['form_url']			= 'auth/login/' . $provider;

		if ($this->uri->segment(4) == 'register') :

			$this->data['form_url'] .= '/register';

		endif;

		if ($this->data['return_to']) :

			$this->data['form_url'] .= '?return_to=' . urlencode($this->data['return_to']);

		endif;

		$this->load->view('structure/header',					$this->data);
		$this->load->view('auth/register/social_request_data',	$this->data);
		$this->load->view('structure/footer',					$this->data);
		echo $this->output->get_output();
		exit();
	}


	// --------------------------------------------------------------------------


	public function _remap()
	{
		$_method = $this->uri->segment(3) ? $this->uri->segment(3) : 'index';

		if (method_exists($this, $_method) && substr($_method, 0, 1) != '_') :

			$this->{$_method}();

		else :

			//	Assume the 3rd segment is a login provider supported by Hybrid Auth
			if ($this->social_signon->is_valid_provider($_method)) :

				$this->_social_signon($_method);

			else :

				show_404();

			endif;

		endif;
	}
}


// --------------------------------------------------------------------------


/**
 * OVERLOADING NAILS' AUTH MODULE
 *
 * The following block of code makes it simple to extend one of the core auth
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
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

if (!defined('NAILS_ALLOW_EXTENSION')) :

	class Login extends NAILS_Login
	{
	}

endif;
