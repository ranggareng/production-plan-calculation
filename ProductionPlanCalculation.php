<?php

Class ProductionPlanCalculation{

    private $minStock;
    private $leadTime;
    private $perLot;
    private $lastStock;
    private $arrDayOff = [];
    private $arrSales = [];
    private $date;

    public function __construct($minStock, $leadTime, $perLot, $lastStock){
        $this->minStock = $minStock;
        $this->leadTime = $leadTime;
        $this->perLot = $perLot;
        $this->lastStock = $lastStock;
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
        $arrayPlans = [];
        $endDate = $this->getLastLoopingDate();
        
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
            while($countOfWorkingDay < $this->leadTime){
                /*
                * Setup array kosong untuk tanggal yang berkaitan
                * yang sedang berjalan
                */
                if(!isset($arrayPlans[$dateMinOneDay])){
                    $arrayPlans[$dateMinOneDay] = [
                        'date' => date('d/m', strtotime($this->date)),
                        'shipping' => 0,
                        'prod_for_shipping_date' => [],
                        'prod' => 0,
                        'l_s' => 0,
                        'after_stock' => 0,
                        'total_prod_for_shipping' => 0,
                        'working_day_until_shipping_date' => []
                    ];
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
                
                $dateMinOneDay = date('Y-m-d', strtotime('-1 day', strtotime($dateMinOneDay)));
                if(!in_array($dateMinOneDay, $this->arrDayOff)){
                    $countOfWorkingDay++;   
                }
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
                $arrayPlans[$prodDate] = [
                    'date' => date('d/m', strtotime($prodDate)),
                    'shipping' => 0,
                    'prod_for_shipping_date' => [],
                    'prod' => 0,
                    'l_s' => 0,
                    'after_stock' => 0,
                    'total_prod_for_shipping' => 0,
                    'working_day_until_shipping_date' => $arrWorkingDaysUntilHMinLateTime
                ];    
            }else{
                $arrayPlans[$prodDate]['working_day_until_shipping_date'] = $arrWorkingDaysUntilHMinLateTime;
            }
            
            /*
             * Lakukan pengecekan apakah shipping date saat ini
             * ada di data sales/shipping
             *
             */
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
            
            $this->date = date('Y-m-d', strtotime("+1 day", strtotime($this->date)));
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
            
            // Kalkulasi sisa stock setelah dikurangi total shipping
            $stockAfterShipping = $this->lastStock - array_sum(array_column($arrayPlans[$date]['working_day_until_shipping_date'], 'shipping_qty')) - $arrayPlans[$date]['shipping'];
            
            if($stockAfterShipping - $this->minStock < 0){
                $lotProd = ceil(abs($stockAfterShipping - $this->minStock) / $this->perLot);
                $prod = $this->perLot * $lotProd;
            }
            
            // Kalkulasi last stock terupdate
            $this->lastStock = $this->lastStock + $prod - $arrayPlans[$date]['shipping'];
            
            // Update value prod, after_stock dan total_prod_for_shipping berdasarkan tanggal
            $arrayPlans[$date]['prod'] = $prod;
            $arrayPlans[$date]['after_stock'] = $this->lastStock;
            $arrayPlans[$date]['total_prod_for_shipping'] = $totalShipping;
            
        }

        return $arrayPlans;
    }

    public function setDayOff($value){
        $this->arrDayOff = $value;
    }

    public function setSales($value){
        $this->arrSales = $value;
    }
}
        