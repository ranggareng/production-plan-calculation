<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class StockTrans extends CrudModel
{
    public static function getBalanceQty($itemNumber, $shts = 'all'){
        $DB = \Database::connect();
        $query = "SELECT IFNULL(SUM(t_stock_trans_qty),0) as balance FROM t_stock_trans WHERE t_stock_trans_item=:itemNumber";

        if($shts != 'all')
            $query .=" AND t_stock_trans_wh='".$shts."'";

        $result = $DB->fetchColumn($query, ['itemNumber' => $itemNumber]);
        
        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }
}