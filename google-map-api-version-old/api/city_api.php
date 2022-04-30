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
    
    if( isset($_POST["get_city"]) ){
        $state_id = $_POST["state_id"];
        
        $cities_sql = "SELECT * FROM cities WHERE state_id = '$state_id'";
        $cities = $conn->query($cities_sql);
        
        $cities_result = array();
        foreach( $cities as $city ){
            $cities_result[] = [
                "id" => $city["id"],
                "text" => $city["name"]
            ];
        }
        $conn->close();
        
        $data = [
            'success' => true,
            'message' => "has data",
            'data'    => $cities_result
        ];
        
        returnResponse($data);
    }
    
?>