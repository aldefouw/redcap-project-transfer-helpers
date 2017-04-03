<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/redcap_connect.php';

if (SUPER_USER){
    //Allow access
} else {
    //If user is not a super user OR account manager, go back to Home page
    redirect(APP_PATH_WEBROOT);
}

require_once '../../lib/importSurveyTimestampsClass.php';

$import_object = array('connection' => $conn,
                  'event_id' => 1,
                  'project_id' => 1,
                  'debug_mode' => true,
                  'csv_path' => "imports/requests.csv");

$import = new importSurveyTimestampsClass($import_object);

$import->performSurveyTimestampQueries();