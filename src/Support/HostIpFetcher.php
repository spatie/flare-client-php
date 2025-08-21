<?php

namespace Spatie\FlareClient\Support;

use Throwable;

class HostIpFetcher
{
    public static function fetch(): ?string
    {
        return $_SERVER['SERVER_ADDR']
            ?? self::getLocalIpAddress()
            ?? null;
    }

    private static function getLocalIpAddress(): ?string
    {
        if(! extension_loaded('sockets')){
            return null;
        }

        try {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if (!$socket) {
                return null;
            }

            if (!socket_connect($socket, '8.8.8.8', 80)) { // UDP so no data is sent
                socket_close($socket);

                return null;
            }

            if (!socket_getsockname($socket, $ip)) {
                socket_close($socket);
                return null;
            }

            socket_close($socket);

            return $ip;
        }catch (Throwable){
            return null;
        }
    }
}
