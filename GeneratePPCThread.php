<?php
// error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

use Aza\Components\LibEvent\EventBase;
use Aza\Components\Thread\Exceptions\Exception;
use Aza\Components\Thread\SimpleThread;
use Aza\Components\Thread\Thread;
use Aza\Components\Thread\ThreadPool;

require __DIR__ . '/vendor/autoload.php';
require_once("./models/Sales.php");
require_once("./models/ProductionPlanCalculation.php");
require_once("./models/QueueGeneratePPC.php");
require_once("./models/StockTrans.php");
require_once("./models/StockWIP.php");
require_once("./models/StockWIPReturn.php");
require_once("./models/WorkDay.php");
require_once("./models/SalesForecast.php");
require_once("ProductionPlanCalculationService.php");

date_default_timezone_set('Asia/Jakarta');
echo PHP_EOL,'Generate Production Plan Calculation Service | Service Running ...', PHP_EOL;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

class GeneratePPCThread extends Thread
{
	/**
	 * {@inheritdoc}
	 */
	function process()
	{
		try {
            $workingDayPerMonth = \WorkDay::getTotalWorkingDayPerMonth();
            $arrDayOff = array_column(\WorkDay::getOffDay(), 'm_work_day_date');
            $today = date('Y-m').'-01';
            $defaultPerLot = 12;
            $defaultLeadTime = 5;
            $defaultMinStock = 24;

            $products = \QueueGeneratePPC::getQueue('sales');
            if($products){
                foreach($products as $key => $product){
                    echo "Generate PPC Product Number ".$product['m_item_number']."\n";
                    $salesPerDay = \Sales::getSalesSummaryForPPCByItem($product['m_item_number'], date('Y-m-d'));
                    $leadTime = $product['m_item_lead_time'] ? $product['m_item_lead_time'] : $defaultLeadTime;
                    $perLot = $product['m_item_qty_box'] ? $product['m_item_qty_box'] : $defaultPerLot;
                    $arrResponse = [];
    
                    if($salesPerDay){
                        foreach($salesPerDay as $month => $data){
                            echo "Generate PPC Product Number ".$product['m_item_number']." - Calculate PPC ".$month."\n";
                            $lockedPPC = \ProductionPlanCalculation::getLockedPlan($product['m_item_number']);
                            $startDate = date('Y-m-d', strtotime($month));
                            if($startDate < date('Y-m-d'))
                                $startDate = date('Y-m-d');
        
                            $firstProductionDate = $startDate;
                            $incWorkingDay = 0;
                            while($incWorkingDay < $leadTime){
                                if(!in_array($firstProductionDate, $arrDayOff))
                                    $incWorkingDay++;
        
                                $firstProductionDate = date('Y-m-d', strtotime('-1 day', strtotime($firstProductionDate)));
                            }
        
                            $dataFromLastMonth = \ProductionPlanCalculation::getPPCByItemAndRangeDate($product['m_item_number'], $firstProductionDate, date('Y-m-t', strtotime($firstProductionDate)), 'array', 0);
        
                            $totalOrderQty = \Sales::getTotalOrderByMonth($product['m_item_number'], date('m', strtotime($month)), date('Y', strtotime($month)))['total'];
                            $totalWorkingDay = $workingDayPerMonth[$month];
                            $minStock = ceil($totalOrderQty/$totalWorkingDay*$leadTime);
                            $lastStock = \StockTrans::getBalanceQty($product['m_item_number']);
        
                            $wipStock =  \StockWIP::getOne($product['m_item_number'], $startDate);
                            $returnStock = \StockWIPReturn::getOne($product['m_item_number'], $startDate);
        
                            $maxProduction = is_null($product['m_item_max_prod']) ? 999999999 : $product['m_item_max_prod'];
                            
                            $ppcService =  new ProductionPlanCalculationService();
                            $ppcService->setMinStock($minStock);
                            $ppcService->setLeadTime($leadTime);
                            $ppcService->setPerLot($perLot);
                            $ppcService->setReturnStock($returnStock);
                            $ppcService->setWipStock($wipStock);
                            $ppcService->setlastStock($lastStock);
                            $ppcService->setStartDate($startDate);
                            $ppcService->setDayOff($arrDayOff);
                            $ppcService->setCalculationFromLastMonth($dataFromLastMonth);
                            $ppcService->setLockedProduction($lockedPPC);
                            $ppcService->setMaxProduction($maxProduction);
                            $ppcService->setTotalShipping($totalOrderQty);
                            $ppcService->setSales($data);
                            $arrPPC = $ppcService->calculate();
        
                            $create = \ProductionPlanCalculation::create($arrPPC, $product['m_item_number'], $minStock, $leadTime, $perLot, 0);
        
                            if($create){
                                \QueueGeneratePPC::updateLineStatus($product['m_item_number'], 'sales');
                            }else{
                                \QueueGeneratePPC::updateLineStatus($product['m_item_number'], 'sales');
                            }
                        }
                    }else{
                        \QueueGeneratePPC::updateLineStatus($product['m_item_number'], 'sales');
                    }                
                }

                $this->do_generate_forecast();
            }else{
                \QueueGeneratePPC::updateHeadStatus();
            }

            return true;
		} catch (\Exception $e) {
			echo  $e;
            echo  PHP_EOL;
			return false;
		}
	}

