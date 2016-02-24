<?php

/**
 * Class SpamFilter_Domains
 */
class SpamFilter_Domains extends SpamFilter_System
{
    public $_reportData;
    public $_aProvisionedEmail;
    public $_aProvisionedPass;
    public $_aRemovedDomains = array();

    /**
     * @return bool
     * @throws InvalidArgumentException
     */
    public function install()
    {
        parent::print_stderr("[Domains] Install Start");
        $this->_reportData = array();

        $email = getenv('SETTINGS_email');

        $completedDomains = array();

        $domains = $this->getProvisionDomains();
        $oldDomains = $this->getOldDomains();

        parent::print_stderr("Processing all new domains.");

        $idn = new IDNA_Convert;
        // Process all new domains
        foreach ($domains as $domain => $data) {

            //Force lowercase domains for POA when adding
            //@see https://trac.spamexperts.com/software/ticket/18360
            $domain = mb_strtolower(trim($domain), 'UTF-8');

            // properly handle IDN domain
            //@see https://trac.spamexperts.com/software/ticket/18496
            $domain = ((0 === strpos($domain, 'xn--')) ? $idn->decode($domain) : $domain);

            // Check if they aren't listed already, this means they are already protected
            // So we can skip those.
            if (!isset($oldDomains[$domain])) {
                if (isset($data['hasmxrecord']) && (!$data['hasmxrecord'])) {
                    parent::print_stderr("Skipping domain '{$domain}' due to missing destination record(s).");
                    continue;
                }

                // Add a new domain
                if (!empty($data['mx'])) {
                    parent::print_stderr("Creating domain '{$domain}' with email: '{$email}' and destination: '{$data['mx']}'");
                    $rv = $this->addDomain(
                        $domain,
                        $email,
                        $data['mx']
                    );
                    if ($rv == true) {
                        // Completed, add to list
                        parent::print_stderr("Domain '{$domain}' was added to the system.");
                        $completedDomains[] = $domain;
                    } else {
                        // Provisioning failed (for any reason) therefor we have to fail the subscription
                        parent::print_stderr("Domain '{$domain}' could not be added.");
                        parent::print_stderr("The subscription has (partially) failed. Unable to proceed!");

                        exit(1);
                    }
                } else {
                    parent::print_stderr("Skipping provisioning of '{$domain}' due to empty MX records");

                    throw new InvalidArgumentException(
                        "Unable to provision services for the domain '$domain' - it has no MX records", 10023);
                }
            } else {
                parent::print_stderr("Skipping creation of '{$domain}' since it already exists.");
            }

        }
        // Rebuild the domain array to a comma separated list

        // Old domains (OLDSETTINGS_provisioned_domains =  list of all domains which exist on SE)
        $oldProvisionDomains
            = getenv('OLDSETTINGS_provisioned_domains'); // Retrieve all previously provisioned domains (if any)
        $oldProvisionDomains = trim($oldProvisionDomains); // Trim it since it might've been a space.
        $oldProvisionDomains = explode(',', $oldProvisionDomains); // Return to normal array instead of Comma Separated
        parent::print_stderr("Old Provisioned domains:" . json_encode($oldProvisionDomains));

        // So do we need this as well then? (SETTINGS_provisioned_domains - values equal to domain names on SE sever _after_ removal)
        $newProvisionDomains
            = getenv('SETTINGS_provisioned_domains'); // Retrieve all previously provisioned domains (if any)
        $newProvisionDomains = trim($newProvisionDomains); // Trim it since it might've been a space.
        $newProvisionDomains = explode(',', $newProvisionDomains); // Return to normal array instead of Comma Separated
        parent::print_stderr("Newly Provisioned domains:" . json_encode($newProvisionDomains));

        $oldProvisionDomains = array_unique(array_merge($oldProvisionDomains, $newProvisionDomains));
        parent::print_stderr("Merged list:" . json_encode($oldProvisionDomains));
        // End of "do we need this"-part

        //in case that OLDSETTINGS_provisioned_domains = SETTINGS_provisioned_domains BUT OLDSETTINGS_domains_1, OLDSETTINGS_domains_2 ...  OLDSETTINGS_domains_n not equal with SETTINGS_domains_1,SETTINGS_domains_2 ...SETTINGS_domains_m means that we need to remove extra OLDSETTINGS_domains_x (the ones that don't have a correspondent to SETTINGS_domains_x)

        if ((count($oldProvisionDomains) > 0) && (count($completedDomains) > 0)) {
            parent::print_stderr("Merging old with new domains.");
            $allDomains = array_merge($oldProvisionDomains, $completedDomains); // Merge array into one.
        } else {
            $allDomains = $oldProvisionDomains;
            parent::print_stderr("Nothing to merge, returning the current list." . json_encode($allDomains));
        }

        // Make the list unique
        $allDomains = array_unique($allDomains);

        $this->_aRemovedDomains
            = array_unique($this->_aRemovedDomains); // remove dupes from removed list (just in case)

        // Cleanup
        foreach ($allDomains as $key => $value) {
            $tv = trim($value);
            if (empty($tv)) {
                parent::print_stderr("Removing empty variable at key {$key}");
                unset($allDomains[$key]);
            }

            if (in_array($tv, $this->_aRemovedDomains)) {
                parent::print_stderr("Domain {$tv} is removed, so we should not report this back.");
                unset($allDomains[$key]);
            } else {
                if (!empty($tv)) {
                    $domain = $tv;
                    $this->updateServices($domain);
                }
            }
        }

        // Make it unique
        parent::print_stderr("Reported domains: " . json_encode($allDomains));

        // Handle reported domains
        parent::print_stderr("Reporting list of domains..");
        $domains = array_unique($allDomains);
        $this->_reportData['provisioned_domains'] = implode(",", $domains);

        // Return resource data.
       // $this->_reportData['num_domains']	= count( $domains );

        // Handle reported emails
        if (count($this->_aProvisionedEmail) > 0) {
            $emails = array_unique($this->_aProvisionedEmail);
            $this->_reportData['provisioned_emails'] = implode(",", $emails);
        }

        // Handle reported passwords
        if (count($this->_aProvisionedPass) > 0) {
            $passwords = array_unique($this->_aProvisionedPass);
            $this->_reportData['provisioned_passwords'] = implode(",", $passwords);
        }

        // Report back all configurations
        parent::report_settings($this->_reportData);

        // Complete
        parent::print_stderr("[Domains] Install End");

        return true;
    }

