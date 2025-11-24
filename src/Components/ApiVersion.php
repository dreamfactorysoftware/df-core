<?php
namespace DreamFactory\Core\Components;

trait ApiVersion
{
    protected $apiVersion = null;

    /**
     * {@inheritdoc}
     */
    public function getApiVersion()
    {
        if (empty($this->apiVersion)) {
            $this->setApiVersion();
        }

        return $this->apiVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function setApiVersion($version = null)
    {
        if (empty($version)) {
            $version = \Config::get('df.api_version');
        }

        $version = strval($version); // if numbers are passed in
        if (substr(strtolower($version), 0, 1) === 'v') {
            $version = substr($version, 1);
        }
        if (strpos($version, '.') === false) {
            $version = $version . '.0';
        }

        $this->apiVersion = $version;
    }
}