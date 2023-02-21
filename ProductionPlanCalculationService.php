<?php
Class ProductionPlanCalculationService{

    private $minStock;
    private $leadTime;
    private $perLot;
    private $lastStock;
    private $wipStock;
    private $returnStock;
    private $arrDayOff = [];
    private $arrSales = [];
    private $calculationFromLastMonth = [];
    private $date;
    private $startDate;
    private $lockedProduction = [];
    private $maxProduction;
    private $totalShipping = 0;

    public function __construct(){
        $this->date = date('Y-m-d');
    }

    /*
     * Mencari tau batasan looping yang akan dilakukan
     * ketika proses penghitungan PPC pada fungsi calculate.
     * Untuk proses pencariannya dilakukan dengan cara mengextract
     * data date pada variable $arrSales dan kemudian sort array tersebut
     * secara DESC dan ambil data paling awal
     * 
     * @return {date} Return latest date
     * 
     */
    public function getLastLoopingDate()
    {
        $arrDateFromSales = array_column($this->arrSales, 'date'); // Extract array dengan key 'date'
        rsort($arrDateFromSales); // sort array hasil extraxt secara desc

        return $arrDateFromSales[0]; // return data paling pertama
    }

    /*
     * Mendapatkan tanggal production date berdasarkan looping day dan day off.
     * 
     * Cara kerjanya adalah dengan mendapatkan list tanggal berdasarkan
     * parameter dan kemudian akan dicompare dengan data $arrDayOff.
     * Jika data ditemukan di variable $arrDayOff, maka take out dari
     * variabel workDay
     * 
     * @param {array} looping day
     * @param {array} dayOff
     * @return {array} Returns array baru berisi work days
     * 
     */ 
    public function getWorkingDays($loopingDay){
        $loopingDay = $this->getLoopingDays($startDate, $endDate);
        $workDays = array_diff($loopingDay, $tgis->arrDayOff);
        return $workDays;
    }

    /*
     * Mendapatkan looping day berdasarkan start date dan end date.
     * 
     * @param startDate untuk start looping
     * @param endDate untuk akhir looping
     * @return {array} Returns array baru berisi looping day
     * 
     */ 
    function getLoopingDays($startDate, $endDate){
        $date = $startDate;
        $arrDays = [];
        while(strtotime($date) <= strtotime($endDate)){
            array_push($arrDays, $date);
            $date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
        }

        return $arrDays;
    }

    public function calculate(){
        // var_dump($this->calculationFromLastMonth);
        $arrayPlans = [];
        $endDate = date('Y-m-t', strtotime($this->date));
        
        /*
         * Untuk memudahkan dalam perhitungan PPC
         * kita set dulu production date untuk setiap
         * shipping date.
         *
         * Hal ini dikarenakan ada kemungkinan dalam 1 hari
         * harus memproduksi barang untuk beberapa shipping date
         *
         */
        while(strtotime($this->date) <= strtotime($endDate)){       
            
            /*
             * Cek apakah production date adalah hari libur,
             * Jika production date adalah hari libur,
             * maka mundurkan hari (H-1) hingga mendapatkan
             * hari kerja terakhir sebelum prod date
             */
            $countOfWorkingDay = 0;
            $dateMinOneDay = $this->date;
            $arrWorkingDaysUntilHMinLateTime = []; // Array untuk menampung working day dari Shipping date hingga production date
            while($countOfWorkingDay < $this->leadTime && $dateMinOneDay >= date('Y-m-d')){
                /*
                * Setup array kosong untuk tanggal yang berkaitan
                * yang sedang berjalan
                */
                if(!isset($arrayPlans[$dateMinOneDay])){
                    if(in_array($dateMinOneDay, array_column($this->calculationFromLastMonth, 't_production_plan_calculation_date'))){
                        $arrayIndex = array_search($dateMinOneDay, array_column($this->calculationFromLastMonth, 't_production_plan_calculation_date'));
                        $arrayPlans[$dateMinOneDay] = [
                            'date' => date('d/m', strtotime($this->date)),
                            'is_workday' => false, 
                            'shipping' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_shipping'],
                            'prod_for_shipping_date' => [],
                            'prod' => isset($this->lockedProduction[$dateMinOneDay]) ? $this->lockedProduction[$dateMinOneDay] : $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_production'],
                            'wip_stock' => 0,
                            'return_stock' => 0,
                            'l_s' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_last_stock'],
                            'after_stock' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_stock_after_prod_and_shipping'],
                            'total_prod_for_shipping' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_total_for_shipping'],
                            'working_day_until_shipping_date' => [],
                            'is_working_day' => !in_array($dateMinOneDay, $this->arrDayOff) ? true : false,
                            'is_locked' => isset($this->lockedProduction[$dateMinOneDay]) ? true : false
                        ];

                        // echo $dateMinOneDay.' Trigger 1 '.(isset($this->lockedProduction[$dateMinOneDay]) ? $this->lockedProduction[$dateMinOneDay] : $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_production'])."\n";
                        // echo $dateMinOneDay.' Trigger 1 Prod: '.$arrayPlans[$dateMinOneDay]['prod']."\n";
                        // echo $dateMinOneDay.' Trigger 1 Ship: '.$arrayPlans[$dateMinOneDay]['shipping']."\n";
                        // var_dump($arrayPlans[$dateMinOneDay]);
                    }else{
                        $arrayPlans[$dateMinOneDay] = [
                            'date' => date('d/m', strtotime($this->date)),
                            'is_workday' => false, 
                            'shipping' => 0,
                            'prod_for_shipping_date' => [],
                            'prod' => isset($this->lockedProduction[$dateMinOneDay]) ? $this->lockedProduction[$dateMinOneDay] : 0,
                            'wip_stock' => 0,
                            'return_stock' => 0,
                            'l_s' => 0,
                            'after_stock' => 0,
                            'total_prod_for_shipping' => 0,
                            'working_day_until_shipping_date' => [],
                            'is_working_day' => !in_array($dateMinOneDay, $this->arrDayOff) ? true : false,
                            'is_locked' => isset($this->lockedProduction[$dateMinOneDay]) ? true : false
                        ];

                        // echo $dateMinOneDay.' Trigger 2 '.(isset($this->lockedProduction[$dateMinOneDay]) ? $this->lockedProduction[$dateMinOneDay] : 0)."\n";
                        // echo $dateMinOneDay.' Trigger 2 Prod: '.$arrayPlans[$dateMinOneDay]['prod']."\n";
                        // echo $dateMinOneDay.' Trigger 2 Ship: '.$arrayPlans[$dateMinOneDay]['shipping']."\n";
                    }
                }
                
                if(in_array($dateMinOneDay, array_column($this->arrSales, 'date'))){
                    $arrIndexShipping = array_search($dateMinOneDay, array_column($this->arrSales, 'date'));
                    array_push(
                        $arrWorkingDaysUntilHMinLateTime, [
                            'shipping_date' => $dateMinOneDay,
                            'shipping_qty' => $this->arrSales[$arrIndexShipping]['shipping_qty']
                        ]
                    );
                }else{
                    array_push(
                        $arrWorkingDaysUntilHMinLateTime, [
                            'shipping_date' => $dateMinOneDay,
                            'shipping_qty' => 0
                        ]
                    );
                }
                
                $newDateMinOneDay = date('Y-m-d', strtotime('-1 day', strtotime($dateMinOneDay)));
                if($newDateMinOneDay >= date('Y-m-d')){
                    $dateMinOneDay = $newDateMinOneDay;
                    if(!in_array($dateMinOneDay, $this->arrDayOff)){
                        $countOfWorkingDay++;
                    }
                }else{
                    break;
                }

                // echo "setEmtpyArray on date ".$dateMinOneDay."\n";
            }

            /*
             * set production Date setelah mendapatkan H-5 
             * working days dari tanggal shipping
             *
             */
            $prodDate = $dateMinOneDay;
            
            /*
             * Setup array kosong jika data berdasarkan 
             * actual production date belum terbentuk
             *
             */
            if(!isset($arrayPlans[$prodDate])){
                if(in_array($prodDate, array_column($this->calculationFromLastMonth, 't_production_plan_calculation_date'))){
                    $arrayIndex = array_search($prodDate, array_column($this->calculationFromLastMonth, 't_production_plan_calculation_date'));
                    $arrayPlans[$prodDate] = [
                        'date' => date('d/m', strtotime($this->date)),
                        'is_workday' => false, 
                        'shipping' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_shipping'],
                        'prod_for_shipping_date' => [],
                        'prod' =>  isset($this->lockedProduction[$prodDate]) ? $this->lockedProduction[$prodDate] : $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_production'],
                        'wip_stock' => 0,
                        'return_stock' => 0,
                        'l_s' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_last_stock'],
                        'after_stock' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_stock_after_prod_and_shipping'],
                        'total_prod_for_shipping' => $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_total_for_shipping'],
                        'working_day_until_shipping_date' => [],
                        'is_working_day' => !in_array($prodDate, $this->arrDayOff) ? true : false,
                        'is_locked' => isset($this->lockedProduction[$prodDate]) ? true : false
                    ];

                    // echo $prodDate.' Trigger 3 '.(isset($this->lockedProduction[$prodDate]) ? $this->lockedProduction[$prodDate] : $this->calculationFromLastMonth[$arrayIndex]['t_production_plan_calculation_production'])."\n";
                    // echo $prodDate.' Trigger 3 Prod: '.$arrayPlans[$prodDate]['prod']."\n";
                    // echo $prodDate.' Trigger 3 Ship: '.$arrayPlans[$prodDate]['shipping']."\n";
                }else{
                    $arrayPlans[$prodDate] = [
                        'date' => date('d/m', strtotime($prodDate)),
                        'shipping' => 0,
                        'prod_for_shipping_date' => [],
                        'prod' => isset($this->lockedProduction[$prodDate]) ? $this->lockedProduction[$prodDate] : 0,
                        'wip_stock' => 0,
                        'return_stock' => 0,
                        'l_s' => 0,
                        'after_stock' => 0,
                        'total_prod_for_shipping' => 0,
                        'working_day_until_shipping_date' => $arrWorkingDaysUntilHMinLateTime,
                        'is_working_day' => !in_array($prodDate, $this->arrDayOff) ? true : false,
                        'is_locked' => isset($this->lockedProduction[$prodDate]) ? true : false
                    ];    
                    // echo $prodDate.' Trigger 4 '.(isset($this->lockedProduction[$prodDate]) ? $this->lockedProduction[$prodDate] : 0)."\n";
                    // echo $prodDate.' Trigger 4 Prod: '.$arrayPlans[$prodDate]['prod']."\n";
                    // echo $prodDate.' Trigger 4 Ship: '.$arrayPlans[$prodDate]['shipping']."\n";
                }
            }else{
                $arrayPlans[$prodDate]['working_day_until_shipping_date'] = $arrWorkingDaysUntilHMinLateTime;
            }
            
            /*
             * Lakukan pengecekan apakah shipping date saat ini
             * ada di data sales/shipping terkecuali jika data merupakan dari bulan sebelumnya
             *
             */
            if(!in_array($this->date, array_column($this->calculationFromLastMonth, 't_production_plan_calculation_date'))){
                // echo $this->date.' Trigger 5 Ship Before: '.$arrayPlans[$this->date]['shipping']."\n";
                if(in_array($this->date, array_column($this->arrSales, 'date'))){

                    // Get index data dari array shipping
                    $arrIndexShipping  = array_search($this->date, array_column($this->arrSales, 'date'));
                    
                    // Get shipping qty dari array shipping
                    $shipping = $this->arrSales[$arrIndexShipping]['shipping_qty'];
                    
                    // Update informasi shipping quantity
                    $arrayPlans[$this->date]['shipping'] = $shipping;
                    
                    // Masukkan informasi shipping pada data sesuai actual production date
                    array_push(
                        $arrayPlans[$prodDate]['prod_for_shipping_date'], 
                        [
                            "shipping_date" => $this->date,
                            "shipping_qty" => $shipping
                        ]
                    );
        
                }
                // echo $this->date.' Trigger 5 Ship: '.$arrayPlans[$this->date]['shipping']."\n";
            }
            
            $this->date = date('Y-m-d', strtotime("+1 day", strtotime($this->date)));
            // echo "setEmtpyArray on date 2 ".$this->date."\n";
        }
        
        // Sort data arrayPlans berdasarkan key/tanggal
        ksort($arrayPlans);

        // echo "Library \n";
        // print_r($arrayPlans);

        /*
         * Setelah data terbentuk pada variabel $arrayPlans
         * dan kita mengetahui berapa angka produksi pada
         * setiap tanggalnya dan untuk mengetahui produksi
         * tersebut untuk shipping pada tanggal berapa, 
         * selanjutnya kita menghitung terkait actual productionnya
         * dan last stok pada hari tersebut
         *
         */
        foreach($arrayPlans as $date => $data){
            // reset $prod setiap ganti tanggal
            $prod = 0;
            
            $arrayPlans[$date]['wip_stock'] = ($date == date('Y-m-d')) ? $this->wipStock : 0;
            $arrayPlans[$date]['return_stock'] = ($date == date('Y-m-d')) ? $this->returnStock : 0;

            // Update value l_s berdasarkan tanggal
            $arrayPlans[$date]['l_s'] = $this->lastStock;
            
            // Total shipping berdasarkan tanggal
            $totalShipping = array_sum(array_column($arrayPlans[$date]['prod_for_shipping_date'], 'shipping_qty'));
            
            // Kalkulasi sisa stock setelah dikurangi total shipping
            $stockAfterShipping = ($this->lastStock + $arrayPlans[$date]['wip_stock'] + $arrayPlans[$date]['return_stock']) - array_sum(array_column($arrayPlans[$date]['working_day_until_shipping_date'], 'shipping_qty')) - $arrayPlans[$date]['shipping'];
            
            // echo "stock after shipping ".$stockAfterShipping."\n";

            if(isset($this->lockedProduction[$date])){
                $prod = $this->lockedProduction[$date];
            }else if($stockAfterShipping - $this->minStock < 0 && !in_array($date, $this->arrDayOff)){
                $lotProd = ceil(abs($stockAfterShipping - $this->minStock) / $this->perLot);
                $prod = $this->perLot * $lotProd;
            }

            if($prod > 0){
                // if($arrayPlans[$date]['l_s'] < $this->minStock){
                    if($this->maxProduction < $this->totalShipping){
                        $prod = floor($this->maxProduction/$this->perLot) * $this->perLot;
                    }else{
                        $prod = floor($this->totalShipping/$this->perLot) * $this->perLot;
                    }
                // }else{
                //     $prod = floor($this->maxProduction/$this->perLot) * $this->perLot;
                // }
            }
            
            // Kalkulasi last stock terupdate
            $this->lastStock = $this->lastStock + $prod - $arrayPlans[$date]['shipping'] + $arrayPlans[$date]['wip_stock'] + $arrayPlans[$date]['return_stock'];
            
            // Update value prod, after_stock dan total_prod_for_shipping berdasarkan tanggal
            // echo $date." Before Stock Update: ".$arrayPlans[$date]['prod']."\n";
            // var_dump($arrayPlans[$date]['prod']);
            // echo $date." After Stock Update: ".$prod."\n";
            // echo $date." After Stock Ship: ".$arrayPlans[$date]['shipping']."\n";
            $arrayPlans[$date]['prod'] = $prod;
            $arrayPlans[$date]['after_stock'] = $this->lastStock;
            $arrayPlans[$date]['total_prod_for_shipping'] = $totalShipping;
            
        }

        // echo $this->startDate."\n";
        // var_dump($arrayPlans);

        return $arrayPlans;
    }

    public function recalculate($changeDate, $changeProduction){
        $arrayPlans = [];
        $arrDates = array_column($this->arrSales, 'date');
        
        foreach($arrDates as $key => $date){
            /*
            * Setup array kosong untuk tanggal yang berkaitan
            * yang sedang berjalan
            */
            if(!isset($arrayPlans[$date])){
                $arrayPlans[$date] = [
                    'date' => date('d/m', strtotime($date)),
                    'is_workday' => false, 
                    'shipping' => 0,
                    'prod_for_shipping_date' => [],
                    'prod' => isset($this->lockedProduction[$date]) ? $this->lockedProduction[$date] : 0,
                    'l_s' => 0,
                    'wip_stock' => 0,
                    'return_stock' => 0,
                    'after_stock' => 0,
                    'total_prod_for_shipping' => 0,
                    'working_day_until_shipping_date' => [],
                    'is_working_day' => !in_array($date, $this->arrDayOff) ? true : false,
                    'is_locked' => isset($this->lockedProduction[$date]) ? true : false
                ];
            }
            
            /*
             * Cek apakah production date adalah hari libur,
             * Jika production date adalah hari libur,
             * maka mundurkan hari (H-1) hingga mendapatkan
             * hari kerja terakhir sebelum prod date
             */
            $countOfWorkingDay = 0;
            $datePlusOneDay = $date;
            $arrWorkingDaysUntilHPlusLeadTime = []; // Array untuk menampung working day dari Shipping date hingga production date
            $normalIteration = 1;
            while($countOfWorkingDay < $this->leadTime){
                $datePlusOneDay = date('Y-m-d', strtotime('+1 day', strtotime($datePlusOneDay)));
                if(!in_array($datePlusOneDay, $this->arrDayOff)){
                    $countOfWorkingDay++;
                }

                /*
                * Setup array kosong untuk tanggal yang berkaitan
                * yang sedang berjalan
                */
                if(!isset($arrayPlans[$datePlusOneDay]) && in_array($datePlusOneDay, $arrDates)){
                    $arrayPlans[$datePlusOneDay] = [
                        'date' => date('d/m', strtotime($date)),
                        'is_workday' => false, 
                        'shipping' => 0,
                        'prod_for_shipping_date' => [],
                        'prod' => isset($this->lockedProduction[$date]) ? $this->lockedProduction[$date] : 0,
                        'l_s' => 0,
                        'wip_stock' => 0,
                        'return_stock' => 0,
                        'after_stock' => 0,
                        'total_prod_for_shipping' => 0,
                        'working_day_until_shipping_date' => [],
                        'is_working_day' => !in_array($datePlusOneDay, $this->arrDayOff) ? true : false,
                        'is_locked' => isset($this->lockedProduction[$datePlusOneDay]) ? true : false
                    ];
                }
                
                if(!in_array($date, $this->arrDayOff) && in_array($datePlusOneDay, array_column($this->arrSales, 'date'))){
                    if($normalIteration < $this->leadTime+2 && $countOfWorkingDay == $this->leadTime-1 && in_array($datePlusOneDay, $this->arrDayOff)){
                        // Get index data dari array shipping
                        $arrIndexShipping  = array_search($datePlusOneDay, array_column($this->arrSales, 'date'));

                        // Masukkan informasi shipping pada data sesuai actual production date
                        array_push(
                            $arrayPlans[$date]['prod_for_shipping_date'], 
                            [
                                "shipping_date" => $datePlusOneDay,
                                "shipping_qty" => $this->arrSales[$arrIndexShipping]['shipping_qty']
                            ]
                        );
                    }else if($normalIteration == $this->leadTime+2 && $countOfWorkingDay == $this->leadTime){
                        // Get index data dari array shipping
                        $arrIndexShipping  = array_search($datePlusOneDay, array_column($this->arrSales, 'date'));

                        // Masukkan informasi shipping pada data sesuai actual production date
                        array_push(
                            $arrayPlans[$date]['prod_for_shipping_date'], 
                            [
                                "shipping_date" => $datePlusOneDay,
                                "shipping_qty" => $this->arrSales[$arrIndexShipping]['shipping_qty']
                            ]
                        );
                    }
                }               
                
                if(in_array($datePlusOneDay, array_column($this->arrSales, 'date'))){
                    $arrIndexShipping = array_search($datePlusOneDay, array_column($this->arrSales, 'date'));
                    array_push(
                        $arrWorkingDaysUntilHPlusLeadTime, [
                            'shipping_date' => $datePlusOneDay,
                            'shipping_qty' => $this->arrSales[$arrIndexShipping]['shipping_qty']
                        ]
                    );
                }               

                $normalIteration++;
            }

            /*
             * Setup array kosong jika data berdasarkan 
             * actual production date belum terbentuk
             *
             */
            $arrayPlans[$date]['working_day_until_shipping_date'] = $arrWorkingDaysUntilHPlusLeadTime;
            
            // Get shipping qty dari array shipping
            $arrIndexShipping = array_search($date, array_column($this->arrSales, 'date'));
            $shipping = $this->arrSales[$arrIndexShipping]['shipping_qty'];
            
            // Update informasi shipping quantity
            $arrayPlans[$date]['shipping'] = $shipping;
        }
        
        // Sort data arrayPlans berdasarkan key/tanggal
        ksort($arrayPlans);
        
        /*
         * Setelah data terbentuk pada variabel $arrayPlans
         * dan kita mengetahui berapa angka produksi pada
         * setiap tanggalnya dan untuk mengetahui produksi
         * tersebut untuk shipping pada tanggal berapa, 
         * selanjutnya kita menghitung terkait actual productionnya
         * dan last stok pada hari tersebut
         *
         */
        foreach($arrayPlans as $date => $data){
            // reset $prod setiap ganti tanggal
            $prod = 0;
            
            // Update value l_s berdasarkan tanggal
            $arrayPlans[$date]['l_s'] = $this->lastStock;
            
            // Total shipping berdasarkan tanggal
            $totalShipping = array_sum(array_column($arrayPlans[$date]['prod_for_shipping_date'], 'shipping_qty'));

            if($date == $changeDate){
                $prod = $changeProduction;
            }else if(!in_array($date, $this->arrDayOff)){
                   
                // Kalkulasi sisa stock setelah dikurangi total shipping
                $stockAfterShipping = $this->lastStock - array_sum(array_column($arrayPlans[$date]['working_day_until_shipping_date'], 'shipping_qty')) - $arrayPlans[$date]['shipping'];
                
                if(isset($this->lockedProduction[$date])){
                    $prod = $this->lockedProduction[$date];
                }else if($stockAfterShipping - $this->minStock < 0){
                    $lotProd = ceil(abs($stockAfterShipping - $this->minStock) / $this->perLot);
                    $prod = $this->perLot * $lotProd;
                }

                if($prod > 0){
                    if($arrayPlans[$date]['l_s'] < $this->minStock){
                        if($this->setMaxProduction < $this->totalShipping){
                            $prod = floor($this->maxProduction/$this->perLot) * $this->perLot;
                        }else{
                            $prod = floor($this->totalShipping/$this->perLot) * $this->perLot;
                        }
                    }else{
                        $prod = $prod;
                    }
                }
            }

            if(in_array($date, array_column($this->arrSales, 'date'))){
                // Kalkulasi last stock terupdate
                $this->lastStock = $this->lastStock + $prod - $arrayPlans[$date]['shipping'];
                
                // Update value prod, after_stock dan total_prod_for_shipping berdasarkan tanggal
                $arrayPlans[$date]['prod'] = $prod;
                $arrayPlans[$date]['after_stock'] = $this->lastStock;
                $arrayPlans[$date]['total_prod_for_shipping'] = $totalShipping;
            }            
        }

        // var_dump($arrayPlans);

        return $arrayPlans;
    }

    public function setDayOff($value){
        $this->arrDayOff = $value;
    }

    public function setSales($value){
        $this->arrSales = $value;
    }

    public function setMinStock($value){
        $this->minStock = $value;
    }

    public function setLeadTime($value){
        $this->leadTime = $value;
    }

    public function setPerLot($value){
        $this->perLot = $value;
    }

    public function setLastStock($value){
        $this->lastStock = $value;
    }

    public function setStartDate($value)
    {
        $this->date = $value;
        $this->startDate = $value;
    }

    public function setCalculationFromLastMonth($value)
    {
        $this->calculationFromLastMonth = $value;
    }

    public function setWipStock($value)
    {
        $this->wipStock = $value;
    }

    public function setReturnStock($value)
    {
        $this->returnStock = $value;
    }

    public function setLockedProduction($value)
    {
        $this->lockedProduction = $value;
    }

    public function setMaxProduction($value)
    {
        $this->maxProduction = $value;
    }

    public function setTotalShipping($value)
    {
        $this->totalShipping = $value;
    }
}
     