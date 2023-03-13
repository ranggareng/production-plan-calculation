<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class SalesForecast
{
    public static function getSalesSummaryForPPCByItemPerDate($itemNumber, $ppcDate){
        $DB = \Database::connect();
        $result = $DB->fetchRowMany("SELECT t_sales_fc_item, sum(t_sales_fc_qty) as qty, t_sales_fc_date FROM t_sales_fc JOIN m_item ON m_item_number = t_sales_fc_item WHERE t_sales_fc_item=:itemNumber AND t_sales_fc_date>=:ppcDate GROUP BY t_sales_fc_date, t_sales_fc_item", ["itemNumber" => $itemNumber, "ppcDate" => $ppcDate]);
        
        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        $response = [];
        foreach($result as $key => $sales){
            $groupByMonth = date('Y-m', strtotime($sales['t_sales_fc_date']));

            if(isset($response[$groupByMonth])){
                array_push($response[$groupByMonth], [
                    'date' => $sales['t_sales_fc_date'],
                    'shipping_qty' => $sales['qty']
                ]);
            }else{
                $response[$groupByMonth] = [
                    [
                        'date' => $sales['t_sales_fc_date'],
                        'shipping_qty' => $sales['qty']
                    ]
                ];
            }            
        }
        return $response;
    }

    public static function getTotalOrderByMonth($item, $month, $year){
        $DB = \Database::connect();
        $query = "SELECT *, SUM(t_sales_fc_qty) as total FROM t_sales_fc WHERE t_sales_fc_item =:item AND MONTH(t_sales_fc_Date) =:month AND YEAR(t_sales_fc_date)=:year";

        $result = $DB->fetchRow($query, ['item' => $item, 'year' => $year, 'month' => $month]);

        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }
}