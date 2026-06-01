<?php
/*
                COPYRIGHT

Copyright 2007 Sergio Vaccaro <sergio@inservibile.org>

This file is part of JSON-RPC PHP.

JSON-RPC PHP is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

JSON-RPC PHP is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with JSON-RPC PHP; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class jsonRPCClient
{
    private $debug;
    private $url;
    private $id;
    private $notification = false;
    private $proxy;

    public function __construct($url, $debug = false, $proxy = '')
    {
        $this->url = $url;
        $this->proxy = $proxy;
        $this->debug = !empty($debug);
        $this->id = 1;
    }

    public function setRPCNotification($notification)
    {
        $this->notification = !empty($notification);
    }

    public function __call($method, $params)
    {
        if (!is_scalar($method)) {
            throw new Exception('Method name has no scalar value');
        }

        if (!is_array($params)) {
            throw new Exception('Params must be given as array');
        }

        $params = array_values($params);
        $currentId = $this->notification ? null : $this->id++;

        $request = json_encode(
            [
                'method' => $method,
                'params' => $params,
                'id' => $currentId,
            ],
            JSON_UNESCAPED_SLASHES
        );

        if ($request === false) {
            throw new Exception('Unable to encode JSON-RPC request');
        }

        $headers = [
            'Content-Type: application/json',
            'Connection: close',
        ];

        if ($this->proxy !== '') {
            $contextOptions['http']['proxy'] = $this->proxy;
            $contextOptions['http']['request_fulluri'] = true;
        }

        $contextOptions['http']['method'] = 'POST';
        $contextOptions['http']['header'] = implode("\r\n", $headers);
        $contextOptions['http']['content'] = $request;
        $contextOptions['http']['ignore_errors'] = true;
        $contextOptions['http']['timeout'] = 30;

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($this->url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new Exception('Unable to connect to ' . $this->url . (!empty($error['message']) ? ': ' . $error['message'] : ''));
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Invalid JSON-RPC response: ' . $response);
        }

        if ($this->notification) {
            return true;
        }

        if (!array_key_exists('id', $decoded) || $decoded['id'] != $currentId) {
            throw new Exception('Incorrect response id');
        }

        if (!empty($decoded['error'])) {
            $message = is_array($decoded['error'])
                ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                : $decoded['error'];
            throw new Exception('Request error: ' . $message);
        }

        return $decoded['result'] ?? null;
    }
}
?>
