<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/redcap_connect.php';

if (SUPER_USER){
    //Allow access
} else {
    //If user is not a super user OR account manager, go back to Home page
    redirect(APP_PATH_WEBROOT);
}

require_once '../../lib/deleteUntouchedRecordsClass.php';

$delete_object = ['connection' => $conn,
                  'event_id' => 1,
                  'project_id' => 1,
                  'debug_mode' => true,
                  'table_path' => "imports/table.html"];

$delete = new deleteUntouchedRecordsClass($delete_object);

$delete->deleteSelectedResponses();