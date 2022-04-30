<?php
   // DB CONNECTION
    $servername = "localhost";
    $username = "websiteb_db";
    $password = "9;5b02[Bl(w!";
    $dbname = "websiteb_db";
    
    // Create connection
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        die('Could not connect: ' . mysql_error());
    }
    
    function returnResponse($data, $success = true) {
        ob_clean();
        header_remove();
        header("Content-type: application/json; charset=utf-8");
        
        if ($success) {
            http_response_code(200);
        } else {
            http_response_code(500);
        }
        
        echo json_encode($data);
        exit();
    }
    
    if( isset($_POST["get_state"]) ){
        $country_id = $_POST["country_id"];
        
        $states_sql = "SELECT * FROM states WHERE country_id = '$country_id' ORDER BY name ASC";
        $states = $conn->query($states_sql);
        
        $states_result = array();
        foreach( $states as $state ){
            $states_result[] = [
                "id" => $state["id"],
                "text" => $state["name"]
            ];
        }
        $conn->close();
        
        $data = [
            'success' => true,
            'message' => "has data",
            'data'    => $states_result
        ];
        
        returnResponse($data);
    }
    
?>