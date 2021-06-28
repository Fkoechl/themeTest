<?php

class Sofa1WPThemeUpdater
{
    /** @var string */
    private $_callFilePath;
    /** @var string */
    private $_themeRootFileDir;
    /** @var string */
    private $_repoName;
    /** @var string */
    private $_authToken;
    /** @var array */
    private $_themeData;
    /** @var bool */
    private $_isActive;
    /** @var mixed */
    private $_sofa1Response;
    /** @var string */
    private $_proxyUrl;
    /** @var string */
    private $_requiredVersion;
    /** @var string */
    private $_testedVersion;

    /**
     * PDUpdater constructor.
     *
     * Need the filePath (__FILE__) of the calling file
     * @param string $filePath
     */
    public function __construct($filePath)
    {
        $this->_callFilePath = $filePath;
        add_action('admin_init', [$this, 'SetThemeInfos']);
    }

    /**
     * Adds the needed filters
     */
    public function Init()
    {
        add_filter('pre_set_site_transient_update_themes', [$this, 'ModifyTransient'], 10, 1);
        add_filter('themes_api', [$this, 'ThemePopup'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'AfterUpdate'], 10, 3);
    }

    /**
     * Sets the some needed plugin information's automatically
     */
    public function SetThemeInfos()
    {
        $this->_themeRootFileDir = plugin_basename($this->_callFilePath);
        $this->_isActive = is_plugin_active($this->_themeRootFileDir);
        $this->_themeData = get_plugin_data($this->_callFilePath);
    }

    /**
     * Set the RepositoryName
     * @param string $name
     */
    public function SetRepositoryName($name)
    {
        $this->_repoName = $name;
    }

    /**
     * Set the AuthToken
     * @param string $token
     */
    public function SetAuthorizationToken($token)
    {
        $this->_authToken = $token;
    }

    /**
     * Set the ProxyUrl
     * @param string $url
     */
    public function SetProxyUrl($url)
    {
        $this->_proxyUrl = $url;
    }

    /**
     * Set the required wordpress version
     * @param string $version
     */
    public function SetRequiredWpVersion($version)
    {
        $this->_requiredVersion = $version;
    }

    /**
     * Set the tested wordpress version
     * @param string $version
     */
    public function SetTestedWpVersion($version)
    {
        $this->_testedVersion = $version;
    }

    /**
     * Get the needed information's from the repository and give it in the githubResponse variable
     */
    private function GetInformationFromRepository()
    {
        if (empty($this->_githubResponse)) {
            $request_uri = "$this->_proxyUrl/init.php?method=0&name=$this->_repoName&authKey=$this->_authToken&component=1";

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $request_uri,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ]);

            $response = curl_exec($curl);

            curl_close($curl);

            $response = json_decode($response);

            $this->_sofa1Response = $response;
        }
    }

    /**
     * Modifies the transit to download the theme from the host server
     *
     * @param $transient
     * @return mixed
     */
    public function ModifyTransient($transient)
    {

        if (property_exists($transient, 'checked')) {

            if ($checked = $transient->checked) {

                $this->GetInformationFromRepository();

                $out_of_date = version_compare($this->_sofa1Response->tag_name, $checked[$this->_themeRootFileDir], 'gt');

                if ($out_of_date) {

                    $slug = current(explode('/', $this->_themeRootFileDir));

                    $plugin = [
                        'url' => $this->_themeData['PluginURI'],
                        'slug' => $slug,
                        'package' => $this->_sofa1Response->sofa1DownloadUrl,
                        'new_version' => $this->_sofa1Response->tag_name
                    ];

                    $transient->response[$this->_themeRootFileDir] = (object)$plugin;
                }
            }
        }

        return $transient;
    }

    /**
     * Set the information for the changelog popup
     *
     * @param $result
     * @param $action
     * @param $args
     * @return false|mixed|object
     */
    public function ThemePopup($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return false;
        }

        if (!empty($args->slug)) {
            if ($args->slug == current(explode('/', $this->_themeRootFileDir))) {
                $this->GetInformationFromRepository();

                $plugin = [
                    'name' => $this->_themeData['Name'],
                    'slug' => $this->_themeRootFileDir,
                    'requires' => $this->_requiredVersion,
                    'tested' => $this->_testedVersion,
                    'version' => $this->_sofa1Response->tag_name,
                    'author' => $this->_themeData['AuthorName'],
                    'author_profile' => $this->_themeData['AuthorURI'],
                    'last_updated' => $this->_sofa1Response->published_at,
                    'homepage' => $this->_themeData['PluginURI'],
                    'short_description' => $this->_themeData['Description'],
                    'sections' => [
                        'Description' => $this->_themeData['Description'],
                        'Updates' => $this->_sofa1Response->body,
                    ]
                ];

                return (object)$plugin;
            }
        }

        return $result;
    }

    /**
     * Moves the files in the right directory and activate the component if it was active
     *
     * @param $response
     * @param $hook_extra
     * @param $result
     * @return mixed
     */
    public function AfterUpdate($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->_callFilePath);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->_isActive) {
            activate_plugin($this->_themeRootFileDir);
        }

        return $result;
    }
}
