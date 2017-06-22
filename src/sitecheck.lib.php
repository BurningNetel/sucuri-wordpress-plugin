<?php

/**
 * Code related to the sitecheck.lib.php interface.
 *
 * @package Sucuri Security
 * @subpackage sitecheck.lib.php
 * @copyright Since 2010 Sucuri Inc.
 */

if (!defined('SUCURISCAN_INIT') || SUCURISCAN_INIT !== true) {
    if (!headers_sent()) {
        /* Report invalid access if possible. */
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

/**
 * Controls the execution of the SiteCheck scanner.
 *
 * SiteCheck is a web application scanner that reads the source code of a
 * website to determine if it is serving malicious code, it scans the home page
 * and linked sub-pages, then compares the results with a list of signatures as
 * well as a list of blacklist services to see if other malware scanners have
 * flagged the website before. This operation may take a couple of seconds,
 * around twenty seconds in most cases; be sure to set enough timeout for the
 * operation to finish, otherwise the scanner will return innacurate
 * information.
 *
 * @see https://sitecheck.sucuri.net/
 */
class SucuriScanSiteCheck extends SucuriScanAPI
{
    private static $scanRequests;

    /**
     * Executes a malware scan against the specified website.
     *
     * @see https://sitecheck.sucuri.net/
     *
     * @param string $domain The clean version of the website's domain.
     * @param bool $clear Request the results from a fresh scan or not.
     * @return array|bool JSON encoded website scan results.
     */
    public static function runMalwareScan($domain = '', $clear = true)
    {
        if (empty($domain)) {
            return false;
        }

        $params = array();
        $params['scan'] = $domain;
        $params['fromwp'] = 2;
        $params['json'] = 1;

        // Request a fresh scan or not.
        if ($clear === true) {
            $params['clear'] = 1;
        }

        $args = array('assoc' => true, 'timeout' => 60);

        return self::apiCall('https://sitecheck.sucuri.net/', 'GET', $params, $args);
    }

    /**
     * Scans a website for malware using SiteCheck.
     *
     * This method will first check if the scan results have been cached by a
     * previous scan. The lifetime of the cache is defined in the global script
     * but usually it should not be higher than fifteen minutes. If the cache
     * exists it will be used to display the information in the dashboard.
     *
     * If the cache does not exists or has already expired, it will send a HTTP
     * request to the SiteCheck API service to execute a fresh scan, this takes
     * around twenty seconds, it will decode and process the response and render
     * the results in the dashboard.
     *
     * If the user sends a GET parameter named "s" with a valid domain name, it
     * will be used instead of the one of the current website. This is useful if
     * you want to test the functionality of the scanner in a different website
     * without access to its domain, which is basically the same thing that you
     * can do in the official SiteCheck website. This parameter also bypasses
     * the cache.
     *
     * @return array|bool SiteCheck scan results.
     */
    public static function scanAndCollectData()
    {
        $tld = SucuriScan::getDomain();
        $cache = new SucuriScanCache('sitecheck');
        $results = $cache->get('scan_results', SUCURISCAN_SITECHECK_LIFETIME, 'array');

        /**
         * Allow the user to scan foreign domains.
         *
         * This condition allows for the execution of the malware scanner on a
         * website different than the one where the plugin is installed. This is
         * basically the same as scanning any domain on the SiteCheck website.
         * In this case, this is mostly used to allow the development execute
         * tests and to troubleshoot issues reported by other users.
         *
         * @var boolean
         */
        if ($custom = SucuriScanRequest::get('s')) {
            $tld = SucuriScan::escape($custom);
            $results = false /* invalid cache */;
        }

        /* return cached malware scan results. */
        if ($results && !empty($results)) {
            return $results;
        }

        /**
         * Do not overflow the API service.
         *
         * If we are scanning the website for the first time or after the cache
         * has expired, it makes no sense to let the script connect to the API
         * more than once. If there is a failure with the first request we can
         * assume that the same error will appear in the subsequent request, so
         * we will return false every time a secondary scan is requested.
         *
         * The first scan is requested and executed normally.
         *
         * The second and subsequent scan requests (from the other methods) are
         * expected to return the data from the cache, hence the position of the
         * conditional in this specific line, right after the cache lifetime is
         * checked. If the cache is invalid (because the first scan failed) or
         * if the cache has expired (and the new request fails) we will assume
         * that the other requests (around ten or so) will fail too.
         */
        self::$scanRequests++;
        if (self::$scanRequests > 1) {
            return false;
        }

        /* send HTTP request to SiteCheck's API service. */
        $results = self::runMalwareScan($tld);

        /* check for error in the request's response. */
        if (is_string($results) || isset($results['SYSTEM']['ERROR'])) {
            if (isset($results['SYSTEM']['ERROR'])) {
                $results = implode("\x20", $results['SYSTEM']['ERROR']);
            }

            return SucuriScanInterface::error('SiteCheck error: ' . $results);
        }

        /* cache the results for some time. */
        $cache = new SucuriScanCache('sitecheck');
        $cache->add('scan_results', $results);

        return $results;
    }

    /**
     * Generates the HTML section for the SiteCheck details.
     *
     * @return string HTML code to render the details section.
     */
    public static function details()
    {
        $params = array();
        $data = self::scanAndCollectData();

        $params['SiteCheck.Website'] = '(unknown)';
        $params['SiteCheck.Domain'] = '(unknown)';
        $params['SiteCheck.ServerAddress'] = '(unknown)';
        $params['SiteCheck.WPVersion'] = SucuriScan::siteVersion();
        $params['SiteCheck.PHPVersion'] = phpversion();
        $params['SiteCheck.Additional'] = '';

        if (isset($data['SCAN']['SITE'])) {
            $params['SiteCheck.Website'] = $data['SCAN']['SITE'][0];
        }

        if (isset($data['SCAN']['DOMAIN'])) {
            $params['SiteCheck.Domain'] = $data['SCAN']['DOMAIN'][0];
        }

        if (isset($data['SCAN']['IP'])) {
            $params['SiteCheck.ServerAddress'] = $data['SCAN']['IP'][0];
        }

        $data['SCAN_ADDITIONAL'] = array();

        if (isset($data['SCAN']['HOSTING'])) {
            $data['SCAN_ADDITIONAL'][] = 'Hosting: ' . $data['SCAN']['HOSTING'][0];
        }

        if (isset($data['SCAN']['CMS'])) {
            $data['SCAN_ADDITIONAL'][] = 'CMS: ' . $data['SCAN']['CMS'][0];
        }

        if (isset($data['SYSTEM']['NOTICE'])) {
            $data['SCAN_ADDITIONAL'] = array_merge(
                $data['SCAN_ADDITIONAL'],
                $data['SYSTEM']['NOTICE']
            );
        }

        if (isset($data['SYSTEM']['INFO'])) {
            $data['SCAN_ADDITIONAL'] = array_merge(
                $data['SCAN_ADDITIONAL'],
                $data['SYSTEM']['INFO']
            );
        }

        if (isset($data['WEBAPP']['VERSION'])) {
            $data['SCAN_ADDITIONAL'] = array_merge(
                $data['SCAN_ADDITIONAL'],
                $data['WEBAPP']['VERSION']
            );
        }

        if (isset($data['WEBAPP']['WARN'])) {
            $data['SCAN_ADDITIONAL'] = array_merge(
                $data['SCAN_ADDITIONAL'],
                $data['WEBAPP']['WARN']
            );
        }

        if (isset($data['OUTDATEDSCAN'])) {
            foreach ($data['OUTDATEDSCAN'] as $outdated) {
                if (isset($outdated[0]) && isset($outdated[2])) {
                    $data['SCAN_ADDITIONAL'][] = $outdated[0] . ':' . $outdated[2];
                }
            }
        }

        foreach ($data['SCAN_ADDITIONAL'] as $text) {
            $parts = explode(':', $text, 2);

            if (count($parts) === 2) {
                $params['SiteCheck.Additional'] .=
                SucuriScanTemplate::getSnippet('sitecheck-details', array(
                    'SiteCheck.Title' => trim($parts[0]),
                    'SiteCheck.Value' => trim($parts[1]),
                ));
            }
        }

        return SucuriScanTemplate::getSection('sitecheck-details', $params);
    }

    /**
     * Generates the HTML section for the SiteCheck malware.
     *
     * @return string HTML code to render the malware section.
     */
    public static function malware()
    {
        $params = array();
        $data = self::scanAndCollectData();

        $params['Malware.Content'] = '';
        $params['Malware.Color'] = 'green';
        $params['Malware.Title'] = 'Site is Clean';
        $params['Malware.CleanVisibility'] = 'visible';
        $params['Malware.InfectedVisibility'] = 'hidden';

        if (isset($data['MALWARE']['WARN']) && !empty($data['MALWARE']['WARN'])) {
            $params['Malware.Color'] = 'red';
            $params['Malware.Title'] = 'Site is not Clean';
            $params['Malware.CleanVisibility'] = 'hidden';
            $params['Malware.InfectedVisibility'] = 'visible';

            foreach ($data['MALWARE']['WARN'] as $mal) {
                if ($info = self::malwareDetails($mal)) {
                    $params['Malware.Content'] .=
                    SucuriScanTemplate::getSnippet('sitecheck-malware', array(
                        'Malware.InfectedURL' => $info['infected_url'],
                        'Malware.MalwareType' => $info['malware_type'],
                        'Malware.MalwareDocs' => $info['malware_docs'],
                        'Malware.AlertMessage' => $info['alert_message'],
                        'Malware.MalwarePayload' => $info['malware_payload'],
                    ));
                }
            }
        }

        return SucuriScanTemplate::getSection('sitecheck-malware', $params);
    }

    /**
     * Generates the HTML section for the SiteCheck blacklist.
     *
     * @return string HTML code to render the blacklist section.
     */
    public static function blacklist()
    {
        $params = array();
        $data = self::scanAndCollectData();

        $params['Blacklist.Title'] = 'Not Blacklisted';
        $params['Blacklist.Color'] = 'green';
        $params['Blacklist.Content'] = '';

        foreach ((array) $data['BLACKLIST'] as $type => $proof) {
            foreach ($proof as $info) {
                $url = $info[1];
                $title = @preg_replace(
                    '/Domain (clean|blacklisted) (on|by) (the )?/',
                    '' /* remove unnecessary text from the output */,
                    substr($info[0], 0, strrpos($info[0], ':'))
                );

                $params['Blacklist.Content'] .=
                SucuriScanTemplate::getSnippet('sitecheck-blacklist', array(
                    'Blacklist.URL' => $url,
                    'Blacklist.Status' => $type,
                    'Blacklist.Service' => $title,
                ));
            }
        }

        if (isset($data['BLACKLIST']['WARN'])) {
            $params['Blacklist.Title'] = 'Blacklisted';
            $params['Blacklist.Color'] = 'red';
        }

        return SucuriScanTemplate::getSection('sitecheck-blacklist', $params);
    }

    /**
     * Generates the HTML section for the SiteCheck recommendations.
     *
     * @return string HTML code to render the recommendations section.
     */
    public static function recommendations()
    {
        $params = array();
        $data = self::scanAndCollectData();

        $params['Recommendations.Content'] = '';
        $params['Recommendations.Visibility'] = 'hidden';

        if (isset($data['RECOMMENDATIONS'])) {
            foreach ($data['RECOMMENDATIONS'] as $recommendation) {
                if (count($recommendation) < 3) {
                    continue;
                }

                $params['Recommendations.Visibility'] = 'visible';
                $params['Recommendations.Content'] .=
                SucuriScanTemplate::getSnippet('sitecheck-recommendations', array(
                    'Recommendations.Title' => $recommendation[0],
                    'Recommendations.Value' => $recommendation[1],
                    'Recommendations.URL' => $recommendation[2],
                ));
            }
        }

        return SucuriScanTemplate::getSection('sitecheck-recommendations', $params);
    }

    /**
     * Returns the title for the iFrames section.
     *
     * @return string Title for the iFrames section.
     */
    public static function iFramesTitle()
    {
        $data = self::scanAndCollectData();

        return sprintf('iFrames: %d', @count($data['LINKS']['IFRAME']));
    }

    /**
     * Returns the title for the links section.
     *
     * @return string Title for the links section.
     */
    public static function linksTitle()
    {
        $data = self::scanAndCollectData();

        return sprintf('Links: %d', @count($data['LINKS']['URL']));
    }

    /**
     * Returns the title for the scripts section.
     *
     * @return string Title for the scripts section.
     */
    public static function scriptsTitle()
    {
        $data = self::scanAndCollectData();
        $total = 0; /* all type of scripts */

        if (isset($data['LINKS']['JSLOCAL'])) {
            $total += count($data['LINKS']['JSLOCAL']);
        }

        if (isset($data['LINKS']['JSEXTERNAL'])) {
            $total += count($data['LINKS']['JSEXTERNAL']);
        }

        return sprintf('Scripts: %d', $total);
    }

    /**
     * Returns the content for the iFrames section.
     *
     * @return string Content for the iFrames section.
     */
    public static function iFramesContent()
    {
        $params = array();
        $data = self::scanAndCollectData();

        if (!isset($data['LINKS']['IFRAME'])) {
            return ''; /* empty content */
        }

        $params['SiteCheck.Resources'] = '';

        foreach ($data['LINKS']['IFRAME'] as $url) {
            $params['SiteCheck.Resources'] .=
            SucuriScanTemplate::getSnippet('sitecheck-links', array(
                'SiteCheck.URL' => $url,
            ));
        }

        return SucuriScanTemplate::getSection('sitecheck-links', $params);
    }

    /**
     * Returns the content for the links section.
     *
     * @return string Content for the links section.
     */
    public static function linksContent()
    {
        $params = array();
        $data = self::scanAndCollectData();

        if (!isset($data['LINKS']['URL'])) {
            return ''; /* empty content */
        }

        $params['SiteCheck.Resources'] = '';

        foreach ($data['LINKS']['URL'] as $url) {
            $params['SiteCheck.Resources'] .=
            SucuriScanTemplate::getSnippet('sitecheck-links', array(
                'SiteCheck.URL' => $url,
            ));
        }

        return SucuriScanTemplate::getSection('sitecheck-links', $params);
    }

    /**
     * Returns the content for the scripts section.
     *
     * @return string Content for the scripts section.
     */
    public static function scriptsContent()
    {
        $total = 0;
        $params = array();
        $data = self::scanAndCollectData();

        $params['SiteCheck.Resources'] = '';

        if (isset($data['LINKS']['JSLOCAL'])) {
            foreach ($data['LINKS']['JSLOCAL'] as $url) {
                $total++;

                $params['SiteCheck.Resources'] .=
                SucuriScanTemplate::getSnippet('sitecheck-links', array(
                    'SiteCheck.URL' => $url,
                ));
            }
        }

        if (isset($data['LINKS']['JSEXTERNAL'])) {
            foreach ($data['LINKS']['JSEXTERNAL'] as $url) {
                $total++;
                $params['SiteCheck.Resources'] .=
                SucuriScanTemplate::getSnippet('sitecheck-links', array(
                    'SiteCheck.URL' => $url,
                ));
            }
        }

        if ($total === 0) {
            return ''; /* empty content */
        }

        return SucuriScanTemplate::getSection('sitecheck-links', $params);
    }

    /**
     * Extract detailed information from a SiteCheck malware payload.
     *
     * @param array $malware Array with two entries with basic malware information.
     * @return array Detailed information of the malware found by SiteCheck.
     */
    public static function malwareDetails($malware = array())
    {
        if (count($malware) < 2) {
            return array(/* empty details */);
        }

        $data = array(
            'alert_message' => '',
            'infected_url' => '',
            'malware_type' => '',
            'malware_docs' => '',
            'malware_payload' => '',
        );

        // Extract the information from the alert message.
        $alert_parts = explode(':', $malware[0], 2);

        if (isset($alert_parts[1])) {
            $data['alert_message'] = $alert_parts[0];
            $data['infected_url'] = trim($alert_parts[1]);
        }

        // Extract the information from the malware message.
        $malware_parts = explode("\n", $malware[1]);

        if (isset($malware_parts[1])) {
            if (@preg_match('/(.+)\. Details: (.+)/', $malware_parts[0], $match)) {
                $data['malware_type'] = $match[1];
                $data['malware_docs'] = $match[2];
            }

            $data['malware_payload'] = trim($malware_parts[1]);
        }

        return $data;
    }
}