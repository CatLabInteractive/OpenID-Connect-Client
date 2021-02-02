<?php

namespace CatLab\OpenIDClient\Models;

use CatLab\OpenIDClient\Mappers\UserMapper;
use Neuron\Config;
use Neuron\MapperFactory;
use Neuron\Net\Client;
use Neuron\Net\Request;

/**
 * Class User
 * @package CatLab\OpenIDClient\Models
 */
class User implements \Neuron\Interfaces\Models\User
{
    public $pingInterval = 3600; // every hour.

    private $accessToken;

    /** @var int $id */
    private $id;

    /** @var string $email */
    private $email;

    /** @var string $password */
    private $password;

    /** @var string $passwordhash */
    private $passwordhash;

    /** @var string $displayName */
    private $displayName;

    /** @var string $sub */
    private $sub;

    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * @var \DateTime
     */
    private $lastPing;

    /**
     * User constructor.
     */
    public function __construct()
    {

    }

    /**
     * Process input from openid provider.
     * @param $details
     */
    public function mergeFromInput($details)
    {

        if (isset ($details['username']))
            $this->setDisplayName($details['username']);

    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @param string $hash
     */
    public function setPasswordHash($hash)
    {
        $this->passwordhash = $hash;
    }

    /**
     * @return string
     */
    public function getPasswordHash()
    {
        return $this->passwordhash;
    }

    /**
     * @param bool $formal
     * @return string
     */
    public function getDisplayName($formal = false)
    {
        return $this->displayName;
    }

    /**
     * @param string $displayName
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return string
     */
    public function getSub()
    {
        return $this->sub;
    }

    /**
     * @param string $sub
     */
    public function setSub($sub)
    {
        $this->sub = $sub;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getLastPing()
    {
        return $this->lastPing;
    }

    /**
     * @param \DateTime $lastPing
     */
    public function setLastPing($lastPing)
    {
        $this->lastPing = $lastPing;
    }

    /**
     * Remove any data that might be considered personal.
     */
    public function anonymize()
    {
        $this->setEmail(null);
        $this->setDisplayName(null);

        \Neuron\MapperFactory::getUserMapper()->update($this);
    }

    /**
     * Should we send a ping message back to the authentication server?
     * @return bool
     */
    public function shouldPing()
    {
        if (
            $this->getLastPing() === null ||
            $this->getLastPing()->getTimestamp() < (time() - $this->pingInterval)
        ) {
            $this->setLastPing(new \DateTime());

            /** @var UserMapper $mapper */
            $mapper = MapperFactory::getUserMapper();
            $mapper->updateLastPing($this);

            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    public function ping()
    {
        $pingEndpoint = Config::get('openid.client.ping_endpoint');
        if (!$pingEndpoint) {
            return null;
        }

        if (!$this->shouldPing()) {
            return null;
        }

        $req = new Request ();
        $req->setUrl($pingEndpoint);
        $req->setParameters(array(
            'access_token' => $this->getAccessToken()
        ));

        $response = Client::getInstance()->post($req);
        $data = $response->getData();
    }
}