    /**
     * @return bool
     */
    public function remove()
    {
        parent::print_stderr("[Domains] Remove Start");

        // Multi domain

        for ($i = 1; getenv("SETTINGS_domains_{$i}"); $i++) {
            $domain = getenv("SETTINGS_domains_{$i}");
            if (!empty($domain)) {
                $this->removeDomain($domain);
            } else {
                parent::print_stderr("Removing domain '{$domain}' from antispam has failed");
            }
        }
        parent::print_stderr("[Domains] Remove End");

        return true;
    }

    /**
     * @return bool
     */
    public function configure()
    {
        /**
         * Skip running the configuration procedure for existing domains
         *
         * @see https://trac.spamexperts.com/ticket/25908
         */
        $provisionedDomains = array_filter(array_keys($this->getOldDomains()));
        foreach ($provisionedDomains as $domainToCheck) {
            $status = parent::getApi()->domain()->exists(array('domain' => $domainToCheck));
            if (empty($status['present'])) {
                /** In case even a single domain is not in the filter the data should be syncronized */

                parent::print_stderr("[Domains] Redirecting configure request to install handler...");

                return $this->install(); // return status returned by install process
            }
        }

        return true;
    }

///////////////////////////////////////////////////////////////////////////////////////
    /**
     * @return int
     */
    public function getAmount()
    {
        //$nu = getenv('SETTINGS_num_domains');
        getenv('RESOURCES_NUM_DOMAINS');

        if ((!isset($nu)) || empty($nu)) {
            // Empty/unset value, lets re-count and return that.
            return count($this->getNewDomains());
        }

        return ((!isset($nu)) || (empty($nu))) ? 0 : $nu;
    }

    /**
     * @return array
     */
    protected function getOldDomains()
    {
        $oldDomains = array();
        $old = explode(",", getenv('SETTINGS_provisioned_domains'));
        if (count($old) > 0) {
            foreach ($old as $o) {
                $domain = trim($o);
                $oldDomains[$domain] = 1;
            }
        }

        return $oldDomains;
    }

