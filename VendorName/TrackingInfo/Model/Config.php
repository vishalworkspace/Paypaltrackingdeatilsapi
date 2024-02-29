<?php

namespace VendorName\TrackingInfo\Model;

class Config
{
    protected $clientId;
    protected $clientSecret;

    public function __construct(
        $clientId,
        $clientSecret
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getClientSecret()
    {
        return $this->clientSecret;
    }
}
