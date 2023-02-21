<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class StockWIP extends CrudModel
{
    public static function getOne($itemNumber, $date){
        $DB = \Database::connect();
        $result = $DB->fetchColumn("SELECT t_stock_wip_qty FROM t_stock_wip WHERE t_stock_wip_item=:itemNumber AND t_stock_wip_date=:wipDate", ['itemNumber' => $itemNumber, 'wipDate'=>$date]);
        
        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }
}