    /**
     * @return array
     */
    protected function getNewDomains()
    {
        $newDomains = array();
        //$errors = false;
        for ($i = 1; getenv('SETTINGS_domains_' . $i); ++$i) {
            $domain = trim(getenv('SETTINGS_domains_' . $i));
            if (isset($domain) && (!empty($domain))) {

                $newDomains[$domain]['exists'] = 1;
                $mxrecords = $this->getMXRecords($domain);
                $newDomains[$domain]['mx'] = $mxrecords;
                $newDomains[$domain]['hasmxrecord'] = ($mxrecords === false) ? false
                    : true; // Check to see whether we have MX records or if it failed.

                /* if ($mxrecords === false) {
                     $errors = true;
                 }*/
            }
        }

        /*if ($errors == true) {
            return false;
        } else {*/

        return $newDomains;
        //}

    }

    /**
     * @return array
     */
    protected function getProvisionDomains()
    {
        // Lookup all the domains previously used.
        $oldDomains = $this->getOldDomains();

        parent::print_stderr("Checking all currently assigned domains in subscription..");
        $newDomains = $this->getNewDomains();

        parent::print_stderr("Comparing old with new domains.");
        // Walk trough the list and compare them.
        foreach ($oldDomains as $domain => $n) {
            if (strlen($domain) > 0) {
                // Check if this is a domain no longer in use
                if (isset($newDomains[$domain])) {
                    if (empty($newDomains[$domain]['exists'])) {
                        // Does not exist, get rid of it.
                        parent::print_stderr("Removing domain '{$domain}' since it no longer exists");
                        $this->removeDomain($domain);
                    } else {
                        parent::print_stderr("Not removing domain '{$domain}' since it apparently still exists in the account.");
                    }
                } else {
                    /**
                     * @see https://trac.spamexperts.com/ticket/18705
                     */
                    parent::print_stderr("Removing domain '{$domain}' since it no longer exists");

                    $this->removeDomain($domain);
                }
            }
        }

        return $newDomains;
    }

    /**
     * @return bool|string
     */
    protected function _getGlobalMXSubstitute()
    {
        $global_mailserver = getenv('DNS_MX1_SUBSTITUTE');
        if ((isset($global_mailserver)) && (!empty($global_mailserver))) {
            if (substr($global_mailserver, -1) == ".") {
                // remove trailing dot.
                $global_mailserver = substr($global_mailserver, 0, strlen($global_mailserver) - 1);
            }

            // Use the global record.
            return $global_mailserver;
        }

        return false;
    }

    /**
     * @param $domain
     *
     * @return array|bool|string
     */
    protected function getMXRecords($domain)
    {
        // Get MX record for $domain
        parent::print_stderr("Requesting current MX record for '{$domain}'");

        // Get global substitute
        $global_mailserver = self::_getGlobalMXSubstitute();

        /*
            Check for overriden, custom, multi MX records (e.g. Google Apps)
        */

        $multimx = array();
        // MultiMX v2
        for ($i = 1; getenv('DNS_MX1_SUBSTITUTE_' . $domain . '_' . $i); ++$i) {
            $mxrecord = trim(getenv('DNS_MX1_SUBSTITUTE_' . $domain . '_' . $i));
            if (substr($mxrecord, -1) == ".") {
                // remove trailing dot.
                $mxrecord = substr($mxrecord, 0, strlen($mxrecord) - 1);
            }

            /*
                        // Check if the value is equal to the global substitute (POA BUG: APS ISV #1261)
                        if ( ($global_mailserver !== false) && $mxrecord == $global_mailserver )
                        {
                            parent::print_stderr("Ignoring global route ({$global_mailserver}) due to POA BUG #1261...");
                            continue;
                        }
            */

            // Ok, so we have a clean MX record now. Append to the set
            parent::print_stderr("Adding route '{$mxrecord}' to the collection of MX records.");
            $multimx[] = $mxrecord;
        }

        if (is_array($multimx) && (count($multimx) > 0)) {
            // Make unique (just in case)
            parent::print_stderr("Cleaning up array.");
            $multimx = array_unique($multimx);
            if (is_array($multimx) && (count($multimx) > 0)) // Do we still have items left? Just in case.
            {
                if (count($multimx) == 1) {
                    // Just 1 leftover.
                    parent::print_stderr("Returning single result from multi-mx list");

                    return $multimx[0];
                }
                parent::print_stderr("Returning multiple custom MX records");

                return $multimx;
            }
        }

        /*
            Ok, so if we ended up here there are no multiple records. Maybe there is just one?
            Check to see if there is a DNS_MX1_SUBSTITUTE_DOMAINNAME (e.g. DNS_MX1_SUBSTITUTE_example.com)
        */
        parent::print_stderr("Checking for 'DNS_MX1_SUBSTITUTE_{$domain}'...");
        $mailserver = getenv('DNS_MX1_SUBSTITUTE_' . $domain); // e.g: DNS_MX1_SUBSTITUTE_testdomain.com
        if ((isset($mailserver)) && (!empty($mailserver))) {
            if (substr($mailserver, -1) == ".") {
                // remove trailing dot.
                $mailserver = substr($mailserver, 0, strlen($mailserver) - 1);
            }
            // Seems to be set properly, returning local record.
            parent::print_stderr("Returning custom MX record");

            return $mailserver;
        }

        /*
            So, no custom MX records (multiple) or record (single).
            Perhaps there is a global one we can use?
        */
        parent::print_stderr("Checking for global 'DNS_MX1_SUBSTITUTE'...");
        if ($global_mailserver !== false) {
            parent::print_stderr("Returning global MX record");

            return $global_mailserver;
        }

        // We've got a problem, since there are no local or global records at all.
        parent::print_stderr("Unable to obtain destination/MX record!");

        return false;
    }

