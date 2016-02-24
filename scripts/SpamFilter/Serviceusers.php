<?php
class SpamFilter_Serviceusers extends SpamFilter_System
{
	var $_reportData;
	public function install()
	{
		parent::print_stderr("[Serviceuser] Install Start");
		$email 		= getenv('MAIL_account_EMAIL');
		$password 	= getenv('MAIL_account_PASSWORD') ? getenv('MAIL_account_PASSWORD') : getenv('SETTINGS_user_password');

		if(empty($email) || empty($password))
		{
			parent::print_stderr( "Either email or password is not set, but is required" );
			return false;
		}

		$split = $this->SplitDomain( $email );

		// Add user to the system
		return $this->addUser($split['domain'], $split['local_part'], $password);
	}

	public function configure()
	{
		parent::print_stderr("[Serviceuser] Configure Start");
		$email		= getenv('MAIL_account_EMAIL') ? getenv('MAIL_account_EMAIL') : getenv('SETTINGS_user_login');
		$password   = getenv('MAIL_account_PASSWORD') ? getenv('MAIL_account_PASSWORD') : getenv('SETTINGS_user_password');

		if(empty($email) || empty($password))
		{
			parent::print_stderr( "Either email or password is not set, but is required" );
			return false;
		}

		// Check whether the email address changed
		$new_email = getenv('MAIL_account_EMAIL');
		$old_email = getenv('SETTINGS_user_login');
		if ( ($new_email <> $old_email) && !empty($new_email))
		{
			// new email - MAIL_account_EMAIL
			// old email - SETTINGS_user_login
			parent::print_stderr( "Email address changed: removing old user.." );
			// Email address changed, remove the one from SETTINGS_user_login.
			$this->delUser( $old_email );

			// Add the one with MAIL_account_EMAIL
			parent::print_stderr( "Email address changed: Creating new user.." );
			$split = $this->SplitDomain( $new_email );

			// Do not handle password changes, since the users have changed.
			return $this->addUser($split['domain'], $split['local_part'], $password);
		}
		// Apparantly the email did not change, so its just the password.
		parent::print_stderr( "Updating password for '{$email}'" );
		return $this->setPassword($email, $password);
	}

	public function remove()
	{
		parent::print_stderr("[Serviceuser] Remove Start");
		$email		= getenv('MAIL_account_EMAIL') ? getenv('MAIL_account_EMAIL') : getenv('SETTINGS_user_login');
		if(empty($email))
		{
			parent::print_stderr("No emailaccount provided to remove.");
			return false;
		}
		return $this->delUser( $email );
	}

	public function getAmount()
	{
		$nu = getenv('RESOURCES_NUM_USERS');
		return ((!isset($nu)) || (empty($nu))) ? 0 : $nu;
	}

	private function setPassword($email, $password)
	{
		$api = parent::getApi();
		if( isset($api) )
		{
			// Proceed
			$status = $api->emailusers()->setpassword(array(
									'username' 	=> $email,
									'password' 	=> $password
								));

			if( (!isset($status)) || (!is_array($status)) || (!$status['status']) )
			{
				parent::print_stderr("Changing password for email user '{$email}' failed. Response: " . serialize( $status ) );

                /** @see https://trac.provider.url/ticket/17032 */
                return (isset($status['additional'][0]) && false !== strpos(strtolower($status['additional'][0]), 'unable to find user'));
			}

			if ( $status['status'] )
			{
				## Completed!
				parent::print_stderr("Changing password for email user '{$email}' has been completed succesfully.");

				## Report back to POA
				$report_settings = array();
				$report_settings['user_login'] 		= $email;
				$report_settings['user_password'] 	= $password;
				$report_settings['RESOURCES_NUM_USERS']	= $this->getAmount() + 1;
				parent::report_settings( $report_settings );
				return true;
			}
		}
		parent::print_stderr("The API is not available");
		return false;
	}

	private function addUser($domain, $local_part, $password)
	{
		$api = parent::getApi();
		if( isset($api) )
		{
			// Build an email address based on local_part and domain.
			$email = $local_part . "@" . $domain;

			// Proceed
			$status = $api->emailusers()->add(array(
								'username' 	=> $local_part,
								'password' 	=> $password,
								'domain' 	=> $domain,
								));

			if( (!isset($status)) || (!is_array($status)) || (!$status['status']) )
			{
				## Failed.
				parent::print_stderr("Adding email user '{$email}' failed. Response: " . serialize( $status ) );
				return false;
			}

			if ( $status['status'] )
			{
				## Completed!
				parent::print_stderr("Adding email user '{$email}' has been completed succesfully.");

				## Report back to POA
				$report_settings = array();
				$report_settings['user_login'] 		= $email;
				$report_settings['user_password'] 	= $password;
				$report_settings['RESOURCES_NUM_USERS']	= $this->getAmount() + 1;
				parent::report_settings( $report_settings );
				return true;
			}
		}
		parent::print_stderr("The API is not available");
		return false;
	}

	private function delUser( $email )
	{
		$api = parent::getApi();
		if( isset($api) )
		{
			$status = $api->emailusers()->remove(array(
									'username' => $email
								));
			if( (!isset($status)) || (!is_array($status)) || (!$status['status']) )
			{
				parent::print_stderr("Removing email user '{$email}' failed. Response: " . serialize( $status ) );
				return false;
			}

			if ( $status['status'] )
			{
				## Completed!
				parent::print_stderr("Removing email user '{$email}' has been completed succesfully.");
				return true;
			}
		}
		parent::print_stderr("The API is not available");
		return false;
	}

	private function SplitDomain($email)
	{
		// Separate local part from domain, we need both.
		$x 		= explode("@", $email);
		$domain		= $x[1];
		$local_part	= $x[0];

		return array(
				'local_part' => $x[0],
				'domain' => $x[1]
			    );
	}
}
