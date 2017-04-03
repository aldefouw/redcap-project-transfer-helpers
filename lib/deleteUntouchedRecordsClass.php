<?php
class deleteUntouchedRecordsClass {

    private $connection;
    private $project_id;
    private $event_id;
    private $debug_mode;

    private $doc;
    private $result_array;


    function __construct($object) {
        $this->setDebugMode($object['debug_mode']);
        $required_keys = array('connection', 'event_id', 'project_id', 'table_path');
        $this->setVars($required_keys, $object);

        $this->setHTML($object['table_path']);
        $this->setResultArray();
    }

    function setHTML($table_path){
        $this->doc = new DOMDocument();
        $this->doc->loadHTML(file_get_contents($table_path));
    }

    function setVars($keys, $object){
        foreach($keys as $k){
            if (array_key_exists($k, $object)) {
                eval('$this->'.$k.' = $object[\''.$k.'\'];');
            } else {
                throw new Exception("Your import object needs a key for '$k'.");
            }
        }
    }

    function setResultArray(){
        $trs = $this->doc->getElementsByTagName('tr');

        $this->result_array = array();

        foreach ($trs as $key => $tr){

            $first_column = true;

            foreach($tr->getElementsByTa8gName('td') as $tdk => $td){
                if($first_column) {
                    $first_column = false; #Skip the first column
                } else {

                    foreach ($td->getElementsByTagName('a') as $k => $a) {
                        $this->setHref($a->getAttribute('href'), $key, $tdk);
                    }

                    foreach ($td->getElementsByTagName('img') as $k => $i) {
                        $this->setSrc($i->getAttribute('src'), $key, $tdk);
                    }
                }
            }
        }
    }

    function setHref($value, $key, $tdk){
        $parts = explode('?', $value);
        parse_str($parts[1], $output);

        foreach($output as $k => $v){
            $this->result_array[$key][$tdk][$k] = $v;
        }
    }

    function setSrc($value, $key, $tdk){
        $parts = explode('/', $value);
        $circle = end($parts);

        if($circle == "circle_gray.png"){
            $this->result_array[$key][$tdk]['delete'] = true;
        } else {
            $this->result_array[$key][$tdk]['delete'] = false;
        }
    }

    function getDeleteFields($e){
        // Get list of all fields with data for this record on this form
        $sql = "SELECT DISTINCT redcap_data.field_name
                FROM redcap_data 
                LEFT JOIN redcap_metadata 
                ON redcap_data.project_id = redcap_metadata.project_id AND 
                  redcap_data.field_name = redcap_metadata.field_name                
                WHERE redcap_metadata.form_name = '%s'AND 
                  redcap_data.project_id = %d AND 
                  event_id = %d AND 
                  record = '%s'";

        $query = sprintf($sql, prep($e['page']), $this->getProjectID(), $this->getEventID(), prep($e['id']));
        $q = db_query($query);

        $eraseFields = array();
        while ($row = db_fetch_assoc($q)) {
            $eraseFields[] = $row['field_name'];
        }

        return $eraseFields;
    }

    function deleteSelectedResponses(){
        $errors = 0;

        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        foreach($this->result_array as $entry){
           foreach($entry as $e){

               if($e['delete']) {
                   $eraseFields = $this->getDeleteFields($e);

                   if($eraseFields){
                       $sql = "DELETE FROM redcap_data WHERE project_id = %d AND event_id = %d AND record = '%s' AND field_name IN (%s)";
                       $query = sprintf($sql, $this->getProjectID(), $this->getEventID(), $e['id'], prep_implode($eraseFields));
                       $response = db_query($query);

                        if (!$response) $errors++;
                       $this->debugOutput($query, $response, 'DELETED '.prep_implode($eraseFields).' FROM record '.$e['id'].'. <br /> ================================');
                   }
               }
           }
       }

        if ($errors > 0) {
            db_query("ROLLBACK"); // Errors occurred, so undo any changes made
            $this->debugOutput("ERROR ENCOUNTERED.  QUERY ROLLBACK PERFORMED.", NULL, NULL);
            exit(); // Return '0' for error
        } else {
            db_query("COMMIT"); // Commit changes
            db_query("SET AUTOCOMMIT=1");
        }

    }

    function getResultsArray(){
        return $this->result_array;
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

    function setDebugMode($debug_mode){
        $this->debug_mode = $debug_mode;
    }

    function getDebugMode(){
        return $this->debug_mode;
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