    /**
     * @param $domain
     * @param $email
     * @param $destination
     *
     * @return bool
     * @throws RuntimeException
     */
    public function addDomain($domain, $email, $destination)
    {
        parent::print_stderr("Requesting '{$domain}' to be added to the spamfilter (destination: '{$destination}').");

        /**
         * It should not be possible to add domains in case of the domains limit is hit
         * @see https://trac.spamexperts.com/ticket/18478
         */
        $provisionedDomainsList = getenv('SETTINGS_provisioned_domains');
        $provisionedDomainsCount = (!empty($provisionedDomainsList)
            ? substr_count($provisionedDomainsList, ',') + 1 : 0);

        $allowedDomainsLimit = getenv('SETTINGS_num_domains');
        if (0 < $allowedDomainsLimit && $allowedDomainsLimit <= $provisionedDomainsCount) {
            throw new RuntimeException("You are not allowed to add more domains because of the limit was hit. " .
                "Added domains count - $provisionedDomainsCount; Domains limit - $allowedDomainsLimit", 10024);
        }

        $api = parent::getApi();

        // Per domain
        $setemail = false;
        $email = (!empty($email) ? $email : getenv('SETTINGS_email'));
        if ((isset($email)) && (!empty($email))) {
            $setemail = true;
        }

        $data = array();
        $data['domain'] = $domain;

        if (isset($destination) && (!empty($destination))) {
            // Only add the destination part if we actually have it, otherwise the API-call will fail.
            if (is_array($destination)) {
                $data['destinations'] = $destination;
            } else {
                $data['destinations'] = array($destination);
            }
        }

        parent::print_stderr("addDomain Data: " . json_encode($data));

        $status = $api->domain()->add($data);

        unset($data);
        if ((!isset($status)) || (!is_array($status)) || (!$status['status'])) {
            // Failed
            parent::print_stderr("Adding domain '{$domain}' to antispam has failed");

            return false;
        } else {
            parent::print_stderr("Domain added. Response: " . json_encode($status));

            /*
                Create domainuser
            */
            if (!isset($domain_password)) {
                parent::print_stderr("Domain password unset, trying to obtain it from ENV");
                $domain_password = getenv('SETTINGS_domain_password');
            }

            if (isset($domain_password) && (!empty($domain_password))) {
                if ((!isset($email)) || (empty($email))) {
                    $email = 'postmaster@' . $domain;
                    parent::print_stderr("No email set, but required for domainuser. Using '{$email}' as a replacement.");
                }

                $data = array(
                    'domain'   => $domain,
                    'password' => $domain_password,
                    'email'    => $email,
                );
                $status = $api->domainuser()->add($data);
                if ((!isset($status)) || (!is_array($status)) || (!$status['status'])) {
                    // Failed to create the domainuser :-(
                    parent::print_stderr("Domainuser NOT created for domain '{$domain}'");
                } else {
                    parent::print_stderr("Domainuser created for domain '{$domain}'");

                    // Succeeded, report back to Parallels
                    $this->_aProvisionedPass[]
                        = $domain_password; // Add it at this point, because it might've been a "local" password rather than the global account one.
                }
            } else {
                parent::print_stderr("[!!] No Domainuser created for domain '{$domain}', missing password");
            }

            /*
                Set the domain contact (not admin)
            */
            if (($setemail) && (!empty($email))) {
                parent::print_stderr("Setting contact address for '{$domain}' to '{$email}'.");
                $data = array(
                    'domain' => $domain,
                    'email'  => $email
                );
                $response = $api->domaincontact()->set(
                    $data
                );
                parent::print_stderr("Domain contact set. Response: " . json_encode($response));
                $this->_aProvisionedEmail[]
                    = $email; // Add it at this point, because it might've been a "local" email rather than the global account one.
            } else {
                parent::print_stderr("Not setting email for '{$domain}', missing email.");
            }
        }

        parent::print_stderr("Adding domain {$domain} has been completed!");

        return true;
    }

