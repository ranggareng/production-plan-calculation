<?php 
use Simplon\Mysql\PDOConnector;
use Simplon\Mysql\Mysql;
use Ramsey\Uuid\Uuid;

class Database
{
    public static function connect()
    {
        try {
            $pdo = new PDOConnector($_ENV['DB_HOST'],$_ENV['DB_USER'],$_ENV['DB_PASSWORD'],$_ENV['DB'] );
            $pdoConn = $pdo->connect('utf8', []);
        
            return new Mysql($pdoConn);
        } catch (\Exception $e) {
            echo 'MYSQL CONNECTION ERROR, ' . $e->getMessage() . PHP_EOL;
            return false;
        }
    }
}
?>