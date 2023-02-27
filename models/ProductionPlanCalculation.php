<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class ProductionPlanCalculation extends CrudModel
{
    public static function getLockedPlan($item){
        $DB = \Database::connect();
        $result = $DB->fetchRowMany("SELECT * FROM t_production_plan_calculation WHERE t_production_plan_calculation_is_forecast = 0 AND t_production_plan_calculation_locked = 1 AND t_production_plan_calculation_item = '".$item."'");

        if(!$result){
            $DB->close();
            return false;
        }
    
        $DB->close();
        $response = [];
        if($result)
            foreach($result as $key => $ppc){
                var_dump($ppc);
                $response[$ppc["t_production_plan_calculation_date"]] = $ppc["t_production_plan_calculation_production"];
            }

        return $response;
    }

    public static function getPPCByItemAndRangeDate($item, $startDate, $endDate, $response, $isForecast){
        $DB = \Database::connect();
        
        $query = "SELECT t_production_plan_calculation.*, FLOOR((t_production_plan_calculation_stock_after_prod_and_shipping/(t_production_plan_calculation_item_minimal_stock/t_production_plan_calculation_item_lead_time))) as stock_on_line, concat(YEAR(t_production_plan_calculation_date),'-',LPAD(MONTH(t_production_plan_calculation_date),2,'0')) as date_identifier
        FROM t_production_plan_calculation 
        WHERE t_production_plan_calculation_is_forecast = ".$isForecast."
            AND t_production_plan_calculation_date >= '".$startDate."'
            AND t_production_plan_calculation_item = '".$item."'";

        if(!empty($endDate))
            $query .=" And t_production_plan_calculation_date <='".$endDate."'";

        $query .= " ORDER BY t_production_plan_calculation_date ASC";
        $result = $DB->fetchRowMany($query);

        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }

    public static function create($plans, $productItem, $minimalStock, $leadTime, $perLot, $isForecast){
        $DB = \Database::connect();
        
        try{
            if(!empty($plans)){
                $DB->transactionBegin();
                $DB->delete(
                    't_production_plan_calculation', 
                    ['productItem' => $productItem, 'planDate' => array_keys($plans)[0], 'isForecast' => $isForecast],
                    't_production_plan_calculation_item=:productItem AND t_production_plan_calculation_date>=:planDate AND t_production_plan_calculation_is_forecast=:isForecast'
                );

                $arrInsert = [];
                foreach($plans as $key => $item){
                    array_push($arrInsert, array(
                        't_production_plan_calculation_item'                            => $productItem,
                        't_production_plan_calculation_item_minimal_stock'              => $minimalStock,
                        't_production_plan_calculation_item_lead_time'                  => $leadTime,
                        't_production_plan_calculation_item_per_lot'                    => $perLot,
                        't_production_plan_calculation_shipping'                        => $item['shipping'],
                        't_production_plan_calculation_production'                      => $item['prod'],
                        't_production_plan_calculation_last_stock'                      => $item['l_s'],
                        't_production_plan_calculation_wip_stock'                       => $item['wip_stock'],
                        't_production_plan_calculation_return_stock'                    => $item['return_stock'],
                        't_production_plan_calculation_stock_after_prod_and_shipping'   => $item['after_stock'],
                        't_production_plan_calculation_date'                            => $key,
                        't_production_plan_calculation_is_working_day'                  => $item['is_working_day'],
                        't_production_plan_calculation_is_forecast'                     => $isForecast,
                        't_production_plan_calculation_locked'                          => $item['is_locked']
                    ));
                }

                $DB->insertMany('t_production_plan_calculation', $arrInsert);

                $DB->transactionCommit();
                return true;
            }else{
                return false;
            }
        }catch(\Exception $e){
            $DB->transactionRollback();
            echo  "Exceptions: ".$e->getMessage() . PHP_EOL;
            return false;
        }
    }

    public static function getOneByDate($itemNumber, $date, $isForecast = 'all')
    {
        $DB = \Database::connect();
        $query = "SELECT * FROM t_production_plan_calculation WHERE t_production_plan_calculation_item=:itemNumber AND t_production_plan_calculation_date=:date";
        $data = ["itemNumber" => $itemNumber, "date" => $date];

        if($isForecast != 'all'){
            $query .=" AND t_production_plan_calculation_is_forecast=:isForecast";
            $data = array_merge($data, ["isForecast" => $isForecast]);
        }
            
        $result = $DB->fetchRow($query, $data);
        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }
}