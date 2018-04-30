<?php

namespace Classes;


use GuzzleHttp\Client;

class ProfitTrailer
{

    /**
     * @var Client
     */
    private $_guzzle = null;

    private $_license = '';

    private $_apiToken = '';
    public function __construct(Client $guzzle, $license, $apiToken)
    {
        $this->_guzzle = $guzzle;
        $this->_license = $license;
        $this->_apiToken = $apiToken;
    }

    public function getConfigData($fileName): string
    {
        $result = $this->_guzzle->request('POST', '/settingsapi/settings/load', [
            'query' => [
                'license' => $this->_license,
                'fileName' => strtoupper($fileName)
            ]

        ]);

        if ($result->getStatusCode() != 200) {
            throw new \Exception('Cant get '.$fileName.' file from PT');
        }
        $pairsDataArray = json_decode($result->getBody()->getContents());
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception('Cant decode '.$fileName.' file from PT');
        }
        return implode(PHP_EOL, $pairsDataArray);
    }
    public function saveConfigData($fileName,$data): string
    {
        $result = $this->_guzzle->request('POST', '/settingsapi/settings/save', [
            'query' => [
                'license' => $this->_license,
                'fileName' => $fileName
            ],
            'form_params' =>
                [
                    'saveData' => $data
                ]

        ]);

        if ($result->getStatusCode() != 200) {
            throw new \Exception('Cant save '.$fileName.' file from PT');
        }

        return true;
    }

    public function getPairsLog(): array
    {
        //pairs log
        $result = $this->_guzzle->request('GET', '/api/pairs/log', [
            'query' => [
                'token' => $this->_apiToken,
            ]

        ]);

        if ($result->getStatusCode() != 200) {
            throw new \Exception('Cant get pairs log from PT');
        }
        $pairsLogData = json_decode($result->getBody()->getContents());
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception('Cant decode pairs log from PT');
        }

        return $pairsLogData;
    }

    public function getDcaLog(): array
    {
        //pairs log
        $result = $this->_guzzle->request('GET', '/api/dca/log', [
            'query' => [
                'token' => $this->_apiToken,
            ]

        ]);

        if ($result->getStatusCode() != 200) {
            throw new \Exception('Cant get dca log from PT');
        }
        $pairsLogData = json_decode($result->getBody()->getContents());
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception('Cant decode dca log from PT');
        }

        return $pairsLogData;
    }

}