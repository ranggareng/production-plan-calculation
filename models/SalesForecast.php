<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class SalesForecast
{
    public static function getSalesSummaryForPPCByItemPerDate($itemNumber, $ppcDate, $arrWorkingDayPerMonth, $arrDayOff){
        $DB = \Database::connect();
        // $result = $DB->fetchRowMany("SELECT t_sales_fc_item, sum(t_sales_fc_qty) as qty, t_sales_fc_date FROM t_sales_fc JOIN m_item ON m_item_number = t_sales_fc_item WHERE t_sales_fc_item=:itemNumber AND t_sales_fc_date>=:ppcDate GROUP BY t_sales_fc_date, t_sales_fc_item", ["itemNumber" => $itemNumber, "ppcDate" => $ppcDate]);
        
        $result = $DB->fetchRowMany("SELECT t_sales_fc_item, SUM(t_sales_fc_qty) AS qty, t_sales_fc_date, MONTH(t_sales_fc_date) AS month, YEAR(t_sales_fc_date) AS year FROM t_sales_fc JOIN m_item ON m_item_number = t_sales_fc_item WHERE t_sales_fc_item=:itemNumber AND t_sales_fc_date>=:ppcDate GROUP BY t_sales_fc_item, YEAR(t_sales_fc_date), MONTH(t_sales_fc_date)", ["itemNumber" => $itemNumber, "ppcDate" => $ppcDate]);

        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        $response = [];

        // var_dump($arrWorkingDayPerMonth);

        foreach($result as $key => $sales){
            $yearMonth = date('Y-m', strtotime($sales['t_sales_fc_date']));
            
            $totalShipping = $sales['qty'];
            $totalShippingDay = ceil($sales['qty']/$arrWorkingDayPerMonth[$yearMonth]);
            
            // Tanggal awal bulan ini
            $start_date = date('Y-m-01', strtotime($sales['t_sales_fc_date']));
            
            // Tanggal akhir bulan ini
            $end_date = date('Y-m-t', strtotime($sales['t_sales_fc_date']));

            for ($date = $start_date; $date <= $end_date; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
                $groupByMonth = date('Y-m', strtotime($date));

                if(!in_array($date, $arrDayOff)){ // Jika bukan hari libur
                    if(isset($response[$groupByMonth])){
                        array_push($response[$groupByMonth], [
                            'date' => $date,
                            'shipping_qty' => $totalShippingDay
                        ]);
                    }else{
                        $response[$groupByMonth] = [
                            [
                                'date' => $date,
                                'shipping_qty' => $totalShippingDay
                            ]
                        ];
                    }
                }
            }
        }

        // foreach($result as $key => $sales){
        //     $groupByMonth = date('Y-m', strtotime($sales['t_sales_fc_date']));

        //     if(isset($response[$groupByMonth])){
        //         array_push($response[$groupByMonth], [
        //             'date' => $sales['t_sales_fc_date'],
        //             'shipping_qty' => $sales['qty']
        //         ]);
        //     }else{
        //         $response[$groupByMonth] = [
        //             [
        //                 'date' => $sales['t_sales_fc_date'],
        //                 'shipping_qty' => $sales['qty']
        //             ]
        //         ];
        //     }            
        // }
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