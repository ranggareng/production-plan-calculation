<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class Sales
{
    public static function getSalesSummaryForPPCByItem($itemNumber, $ppcDate){
        $DB = \Database::connect();
        $result = $DB->fetchRowMany("SELECT t_sales_line_item, sum(t_sales_line_qty) as qty, t_sales_line_delv FROM t_sales_line JOIN m_item ON m_item_number=t_sales_line_item WHERE t_sales_line_item=:itemNumber AND t_sales_line_delv>=:ppcDate GROUP BY t_sales_line_delv, t_sales_line_item", ["itemNumber" => $itemNumber, "ppcDate" => $ppcDate]);
        
        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        $response = [];
        foreach($result as $key => $sales){
            $groupByMonth = date('Y-m', strtotime($sales['t_sales_line_delv']));

            if(isset($response[$groupByMonth])){
                array_push($response[$groupByMonth], [
                    'date' => $sales['t_sales_line_delv'],
                    'shipping_qty' => $sales['qty']
                ]);
            }else{
                $response[$groupByMonth] = [
                    [
                        'date' => $sales['t_sales_line_delv'],
                        'shipping_qty' => $sales['qty']
                    ]
                ];
            }            
        }
        return $response;
    }

    public static function getTotalOrderByMonth($item, $month, $year){
        $DB = \Database::connect();
        $query = "SELECT *, SUM(t_sales_line_qty) as total FROM t_sales_line WHERE t_sales_line_item =:item AND MONTH(t_sales_line_delv) =:month AND YEAR(t_sales_line_delv)=:year";

        $result = $DB->fetchRow($query, ['item' => $item, 'year' => $year, 'month' => $month]);

        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }
}