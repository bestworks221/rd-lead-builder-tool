<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    session_start();

    require __DIR__ . '/vendor/autoload.php';
    
    $has_data = false;
    $local_results = array();
    $q = "";
    
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
    
    // get list of countries
    $countries_sql = "SELECT * FROM countries";
    $countries = $conn->query($countries_sql);
    
    // get credits
    $user_credits_sql = "SELECT * FROM credits where user_id = 1";
    $user_credits = $conn->query($user_credits_sql)->fetch_array();
    
    // compute the remaining credit
    $total_credit       = $user_credits["total_credit"];
    $total_api_call     = $user_credits["total_api_call"];
    $credit_remaining   = $user_credits["total_credit"] - $user_credits["total_api_call"];
    
    $temp_country = array();
    $default_country = ["US", "GB", "AU", "CA", "FR", "DE"];
    foreach( $countries as $key => $country ) {
        $temp_country[] = $country;
        if( in_array($country["iso2"], $default_country) ){
            array_unshift($temp_country, $country);
        }
    }
    
    $s_country = "";
    $s_state = "";
    $s_city = "";
    $t_country = "";
    $t_state = "";
    $t_city = "";
    $temp_result = array();
    
    if ( isset($_POST["q"]) ) {
        if( $total_api_call < $total_credit ){
            $q = $_POST["q"];
            $s_country = isset($_POST["country"]) ? $_POST["country"] : "" ;
            $t_country = $conn->query("SELECT name FROM countries WHERE iso2 = '$s_country'")->fetch_array();
            
            $s_state = isset($_POST["state"]) ? $_POST["state"] : "";
            $t_state = $conn->query("SELECT name FROM states WHERE id = '$s_state'")->fetch_array();
            
            $s_city = isset($_POST["city"]) ? $_POST["city"] : "";
            $t_city = $conn->query("SELECT name FROM cities WHERE id = '$s_city'")->fetch_array();
            
            $city_sql = "SELECT * FROM cities WHERE id = '$s_city'";
            $city = $conn->query($city_sql)->fetch_array();
            
            $latitude = $city["latitude"];
            $longitude = $city["longitude"];
            $ll = $latitude.",".$longitude;
            
            # REQUEST FOR THE PLACE DETAILS
            $apiKey = "AIzaSyCEY76t2odPx_Ze1ea74UrA5gmGWlfvLMc";
            // $tempLat = "-33.8670522";
            // $tempLng = "151.1957362";
            // $loc = $tempLat.",".$tempLng;
            $radius = "1500";
            $type = "restaurant";
            // $keyword = "cruise";
            
            $firstData = getPlaceID( $ll, $radius, $type, $q, $apiKey );
            
            $decFirstData = json_decode( $firstData );
            
            if( count($decFirstData) > 0 ){
                foreach( $decFirstData as $item ) {
                    $placeDetail = getPlaceDetails( $item->place_id, $apiKey );
                    
                    if( $placeDetail->status == "OK" ){
                        $result = $placeDetail->result;
                        $temp_result[] = [
                            "title"             => $result->name,
                            "address"           => isset( $result->formatted_address ) ? $result->formatted_address : "",
                            "phone"             => isset( $result->formatted_phone_number ) ? $result->formatted_phone_number : "",
                            "unclaimed_listing" => "Unavailable",
                            "website"           => isset( $result->website ) ? $result->website : ""
                        ];
                    }
                }
                
                // 80d47d076e861aebb8f8730dae483afb070dc6b88fe34f04f4a8588f483f8609
                // $client = new GoogleSearch("51b78cd0e9d83c5aa8bf927f526af7203a46e10b25a456065c6f3493604d14bc");
                
                // $start = 0;
                // for ($i = 0; $i < 5; $i++) {
                //     $query = [
                //         "engine" => "google_maps",
                //         "no_cache" => "true",
                //         "q" => $q,
                //         "google_domain" => "google.com",
                //         "ll" => $ll,
                //         "type" => "search",
                //         "hl" => "en",
                //         "start" => $start
                //     ];
                //     $response = $client->get_json($query);
                
                //     $local_results = $response->local_results;
                //     foreach( $local_results as $local_result ) {
                //         $temp_result[] = $local_result;
                //     }
                    
                //     $start = $start+10;
                // }
            
                $has_data = true;
                
                # Update the credit table
                $new_total_api_call = $total_api_call + 1;
                $new_credit_sql = "UPDATE credits SET 
                                    total_api_call = $new_total_api_call
                                WHERE user_id = 1";
                $new_credit_result = $conn->query($new_credit_sql);
                
                if( $new_credit_result ) {
                    $credit_remaining       = $credit_remaining - 1;
                    $total_api_call     = $total_api_call + 1;
                }
            }else{
                $has_data = false;
            }
        }else{
            $_SESSION["warning"] = "Oops you already reach your credits! please buy for more credits.";
        }
    }
    $conn->close();
    
    function getPlaceID( $loc, $r, $type, $keyword, $key ) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location='.$loc.'&radius='.$r.'&type='.$type.'&keyword='.$keyword.'&key='.$key,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        $output = json_decode($response);
        $data = array();
        foreach( $output->results as $item ){
            $data[] = [
                "name"  => $item->name,
                "business_status" => $item->business_status,
                "lat" => $item->geometry->location->lat,
                "lng" => $item->geometry->location->lng,
                "place_id" => $item->place_id
            ];
        }
        
        return json_encode($data);
    }
    
    function getPlaceDetails( $place_id, $key ) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://maps.googleapis.com/maps/api/place/details/json?key='.$key.'&place_id='.$place_id,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        
        $response = curl_exec($curl);
        
        return json_decode($response);
    }
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <title>Google Map Tools</title>
    <style>
        .center{
            justify-content: center !important;
        }

        .form-label {
            width: 100%;
        }
        
        .select2-selection {
            display: block !important;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff !important;
            background-clip: padding-box;
            border: 1px solid #ced4da !important;
            border-radius: 0.25rem !important;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
            height: 38px !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }
        
        .credits-container {
            padding-bottom: 1rem;
        }
        
        .credits-container p {
            border: 2px solid #6c757d;
            display: inline-block;
            padding: 0.3rem;
            background-color: #dcdcdc;
            border-radius: 0.3rem;
        }
        
        .credits-container button {
            margin-top: -3px;
        }
        
        .credits-col {
            display: flex;
            justify-content: flex-end;
        }
        
        .warning-credit-tag {
            border-color: #e75555 !important;
            background-color: #ebbebe !important;
        }
        
        .btn-secondary {
            color: #fff!important;
        }
    </style>
  </head>
  <body>
    <div class="container mt-4">
        <h1 class="text-center mb-5">Google MAP Tool</h1>

        <div class="row">
            <div class="col-md-12 credits-col">
                <div class="credits-container">
                    <a class="btn btn-secondary" id="buy-more-credits">Buy more credits</a>
                    <p class="<?= $credit_remaining == 0 ? 'warning-credit-tag' : '' ?>">Credits Remaining: <b><?= $credit_remaining ?></b></p>
                    <p>API calls: <b><?= $total_api_call ?></b></p>
                </div>
            </div>
            <div class="col-md-12">
                <?php if( isset($_SESSION["warning"])): ?>
                    <div class="alert alert-warning" role="alert"><?= $_SESSION["warning"]; ?></div>
                    
                    <?php unset($_SESSION["warning"]); ?>
                <?php endif; ?>
            </div>
            <div class="col-md-12 pb-5">
                <form method="POST">
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label for="c-country-select" class="form-label">Countries:</label>
                                <select name="country" class="form-control c-select" id="c-country-select">
                                    <option value="" disabled default selected>Select Country</option>
                                    <?php foreach($temp_country as $country): ?>
                                        <option value="<?= $country['id'] ?>" class="<?= $country['id'] ?>"><?= $country["name"] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="c-state-select" class="form-label">State:</label>
                                <select name="state" class="form-control c-select" id="c-state-select">
                                    <option value="" disabled default>Please Select Country</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label for="c-city-select" class="form-label">City:</label>
                                <select name="city" class="form-control c-select" id="c-city-select" required>
                                    <option value="" disabled default>Please Select State</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-3">
                            <div class="form-group">
                                <label for="text_input" class="form-label">Search Query:</label>
                                <input type="text" class="form-control" name="q" value="" id="text_input" aria-describedby="emailHelp" placeholder="" required>
                            </div>
                        </div>
                    </div>
                    <div class="btn-container d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary text-center">Search</button>
                    </div>
                </form>
            </div>
            <div class="col-md-12 mb-3">
                
                <?php if( $t_country ): ?>
                    <span class="">Tags: </span>
                    <span class="badge badge-secondary"><?= $t_country[0] ?></span>
                <?php endif; ?>
                <?php if( $t_state ): ?>
                    <span class="badge badge-success"><?= $t_state[0] ?></span>
                <?php endif; ?>
                <?php if( $t_city ): ?>
                    <span class="badge badge-info"><?= $t_city[0] ?></span>
                <?php endif; ?>
                <span class="badge badge-primary"><?= $q ?></span>
            </div>
            <div class="col-md-12">
                <table class="table" id="c-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Title</th>
                            <th scope="col">Address</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Claimed Listing</th>
                            <th scope="col">Have Website</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($temp_result as $key => $value): ?>
                            <tr>
                                <th scope="row"><?= $key+1 ?></th>
                                <td><?= isset($value["title"]) ? $value["title"] : 'N/a' ?></td>
                                <td><?= isset($value["address"]) ? $value["address"] : 'N/a' ?></td>
                                <td><?= isset($value["phone"]) ? $value["phone"] : 'N/a' ?></td>
                                <td class="text-danger text-center"><b><?= $value["unclaimed_listing"] ?></b></td>
                                <!--<td class="<?= !isset($value["unclaimed_listing"]) ? "text-success" : "text-danger" ?>"><b><?= isset($value["unclaimed_listing"]) ? 'No' : "Yes" ?></b></td>-->
                                <td class="text-center <?= isset($value["website"]) ? "text-success" : "text-danger" ?>">
                                    <?php if( isset($value["website"]) ): ?>
                                        <b><a href="<?= $value["website"] ?>" class="text-success">Yes</a></b>
                                    <?php else: ?>
                                        <b>No</b>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                       
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <!--Custom Script-->
    <script>
        $(document).ready(function () { 
            
            $('#c-table').DataTable(); 
            
            $('#c-country-select').select2();
            $('#c-state-select').select2();
            $('#c-city-select').select2();
            
            // FOR Country Selection Event
            $('#c-country-select').on('select2:select', function (e) {
                // e.preventDefault();
                var data = e.params.data;
                console.log( data )
                
                let formData = new FormData();
                formData.append('get_state', true);
                formData.append('country_id', data.id);
                
                let temp_data = [{
                    id: "",
                    text: "Please wait..."
                }]
                $('#c-state-select').empty().select2({
                    data: temp_data
                });
                
                $.ajax({
                    url: "./api/state_api.php",
                    method: "POST",
                    contentType: false,
                    processData: false,
                    data: formData,
                    success: function (response) {
                        if( response.success ) {
                            $('#c-state-select').empty().select2({
                                data: response.data
                            });
                        }
                        // TO DO - Update Success
                        
                    },
                    error: function (err) {
                        console.log(err)
                    }
                });
            });
            
            // FOR State Selection Envet
            $('#c-state-select').on('select2:select', function (e) {
                // e.preventDefault();
                var data = e.params.data;
                
                let formData = new FormData();
                formData.append('get_city', true);
                formData.append('state_id', data.id);
                
                let temp_data = [{
                    id: "",
                    text: "Please wait..."
                }]
                $('#c-city-select').empty().select2({
                    data: temp_data
                });
                
                $.ajax({
                    url: "./api/city_api.php",
                    method: "POST",
                    contentType: false,
                    processData: false,
                    data: formData,
                    success: function (response) {
                        if( response.success ) {
                            $('#c-city-select').empty().select2({
                                data: response.data
                            });
                        }
                        // TO DO - Update Success
                        
                    },
                    error: function (err) {
                        console.log(err)
                    }
                });
                
                
                console.log(data);
            });
            
            $('#buy-more-credits').on("click", function(){
                alert("Under development");
            });
            
        });
    </script>
  </body>
</html>