<?php

Class ProductionPlanCalculation{

    private $minStock;
    private $leadTime;
    private $perLot;
    private $lastStock;
    private $arrDayOff = [];
    private $arrSales = [];
    private $today;

    public function __construct($minStock, $leadTime, $perLot, $lastStock){
        $this->minStock = $minStock;
        $this->leadTime = $leadTime;
        $this->perLot = $perLot;
        $this->lastStock = $lastStock;
        $this->today = date();
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
        sort($arrDateFromSales, 'DESC'); // sort array hasil extraxt secara desc
        
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
    public function getWorkDay($loopingDay, $dayOff){
        $workDays = array_diff($loopingDay, $dayOff);
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
    function getLoopingDay($startDate, $endDate){
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

        $shippingDate = date(('Y-m').'-01');
        
        
        /*
         * Untuk memudahkan dalam perhitungan PPC
         * kita set dulu production date untuk setiap
         * shipping date.
         *
         * Hal ini dikarenakan ada kemungkinan dalam 1 hari
         * harus memproduksi barang untuk beberapa shipping date
         *
         */
        while(strtotime($shippingDate) <= strtotime(date('Y-m') . '-' . date('t', strtotime($shippingDate)))){
         
            /*
             * Setup array kosong untuk shipping date 
             * yang sedang berjalan
             */
            $arrayPlans[$shippingDate] = [
                'date' => date('d/m', strtotime($shippingDate)),
                'shipping' => 0,
                'prod_for_shipping_date' => [],
                'prod' => 0,
                'l_s' => 0,
                'after_stock' => 0,
                'total_prod_for_shipping' => 0,
            ]; 
        
            /*
             * Get production Date dengan melkalkulasikan
             * Shipping date dikurangi Lead Time
             *
             * Untuk sementara asumsikan H min LeadTime
             * adalah actual production datenya
             *
             */
            $actualProdDate = $prodDate = date('Y-m-d', strtotime('-'.$this->leadTime.' day', strtotime($shippingDate))); 
            
            /*
             * Cek apakah production date adalah hari libur,
             * Jika production date adalah hari libur,
             * maka mundurkan hari (H-1) hingga mendapatkan
             * hari kerja terakhir sebelum prod date
             */
            while(in_array($actualProdDate, $this->arrDayOff)){
                $actualProdDate = date('Y-m-d', strtotime('-1 day', strtotime($actualProdDate)));
            }

            /*
             * Setup array kosong jika data berdasarkan 
             * actual production date belum terbentuk
             *
             */
            if(!isset($arrayPlans[$actualProdDate])){
                $arrayPlans[$actualProdDate] = [
                    'date' => date('d/m', strtotime($actualProdDate)),
                    'shipping' => 0,
                    'prod_for_shipping_date' => [],
                    'prod' => 0,
                    'l_s' => 0,
                    'after_stock' => 0,
                    'total_prod_for_shipping' => 0,
                ];    
            }
            
            /*
             * Lakukan pengecekan apakah shipping date saat ini
             * ada di data sales/shipping
             *
             */
            if(in_array($shippingDate, array_column($this->arrSales, 'date'))){
                
                // Get index data dari array shipping
                $arrIndexShipping  = array_search($shippingDate, array_column($this->arrSales, 'date'));
                
                // Get shipping qty dari array shipping
                $shipping = $this->arrSales[$arrIndexShipping]['shipping_qty'];
                
                // Update informasi shipping quantity
                $arrayPlans[$shippingDate]['shipping'] = $shipping;
                
                // Masukkan informasi shipping pada data sesuai actual production date
                array_push(
                    $arrayPlans[$actualProdDate]['prod_for_shipping_date'], 
                    [
                        "shipping_date" => $shippingDate,
                        "shipping_qty" => $shipping
                    ]
                );
    
            }
            
            $shippingDate = date('Y-m-d', strtotime("+1 day", strtotime($shippingDate)));
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
        /* foreach($arrayPlans as $date => $data){
            // reset $prod setiap ganti tanggal
            $prod = 0;
            
            // Update value l_s berdasarkan tanggal
            $arrayPlans[$date]['l_s'] = $this->lastStock;
            
            // Total shipping berdasarkan tanggal
            $totalShipping = array_sum(array_column($arrayPlans[$date]['prod_for_shipping_date'], 'shipping_qty'));
            
            // Kalkulasi sisa stock setelah dikurangi total shipping
            $stockAfterShipping = $this->lastStock - $totalShipping;
            
            if($stockAfterShipping - $this->minStock < 0){
                $lotProd = ceil(abs($stockAfterShipping - $this->minStock) / $this->perLot);
                $prod = $this->perLot * $lotProd;
            }
            
            // Kalkulasi last stock terupdate
            $this->lastStock = $stockAfterShipping + $prod;
            
            // Update value prod, after_stock dan total_prod_for_shipping berdasarkan tanggal
            $arrayPlans[$date]['prod'] = $prod;
            $arrayPlans[$date]['after_stock'] = $this->lastStock;
            $arrayPlans[$date]['total_prod_for_shipping'] = $totalShipping;
        }*/

        return $arrayPlans;
    }

    public function setDayOff($value){
        $this->arrDayOff = $value;
    }

    public function setSales($value){
        $this->arrSales = $value;
    }
}
        