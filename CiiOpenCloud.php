<?php

/**
 * CiiOpenCloud Component
 * Assists in managing CiiOpenCloud Resources (namely)
 */
class CiiOpenCloud extends CComponent
{
	// Identity URL
	public $identity		= NULL;

	// Openstack Username
	public $username		= NULL;

	// Openstack APIKey
	public $apiKey			= NULL;

	// Whether or not we should use rackspace cloudfiles
	public $useRackspace 	= false;

	// Default region
	public $region 			= 'IAD';

	// Client interface
	private $_client = NULL;

	// Service interface
	private $_service = NULL;

	// Container
	private $_container = NULL;

	// Control file for certain overrides
	private $_overrideControl = array();

	/**
	 * Constructor for CiiOpenCloud Utility Helper
	 * @param string  $username     Openstack Username
	 * @param string  $apiKey       Openstack APIKey
	 * @param boolean $useRackspace Whether or not we should use Rackspace CLoudfiles
	 * @param url     $identity     Identity URL
	 * @param string  $region       Default region we should store assets in
	 */
	public function __construct($username, $apiKey, $useRackspace = false, $identity = NULL, $region='IAD')
	{
		$this->identity = $identity;
		$this->username = $username;
		$this->apiKey = $apiKey;
		$this->useRackspace = $useRackspace;
		$this->region = $region;
	}

	/**
	 * Retrieves the Openstack interface client
	 * @return self::$_client
	 */
	public function getClient()
	{
		if ($this->_client != NULL)
			return $this->_client;

		if ($this->useRackspace)
		{
			if ($this->identity == NULL)
				$this->identity = OpenCloud\Rackspace::US_IDENTITY_ENDPOINT;

			$this->_client = new OpenCloud\Rackspace($this->identity, array(
	            'username' => $this->username,
	            'apiKey'   => $this->apiKey
	        ));
		}
		else
		{
			$this->_client = new OpenCloud\OpenStack($this->identity, array(
	            'username' => $this->username,
	            'apiKey'   => $this->apiKey
			));
		}

		return $this->_client;
	}

	/**
	 * Retrieves the cloudfiles service
	 * @return self::$_service
	 */
	public function getService()
	{
		$this->_service = $this->getClient()->objectStoreService('cloudFiles', $this->region);
		return $this->_service;
	}

	/**
	 * Retrieves the cloudfiles container by name
	 * @param  string $name The name of the contianer
	 * @return Openstack\Container object
	 */
	public function getContainer($name=NULL)
	{
		if ($this->_container != NULL)
			return $this->_container;

		if ($name == NULL)
			return $this->_container = false;

        $this->_container = $this->getService()->getContainer($name);

        return $this->_container;
	}

	/**
	 * Uploads a file to an OpenStack Conainter
	 */
	public function uploadFile($file, $filename)
	{
		// Validate the container
		$this->_container = $this->getContainer();
		if ($this->_container == NULL)
			return array('error' => Yii::t('ciims.misc', 'Unable to attach OpenStack Container.'));

        $fullFileName = $filename.'.'.$file->getExtension();

		$factory = new CryptLib\Random\Factory;
		$fileZ = preg_replace('/[^\da-z]/i', '', $factory->getLowStrengthGenerator()->generateString(32));
		$cdnFilename = $fileZ.'.'.$file->getExtension();

        try {
        	$response = $this->_container->uploadObject($cdnFilename, file_get_contents($file->tmp_name), array());
        	if ($response)
	        	 return array('success' => true,'filename'=> $cdnFilename, 'url' => $this->_container->getCDN()->getMetadata()->getProperty('Ssl-Uri') . '/'. $cdnFilename);
	        else
	        	return array('error'=> Yii::t('ciims.misc', 'Could not save uploaded file. The upload was cancelled, or server error encountered'));
        } catch (Exception $e) {
        	return array('error'=> Yii::t('ciims.misc', 'The server encountered an error during uploading. Please verify that you have saufficient space in the container and that your quota has not been reached.'));
        }
	}
}