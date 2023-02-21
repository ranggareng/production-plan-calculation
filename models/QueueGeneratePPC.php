<?php 
require_once("./helpers/Database.php");
use Simplon\Mysql\Crud\CrudModel;

class QueueGeneratePPC extends CrudModel
{
    public static function getQueue($type){
        $DB = \Database::connect();

        if($type == 'sales'){
            $result = $DB->fetchRowMany("SELECT * FROM queue_generate_ppc_head LEFT JOIN queue_generate_ppc_line ON queue_generate_ppc_line_head_id = queue_generate_ppc_id LEFT JOIN m_item ON m_item_number = queue_generate_ppc_line_item WHERE queue_generate_ppc_executed_at IS NULL AND queue_generate_ppc_line_success IS NULL AND queue_generate_ppc_line_sales IS NULL AND queue_generate_ppc_active = 1");
        }else{
            $result = $DB->fetchRowMany("SELECT * FROM queue_generate_ppc_head LEFT JOIN queue_generate_ppc_line ON queue_generate_ppc_line_head_id = queue_generate_ppc_id LEFT JOIN m_item ON m_item_number = queue_generate_ppc_line_item WHERE queue_generate_ppc_executed_at IS NULL AND queue_generate_ppc_line_success IS NULL AND queue_generate_ppc_line_forecast IS NULL AND queue_generate_ppc_active = 1");
        }        

        if(!$result){
            $DB->close();
            return false;
        }
        
        $DB->close();
        return $result;
    }

    public static function updateLineStatus($itemNumber, $type, $success = null){
        $DB = \Database::connect();
        $data = [];
        if($type == 'sales')
            $data = array_merge($data, ['queue_generate_ppc_line_sales' => true]);
        else
            $data = array_merge($data, ['queue_generate_ppc_line_forecast' => true]);

        if(!is_null($success))
            $data = array_merge($data, ['queue_generate_ppc_line_success' => $success]);

        $DB->update('queue_generate_ppc_line', ['queue_generate_ppc_line_item' => $itemNumber], $data);
        $DB->close();
    }

    public static function updateHeadStatus(){
        $DB = \Database::connect();
        $DB->update('queue_generate_ppc_head', ['queue_generate_ppc_active' => true, 'queue_generate_ppc_executed_at' => NULL], ['queue_generate_ppc_executed_at' => date('Y-m-d H:i:s'), 'queue_generate_ppc_active' => false], 'queue_generate_ppc_executed_at IS :queue_generate_ppc_executed_at and queue_generate_ppc_active=:queue_generate_ppc_active');
        $DB->close();
    }
}