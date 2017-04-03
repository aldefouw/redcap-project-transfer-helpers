<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/redcap_connect.php';

if (SUPER_USER){
    //Allow access
} else {
    //If user is not a super user OR account manager, go back to Home page
    redirect(APP_PATH_WEBROOT);
}

require_once '../../lib/deleteUntouchedRecordsClass.php';

$delete_events = array('Event 1' => 1,
                       'Event 2' => 2,
                       'Event 3' => 3);

$delete_object = array('connection' => $conn,
    'event_id' => $delete_events,
    'project_id' => 1,
    'debug_mode' => true,
    'table_path' => "imports/table.html");

$delete = new deleteUntouchedRecordsClass($delete_object);

$delete->deleteSelectedResponses();