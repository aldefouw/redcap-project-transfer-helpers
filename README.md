# redcap-project-transfer-helpers

This project contains PHP libraries that will aid in the transfer of a REDCap project from one server to another.


## Project Transfer Problems Targeted:

1) **Survey timestamps are not maintained when importing data that has been exported**

    There is no **built-in method** for REDCap to import survey timestamps.  
    
    The **importSurveyTimestampsClass** aims to fill that gap.
    

2) **Records that have never been touched are set to "inactive" rather than "null" completion status**

      All records receive a red (inactive) bubble - but not all bubbles should. 
      
      The **deleteUntouchedRecordsClass** aims to correct this.


## Disclaimer

**Use the scripts at your own risk.**  

**No warranty against the loss of data as a result of running a script against the provided libraries.**


# Getting Started

To perform the operations provided by the library, you need server-level access to the server you are transfering the REDCap project to.  

Your script(s) should be run (**once!** and only once) through your web browser. 

The scripts work by directly connecting to the REDCap database tables and performing raw queries.

The scripts are written within transactions.  Queries should rollback if an error happens along the way.  

**That said, it is recommended that these scripts are run on a development server before running in production.**


## Connecting to REDCap

To connect to the REDCap database, include **redcap_connect.php** in your script like so:

    require_once 'path/to/redcap_connect.php';
    
## Restricting Access

To restrict the access to super users only, you can do something like this:

    if (SUPER_USER){
      //Allow access
    } else {
        redirect(APP_PATH_WEBROOT); //If user is not a super user OR account manager, go back to Home page
    }    
    
    

# Delete Inactive Response Bubbles

 You will need the following to delete the unwanted inactive response bubbles:

* Event ID (for new project)
* Project ID (for new project)
* **HTML file** containing the **Record Status Dashboard table** from **SOURCE REDCap server**  
(see sample: https://github.com/aldefouw/redcap-project-transfer-helpers/blob/master/projects/sample_project/imports/table.html) 


In a PHP file, do the following:

Include the **deleteUntouchedRecordsClass** file.

    require_once 'path/to/lib/deleteUntouchedRecordsClass.php';
        
Create an instance of **deleteUntouchedRecordsClass**, including an array variable that contains the following keys:
    
    $delete = array('connection' => $conn,
                    'event_id' => 1,
                    'project_id' => 1,
                    'debug_mode' => true,
                    'table_path' => "imports/table.html");
                    
    $delete = new importSurveyTimestampsClass($import);

If you are missing a key in the array, an Exception will be thrown.  

Your **debug_mode** key can be set to **false** if you'd prefer not to receive feedback when you run the script.

    
To delete the unwanted bubbles, call **deleteSelectedResponses**:    
       
    $delete->deleteSelectedResponses();
    
    
 
# Import Survey Timestamps

You will need the following to import survey timestamps:

* Event ID (for new project)
* Project ID (for new project)
* CSV File containing the timestamps for each instrument

(see sample: https://github.com/aldefouw/redcap-project-transfer-helpers/blob/master/projects/sample_project/imports/requests.csv)

In a PHP file, do the following:


Include the **importSurveyTimestampsClass** file.

    require_once 'path/to/lib/importSurveyTimestampsClass.php';
    
    
Create an instance of **importSurveyTimestampsClass**, including an array variable that contains the following keys:
    
    $import = array('connection' => $conn,
                    'event_id' => 1,
                    'project_id' => 1,
                    'debug_mode' => true,
                    'csv_path' => "imports/requests.csv");
                    
    $import = new importSurveyTimestampsClass($import);

If you are missing a key in the array, an Exception will be thrown.  

Your **debug_mode** key can be set to **false** if you'd prefer not to receive feedback when you run the script.

    
To add the timestamps to your project, call **performSurveyTimestampQueries**:    
       
    $import->performSurveyTimestampQueries();
    
   
   

If you have questions, comments, or suggested improvements, please feel free to open an Issue on this repository.  I hope someone finds this helpful.