    /**
     * @param $domain
     *
     * @return bool
     */
    protected function removeDomain($domain)
    {
        // Append domain to the removed list, so we can report a cleaned up version of the provisioned_domains value
        $this->_aRemovedDomains[] = $domain;

        parent::print_stderr("Requesting '{$domain}' to be removed from the spamfilter.");
        // Remove the addon for this domain
        $api = parent::getApi();

        // Per domain
        $data = array(
            'domain' => $domain
        );

        $status = $api->domain()->remove($data);
        if ((!isset($status)) || (!is_array($status)) || (!$status['status'])) {
            // Failed
            parent::print_stderr("Removing domain {$domain} from antispam has failed.");

            return false;
        }

        parent::print_stderr("Removing domain {$domain} from antispam has been completed succesfully.");

        return true;
    }

    /**
     * @param $domain
     *
     * @return bool
     */
    protected function updateServices($domain)
    {
        // Check whether the services need to be enabled or not.
        $enable_incoming = ((int)getenv('SETTINGS_service_delivery') > 0) ? true : false;
        $enable_outgoing = ((int)getenv('SETTINGS_service_submission') > 0) ? true : false;
        $enable_archiving = ((int)getenv('SETTINGS_service_archiving') > 0) ? true : false;

        // We need to keep track of whats enabled
        $enable_services = array();
        $enable_services['incoming'] = (($enable_incoming) ? true : false);
        $enable_services['outgoing'] = (($enable_outgoing) ? true : false);
        $enable_services['archiving'] = (($enable_archiving) ? true : false);

        parent::print_stderr("The services for {$domain} will be set to: " . json_encode($enable_services));

        return $this->setServices($domain, $enable_services);
    }

    /**
     * @param $domain
     * @param $enable_services
     *
     * @return bool
     */
    protected function setServices($domain, $enable_services)
    {
        parent::print_stderr("Settings services for '{$domain}' to (Inc:{$enable_services['incoming']}|Out:{$enable_services['outgoing']}|Arch:{$enable_services['archiving']})");
        $api = parent::getApi();

        // Per domain
        $data = array(
            'domain'    => $domain,
            'incoming'  => (int)$enable_services['incoming'],
            'outgoing'  => (int)$enable_services['outgoing'],
            'archiving' => (int)$enable_services['archiving']
        );

        $status = $api->domain()->setproducts($data);
        if ((!isset($status)) || (!is_array($status)) || (!$status['status'])) {
            // Failed
            parent::print_stderr("Changing products for domain '{$domain}' has failed.");

            return false;
        }

        parent::print_stderr("Changing products for domain {$domain} has been completed succesfully.");

        // Toggle archiving for the domain
        self::toggleArchiving($domain, $enable_services['archiving']);

        return true;
    }

    /**
     * @param $domain
     * @param $enabled
     *
     * @return bool
     */
    protected function toggleArchiving($domain, $enabled)
    {
        $action = "enable";
        if (!$enabled) {
            $action = "disable";
        }

        parent::print_stderr("Setting archiving for '{$domain}' to '{$action}d'.");
        $api = parent::getApi();

        // Per domain
        $data = array(
            'domain' => $domain,
            'action' => $action,
        );

        $status = $api->domain()->archive($data);
        if ((!isset($status)) || (!is_array($status)) || (!$status['status'])) {
            // Failed
            parent::print_stderr("Setting archiving to {$status}d for domain {$domain} has failed.");

            return false;
        }
        parent::print_stderr("Setting archiving to {$status}d for domain {$domain} has been completed succesfully.");

        return true;
    }
}
