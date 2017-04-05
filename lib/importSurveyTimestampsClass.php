<?php
class importSurveyTimestampsClass {

    private $connection;
    private $project_id;
    private $event_id;
    private $csv_path;
    private $debug_mode;

    private $form_array;
    private $csv_array;
    private $timestamps_array;

    function __construct($imports) {
        $this->setDebugMode($imports['debug_mode']);

        $required_keys = array('connection', 'event_id', 'project_id', 'csv_path');
        $this->setVars($required_keys, $imports);

        $this->setCSVArray();
        $this->setFormArray();
        $this->setTimestampsArray();
    }

    function getConnection(){
        return $this->connection;
    }

    function getProjectID(){
        return $this->project_id;
    }

    function getEventID(){
        return $this->event_id;
    }

    function getCSVPath(){
        return $this->csv_path;
    }

    function getCSVArray(){
        return $this->csv_array;
    }

    function getFormArray(){
        return $this->form_array;
    }

    function getTimestampsArray(){
        return $this->timestamps_array;
    }

    function setDebugMode($debug_mode){
        $this->debug_mode = $debug_mode;
    }

    function getDebugMode(){
        return $this->debug_mode;
    }

    function setVars($keys, $imports){
        foreach($keys as $k){
            if (array_key_exists($k, $imports)) {
                eval('$this->'.$k.' = $imports[\''.$k.'\'];');
            } else {
                throw new Exception("Your import object needs a key for '$k'.");
            }
        }
    }

    function setCSVArray(){
        $csv_array = array_map('str_getcsv', file($this->getCSVPath()));
        $this->csv_array = $csv_array;
    }

    function setFormArray(){
        $csv = $this->getCSVArray();

        $sql = "SELECT * FROM redcap_surveys WHERE project_id = %d";
        $query = sprintf($sql, $this->getProjectID());
        $survey_results = db_query($query);
        $row = db_fetch_assoc($survey_results);

        $header_row = $csv[0];

        $survey_forms = array();

        do {
            foreach($header_row as $k => $v){
                if( substr_count($v, '_timestamp') ){
                    $form_name = str_replace('_timestamp', '', $v);
                    if($form_name == $row['form_name']) {
                        $survey_forms[$k] = array( 'form_name' => $form_name, 'form_id' =>  $row['survey_id']);
                        break;
                    }
                }
            }
        } while($row = db_fetch_assoc($survey_results));

        return $this->form_array = $survey_forms;
    }

    function setTimestampsArray(){
        $form_array = $this->getFormArray();
        $csv = $this->getCSVArray();
        $first_line = true;
        $timestamps_array = array();

        foreach($csv as $k => $v){
            if($first_line) {
                $first_line = false; #Skip the first line
            } else {
                $record_id = $v[0];
                unset($v[0]);

                foreach($v as $key => $value){
                    $form_id = $form_array[$key]['form_id'];
                    if(strlen($value)){ $timestamps_array[$record_id][$form_id] = $value; }
                }
            }
        }

        $this->timestamps_array = $timestamps_array;
    }

    function performSurveyTimestampQueries(){
        $timestamps = $this->getTimestampsArray();

        foreach($timestamps as $key => $values){
            $request_id = $key;
            foreach($values as $k => $v){
                $survey_id = $k;
                list($first_submit_time, $completion_time) = $this->adjustedTimestamps($v);
                $this->insertParticipantResponse($request_id, $survey_id, $first_submit_time, $completion_time);
            }
        }
    }

    function adjustedTimestamps($initial_timestamp){
        if($initial_timestamp == '[not completed]'){
            $first_submit_timestamp = date('Y-m-d H:i:s');
            $completion_timestamp = NULL;
        } else {

            if($initial_timestamp != ''){
                $format_date = DateTime::createFromFormat('m/d/y H:i', $initial_timestamp);

                if($format_date){
                    $first_submit_timestamp = date_format($format_date, 'Y-m-d H:i:s');
                } else {
                    throw new Exception("Invalid date format for timestamp $initial_timestamp. Needs to be formatted as follows: 'm/d/yy h:mm.");
                }
            }

            $completion_timestamp = $first_submit_timestamp;
        }
        return array($first_submit_timestamp, $completion_timestamp);
    }

    function insertParticipantResponse($request_id, $survey_id, $first_submit_time, $completion_time){
        $errors = 0;

        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        $sql = "INSERT INTO redcap_surveys_participants (survey_id, event_id, participant_email) VALUES (%d, %d, %s)";
        $query = sprintf($sql, $survey_id, $this->getEventID(), '');
        $participants_inserted = db_query($query);

        if (!$participants_inserted) $errors++;
        $last_id = mysqli_insert_id($this->getConnection());
        $this->debugOutput($query, $participants_inserted, 'PARTICIPANT ID '.$last_id.' INSERTED');

        if($completion_time == NULL) {
            $sql = "INSERT INTO redcap_surveys_response (participant_id, record, instance, first_submit_time) VALUES (%d, %d, %d, '%s')";
            $query = sprintf($sql, $last_id, $request_id, 1, $first_submit_time);
        } else {
            $sql = "INSERT INTO redcap_surveys_response (participant_id, record, instance, first_submit_time, completion_time) VALUES (%d, %d, %d, '%s', '%s')";
            $query = sprintf($sql, $last_id, $request_id, 1, $first_submit_time, $completion_time);
        }

        $response = db_query($query);

        if (!$response) $errors++;
        $this->debugOutput($query, $response, 'RESPONSE INSERTED FOR '.$last_id.'. <br /> ================================');

        if ($errors > 0) {
            db_query("ROLLBACK"); // Errors occurred, so undo any changes made
            $this->debugOutput("ERROR ENCOUNTERED.  QUERY ROLLBACK PERFORMED.", NULL, NULL);
            exit(); // Return '0' for error
        } else {
                db_query("COMMIT"); // Commit changes
                db_query("SET AUTOCOMMIT=1");
        }
    }

    function debugOutput($query, $query_return, $text){
        if($this->getDebugMode()){
            if($query_return){
                echo '<strong style="color: green;">QUERY SUCCESS:</strong> '.$query.'<br />'.$text.'<br />';
            } else{
                echo '<strong style="color: red;">QUERY ERROR:</strong> '.$query.'<br />'.$text.'<br />';
            }
        }
    }

}