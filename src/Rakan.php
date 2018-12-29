<?php

namespace TELstatic\Rakan;

use TELstatic\Rakan\Interfaces\GatewayApplicationInterface;

class Rakan
{
    public static function Author()
    {
        return [
            'author' => 'TELstatic',
            'email'  => 'telstatic@gmail.com',
        ];
    }

    public static function __callStatic($method, $arguments)
    {
        $app = new self(...$arguments);

        return $app->create($method);
    }

    public function create($method)
    {
        $gateway = __NAMESPACE__.'\\Gateways\\'.ucwords($method);

        return self::make($gateway);
    }

    protected function make($gateway)
    {
        $app = new $gateway();
        if ($app instanceof GatewayApplicationInterface) {
            return $app;
        } else {
            throw new \Exception('Unkow gateway');
        }
    }
}
