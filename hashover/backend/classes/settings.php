<?php namespace HashOver;

// Copyright (C) 2010-2018 Jacob Barkdull
// This file is part of HashOver.
//
// HashOver is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// HashOver is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with HashOver.  If not, see <http://www.gnu.org/licenses/>.
//
//--------------------
//
// IMPORTANT NOTICE:
//
// Do not edit this file unless you know what you are doing. Instead,
// please use the HashOver administration panel to graphically adjust
// the settings, or create/edit the settings JSON file.


// Automated settings
class Settings extends SensitiveSettings
{
	public $rootDirectory;
	public $httpRoot;
	public $httpBackend;
	public $httpImages;
	public $cookieExpiration;
	public $domain;

	public function __construct ()
	{
		// Theme path
		$this->themePath = 'themes/' . $this->theme;

		// Set server timezone
		date_default_timezone_set ($this->serverTimezone);

		// Set encoding
		mb_internal_encoding ('UTF-8');

		// Get parent directory
		$root_directory = dirname (dirname (__DIR__));

		// Get HTTP parent directory
		$document_root = realpath ($_SERVER['DOCUMENT_ROOT']);
		$http_directory = mb_substr ($root_directory, mb_strlen ($document_root));

		// Replace backslashes with forward slashes on Windows
		if (DIRECTORY_SEPARATOR === '\\') {
			$http_directory = str_replace ('\\', '/', $http_directory);
		}

		// Determine HTTP or HTTPS
		$protocol = ($this->isHTTPS () ? 'https' : 'http') . '://';

		// Technical settings
		$this->rootDirectory	= $root_directory;		// Root directory for script
		$this->httpRoot		= $http_directory;		// Root directory for HTTP
		$this->httpBackend	= $http_directory . '/backend';	// Backend directory for HTTP
		$this->httpImages	= $http_directory . '/images';	// Image directory for HTTP
		$this->cookieExpiration	= time () + 60 * 60 * 24 * 30;	// Cookie expiration date
		$this->domain		= $_SERVER['HTTP_HOST'];	// Domain name for refer checking & notifications
		$this->absolutePath	= $protocol . $this->domain;	// Absolute path or remote access

		// Load JSON settings
		$this->loadSettingsFile ();

		// Synchronize settings
		$this->syncSettings ();
	}

	public function isHTTPS ()
	{
		// The connection is HTTPS if server says so
		if (!empty ($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off') {
			return true;
		}

		// Assume the connection is HTTPS on standard SSL port
		if ($_SERVER['SERVER_PORT'] == 443) {
			return true;
		}

		return false;
	}

	// Overrides settings based on JSON data
	protected function overrideSettings ($json, $class = 'Settings')
	{
		// Parse JSON data
		$settings = @json_decode ($json, true);

		// Return void if data is anything other than an array
		if (!is_array ($settings)) {
			return;
		}

		// Loop through JSON data
		foreach ($settings as $setting => $value) {
			// Check if the key contains dashes
			if (mb_strpos ($setting, '-') !== false) {
				// If so, convert setting key to lowercase
				$setting = mb_strtolower ($setting);

				// Then convert dashed-case setting key to camelCase
				$setting = preg_replace_callback ('/-([a-z])/', function ($grp) {
					return mb_strtoupper ($grp[1]);
				}, $setting);
			}

			// Check if the setting from the JSON data exists
			if (property_exists ('HashOver\\' . $class, $setting)) {
				// If so, override the default if the setting types match
				if (gettype ($value) === gettype ($this->{$setting})) {
					$this->{$setting} = $value;
				}
			}
		}
	}

	// Reads JSON settings file and uses it to override default settings
	protected function loadSettingsFile ()
	{
		// JSON settings file path
		$path = $this->getAbsolutePath ('config/settings.json');

		// Check if JSON settings file exists
		if (file_exists ($path)) {
			// If so, read the file
			$json = @file_get_contents ($path);

			// And override settings
			$this->overrideSettings ($json);
		}
	}

	// Synchronizes specific settings after remote changes
	public function syncSettings ()
	{
		// Theme path
		$this->themePath = 'themes/' . $this->theme;

		// Disable likes and dislikes if cookies are disabled
		if ($this->setsCookies === false) {
			$this->allowsLikes = false;
			$this->allowsDislikes = false;
		}

		// Setup default field options
		foreach (array ('name', 'password', 'email', 'website') as $field) {
			if (!isset ($this->fieldOptions[$field])) {
				$this->fieldOptions[$field] = true;
			}
		}

		// Disable password if name is disabled
		if ($this->fieldOptions['name'] === false) {
			$this->fieldOptions['password'] = false;
		}

		// Disable login if name or password is disabled
		if ($this->fieldOptions['name'] === false
		    or $this->fieldOptions['password'] === false)
		{
			$this->allowsLogin = false;
		}

		// Disable autologin if login is disabled
		if ($this->allowsLogin === false) {
			$this->usesAutoLogin = false;
		}

		// Check if the Gravatar default image name is not custom
		if ($this->gravatarDefault !== 'custom') {
			// If so, list Gravatar default image names
			$gravatar_defaults = array ('identicon', 'monsterid', 'wavatar', 'retro');

			// And set Gravatar default image to custom if its value is invalid
			if (!in_array ($this->gravatarDefault, $gravatar_defaults, true)) {
				$this->gravatarDefault = 'custom';
			}
		}

		// Backend directory for HTTP
		$this->httpBackend = $this->httpRoot . '/backend';

		// Image directory for HTTP
		$this->httpImages = $this->httpRoot . '/images';
	}

	// Accepts JSON data from the frontend to override default settings
	public function loadUserSettings ($json)
	{
		// Only override settings safe to expose to the frontend
		$this->overrideSettings ($json, 'SafeSettings');

		// Synchronize settings
		$this->syncSettings ();
	}

	// Returns a server-side absolute file path
	public function getAbsolutePath ($file)
	{
		return $this->rootDirectory . '/' . trim ($file, '/');
	}

	// Returns a client-side path for a file within the HashOver root
	public function getHttpPath ($file)
	{
		return $this->httpRoot . '/' . trim ($file, '/');
	}

	// Returns a client-side path for a file within the backend directory
	public function getBackendPath ($file)
	{
		return $this->httpBackend . '/' . trim ($file, '/');
	}

	// Returns a client-side path for a file within the images directory
	public function getImagePath ($filename)
	{
		$path  = $this->httpImages . '/' . trim ($filename, '/');
		$path .= '.' . $this->imageFormat;

		return $path;
	}

	// Returns a client-side path for a file within the configured theme
	public function getThemePath ($file, $http = true)
	{
		// Path to the requested file in the configured theme
		$theme_file = $this->themePath . '/' . $file;

		// Use the same file from the default theme if it doesn't exist
		if (!file_exists ($this->getAbsolutePath ($theme_file))) {
			$theme_file = 'themes/default/' . $file;
		}

		// Convert the theme file path for HTTP use if told to
		if ($http !== false) {
			$theme_file = $this->getHttpPath ($theme_file);
		}

		return $theme_file;
	}

	// Check if a given API format is enabled
	public function apiStatus ($api)
	{
		// Check if the given API is enabled
		if (is_array ($this->enabledApi)) {
			// Return enabled if all available APIs are enabled
			if (in_array ('all', $this->enabledApi)) {
				return 'enabled';
			}

			// Return enabled if the given API is enabled
			if (in_array ($api, $this->enabledApi)) {
				return 'enabled';
			}
		}

		// Otherwise, assume API is disabled by default
		return 'disabled';
	}
}