    public function do_generate_forecast(){
        $workingDayPerMonth = \WorkDay::getTotalWorkingDayPerMonth();
        $arrDayOff = array_column(\WorkDay::getOffDay(), 'm_work_day_date');
        $today = date('Y-m').'-01';
        $defaultPerLot = 12;
        $defaultLeadTime = 5;
        $defaultMinStock = 24;
        
        $products = \QueueGeneratePPC::getQueue('forecast');
        $arrResponse = [];

        if($products){
            foreach($products as $key => $product){
                echo "Generate PPC Forecast Product Number ".$product['m_item_number']."\n";
                $salesPerDay = \SalesForecast::getSalesSummaryForPPCByItemPerDate($product['m_item_number'], date('Y-m-d'));
                $leadTime = $product['m_item_lead_time'] ? $product['m_item_lead_time'] : $defaultLeadTime;
                $perLot = $product['m_item_qty_box'] ? $product['m_item_qty_box'] : $defaultPerLot;
    
                if($salesPerDay){
                    foreach($salesPerDay as $month => $data){
                        $lockedPPC = \ProductionPlanCalculation::getLockedPlan($product['m_item_number']);
                        echo "Generate PPC Forecast Product Number ".$product['m_item_number']." - Calculate PPC ".$month."\n";

                        $dataTypeFromLastMonth = "sales";
    
                        // var_dump($data);
                        $startDate = date('Y-m-d', strtotime($month));
                    
                        echo $startDate."\n";

                        if($startDate < date('Y-m-d'))
                            $startDate = date('Y-m-d');
    
                        // echo "Start Date for item ".$product->m_item_number." is ".$startDate."\n";
    
                        $firstProductionDate = $startDate;
                        $incWorkingDay = 0;
                        while($incWorkingDay < $leadTime){
                            if(!in_array($firstProductionDate, $arrDayOff))
                                $incWorkingDay++;
    
                            $firstProductionDate = date('Y-m-d', strtotime('-1 day', strtotime($firstProductionDate)));
                            // echo $firstProductionDate."\n";
    
                            if($firstProductionDate <= date('Y-m-d')){
                                $firstProductionDate = date('Y-m-d');
                                break;
                            }                        
                        }
    
                        // echo "first production date ".$firstProductionDate."\n";
                        $dataFromLastMonth = \ProductionPlanCalculation::getPPCByItemAndRangeDate($product['m_item_number'], $firstProductionDate, date('Y-m-t', strtotime($firstProductionDate)), 'array', 0);
                        $dataForecastFromLastMonth = \ProductionPlanCalculation::getPPCByItemAndRangeDate($product['m_item_number'], $firstProductionDate, date('Y-m-t', strtotime($firstProductionDate)), 'array', 1);
                        $dataFromFirstProdDate = \ProductionPlanCalculation::getOneByDate($product['m_item_number'], $firstProductionDate, "0");
                        $dataForecastFromFirstProdDate = \ProductionPlanCalculation::getOneByDate($product['m_item_number'], $firstProductionDate, "1");

                        // var_dump($dataFromLastMonth);

                        if($dataFromFirstProdDate){
                            $lastStock = $dataFromFirstProdDate['t_production_plan_calculation_last_stock'];
                        }else if($dataForecastFromFirstProdDate){
                            $lastStock = $dataForecastFromFirstProdDate['t_production_plan_calculation_last_stock'];
                        }else{
                            $lastStock = \SalesTrans::getBalanceQty($product['m_item_number']);
                        }
    
                        if(empty($dataFromLastMonth) && !empty($dataForecastFromLastMonth)){
                            $dataFromLastMonth = $dataForecastFromLastMonth;
                            $dataTypeFromLastMonth = "forecast";
                        }                        
                        
                        $totalOrderQty = \SalesForecast::getTotalOrderByMonth($product['m_item_number'], date('m', strtotime($month)), date('Y', strtotime($month)))['total'];
                        $totalWorkingDay = $workingDayPerMonth[$month];
                        $minStock = ceil($totalOrderQty/$totalWorkingDay*$leadTime);
                        
                        // var_dump(\SalesForecast::getTotalOrderByMonth($product['m_item_number'], date('m', strtotime($month)), date('Y', strtotime($month))));
                        // echo "Total Order Qty: ".$totalOrderQty."\n";
                        // echo "Total Working Day: ".$totalWorkingDay."\n";
                        // echo "Total Min Stock: ".$minStock."\n";
                        
                        $wipStock =  \StockWIP::getOne($product['m_item_number'], $firstProductionDate);
                        $returnStock = \StockWIPReturn::getOne($product['m_item_number'], $firstProductionDate);
                        $maxProduction = is_null($product['m_item_max_prod']) ? 999999999 : $product['m_item_max_prod'];

                        $ppc = new ProductionPlanCalculationService();
                        $ppc->setMinStock($minStock);
                        $ppc->setLeadTime($leadTime);
                        $ppc->setPerLot($perLot);
                        $ppc->setReturnStock($returnStock);
                        $ppc->setWipStock($wipStock);
                        $ppc->setlastStock($lastStock);
                        $ppc->setStartDate($startDate);
                        $ppc->setDayOff($arrDayOff);
                        $ppc->setCalculationFromLastMonth($dataFromLastMonth);
                        $ppc->setMaxProduction($maxProduction);
                        $ppc->setLockedProduction($lockedPPC);
                        $ppc->setTotalShipping($totalOrderQty);
                        $ppc->setSales($data);                
                        $arrPPC = $ppc->calculate();
                        
                        // var_dump($arrPPC);

                        /*
                        * Jika bulan sebelumnya sudah ada data PPC yang menggunakan data sales,
                        * maka irisan data tersebut perlu didelete dan dicreate kembali.
                        * Namun, perlu dicermati bahwa irisan data tersebut harus memiliki
                        * is_forecast yang bernilai 0
                        */
                        if($dataTypeFromLastMonth == 'sales' && !empty($dataFromLastMonth)){
                            $arrDateFromDataLastMonth = array_column($dataFromLastMonth, 't_production_plan_calculation_date');
                            $arrPPCLastMonth = array_filter($arrPPC, function($key) use($arrDateFromDataLastMonth){
                                if(in_array($key, $arrDateFromDataLastMonth))
                                    return true;
                            }, ARRAY_FILTER_USE_KEY);
    
                            $arrPPCCurrentMonth = array_filter($arrPPC, function($key) use($arrDateFromDataLastMonth){
                                if(!in_array($key, $arrDateFromDataLastMonth))
                                    return true;
                            }, ARRAY_FILTER_USE_KEY);
    
                            echo "Type Sales ".$startDate."\n";
                            // var_dump($arrDateFromDataLastMonth);
                            // var_dump($arrPPCLastMonth);
                            // var_dump($arrPPCCurrentMonth);
    
                            $create = \ProductionPlanCalculation::create($arrPPCLastMonth, $product['m_item_number'], $minStock, $leadTime, $perLot, 0);
                            $create = \ProductionPlanCalculation::create($arrPPCCurrentMonth, $product['m_item_number'], $minStock, $leadTime, $perLot, 1);
                            
                        }else{
                            echo "Type forecast ".$startDate."\n";
                            $create = \ProductionPlanCalculation::create($arrPPC, $product['m_item_number'], $minStock, $leadTime, $perLot, 1);
                        }
    
                        if($create){
                            \QueueGeneratePPC::updateLineStatus($product['m_item_number'], 'forecast', true);
                        }else{
                            \QueueGeneratePPC::updateLineStatus($product['m_item_number'], 'forecast', true);
                        }
                    }
                }else{
                    \QueueGeneratePPC::updateLineStatus($product['m_item_number'], 'forecast', true);
                }
            }
        }

        return $arrResponse;
    }
}

$memoryStart = memory_get_usage();
$startTime = date('H:i:s');
$thread = new GeneratePPCThread();
$thread->wait();

do {
	if ($thread->run(/* $DB */)->wait()->getSuccess())
	{
        echo "Memory Start: ".number_format($memoryStart/1024/1024, 2, ',', ".")." MB \n";
        echo "Memory End: ".number_format(memory_get_usage()/1024/1024, 2, ',', ".")." MB \n";
        echo "Memory Peak: ".number_format(memory_get_peak_usage()/1024/1024, 2, ',', ".")." MB \n";
        echo "Time Start: ".$startTime."\n";
        echo "Time End: ".date('H:i:s')."\n";
		echo date('[d/m/Y | H:i:s] ')."Generate PPC Service Running..." . PHP_EOL;
		sleep($_ENV['DELAY']);
	}
	else
	{
		echo 'error' . PHP_EOL;
	}
} while (true);

$thread->cleanup();