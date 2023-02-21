<?php
use GuzzleHttp\Client;

class HttpClient
{
    public static function get($path , $method = 'GET' , $params)
    {
        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => $_ENV['BASE_URL'], 'timeout' => $_ENV['TIME_OUT'], 'connect_timeout' => $_ENV['CONNECT_TIMEOUT']
            ]);

            $res = $client->request($method , $path , $params);

            if ($res->getStatusCode() == 200) {
                $res_body = json_decode($res->getBody());
                return $res_body;
            }

            return false;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return false;
        }
    }

}
?>
