<?php

include_once('../ProductionPlanCalculation.php');
// Starting clock time in seconds
$start_time = microtime(true);

$arrDayOff = ['2022-11-26','2022-11-27','2022-12-03','2022-12-04','2022-12-10','2022-12-11','2022-12-17','2022-12-18','2022-12-24','2022-12-25','2022-12-31'];
$arrSales = [
    [
        'date'  => '2022-12-02',
        'shipping_qty'   => 24,
    ],
    [
        'date'  => '2022-12-06',
        'shipping_qty'   => 60,
    ],
    [
        'date'  => '2022-12-07',
        'shipping_qty'   => 10,
    ],
    [
        'date'  => '2022-12-08',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-09',
        'shipping_qty'   => 20,
    ],
    [
        'date'  => '2022-12-10',
        'shipping_qty'   => 20,
    ],
    [
        'date'  => '2022-12-11',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-12',
        'shipping_qty'   => 24,
    ],
    [
        'date'  => '2022-12-13',
        'shipping_qty'   => 36,
    ],
    [
        'date'  => '2022-12-14',
        'shipping_qty'   => 50,
    ],
    [
        'date'  => '2022-12-15',
        'shipping_qty'   => 100,
    ],
    [
        'date'  => '2022-12-16',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-17',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-18',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-21',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-22',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-23',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-26',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-27',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-28',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-29',
        'shipping_qty'   => 12,
    ],
    [
        'date'  => '2022-12-30',
        'shipping_qty'   => 12,
    ]
];

$ppc = new ProductionPlanCalculation( 24, 5, 12, 12);
$ppc->setDayOff($arrDayOff);
$ppc->setSales($arrSales);
// End clock time in seconds
$end_time = microtime(true);

echo "Execution time of script = ".(($end_time - $start_time)*1000)." Milisecon\n";

echo "<pre>";
print_r($ppc->calculate());
echo "</pre>";