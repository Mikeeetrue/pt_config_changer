<?php

namespace Binance;


class ExtendedApi extends API
{
    public function prevDay($symbol = '') {
        if(!empty($symbol))
            return $this->httpRequest("v1/ticker/24hr", "GET", ["symbol" => $symbol]);
        else
            return $this->httpRequest("v1/ticker/24hr", "GET", []);
    }
}