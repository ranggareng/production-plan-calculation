<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class WorkDay extends CrudModel
{
    public static function getTotalWorkingDayPerMonth(){
        $DB = \Database::connect();

        $year = date('Y');
        $start = $year.'-01-01';

        $arrTotalWorkingDay = [];

        for($i=0; $i<36; $i++){
            $end = date('Y-m-t', strtotime($start));
            $totalDay = date('t', strtotime($start));

            $result = $DB->fetchRow("SELECT count('m_work_day_date') as total_off_day FROM m_work_day WHERE m_work_day_stat=0 AND m_work_day_date>=:start AND m_work_day_date<=:end", ['start' => $start, 'end' => $end]);

            if($result){
                $totalOffDay = $result['total_off_day'];
                $arrTotalWorkingDay[date('Y', strtotime($start)).'-'.date('m', strtotime($start))] = $totalDay - $totalOffDay;
            }else{
                $arrTotalWorkingDay[date('Y', strtotime($start)).'-'.date('m', strtotime($start))] = $totalDay;
            }

            $start = date('Y-m-d', strtotime('+1 month', strtotime($start)));
        }

        return $arrTotalWorkingDay;
    }

    public static function getOffDay()
    {
        $DB = \Database::connect();

        $result = $DB->fetchRowMany("SELECT m_work_day_date FROM m_work_day WHERE m_work_day_stat=0");

        if($result){
            $DB->close();
            return $result;
        }else{
            $DB->close();
            return false;
        }
    }
}