<?php

// from: http://stackoverflow.com/questions/9802788/call-a-rest-api-in-php
// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value
function CallAPI($method, $url, $data = false)
{
    $curl = curl_init();

    switch ($method)
    {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);

            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_PUT, 1);
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
}

//$array is the array of form data used, $table is the name of the table, area_served or type, $name_col is the column with the names in that table,
//$prop is the name of the property, type or area, $loc_id is the id of the location that is being added to, and $key is the api_key
function AddToLookup ($array, $table, $name_col, $prop, $loc_id, $key) {
    //Get the cartodb_id for each type that was checked
    foreach ($array as $name) {
        $url_base = 'https://haverfordds.cartodb.com/api/v2/sql';
        $lookup_id_sql = 'SELECT cartodb_id FROM ' . $table . ' WHERE ' . $name_col . '=\'' . $name . '\'';
        $url_params = array(
            'format' => 'JSON',
            'q' => $lookup_id_sql,
            'api_key' => $key
        );
        $lookup_id_json = json_decode(CallAPI('GET', $url_base, $url_params), true);
        $lookup_id = $lookup_id_json['rows'][0]['cartodb_id'];

        $add_lookup_sql = 'INSERT INTO lookup_loc_test (loc_id,'. $prop .'_id) VALUES (' . $loc_id . ',' . $lookup_id . ')';
        $url_params['q'] = $add_lookup_sql;
        CallAPI('POST', $url_base, $url_params);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //First, validate the form data
    $types = $_GET['types'];
    $areas = $_GET['areas'];

    $loc_sql = 'INSERT INTO location (loc_name, link, email, phone_number, address, city, state, zipcode, mission, the_geom) ' .
                'VALUES (\'' . $_GET['loc_name'] . '\',\'' . $_GET['link'] . '\',\'' . $_GET['email'] . '\',\'' .
                $_GET['phone_number'] . '\',\'' . $_GET['address'] . '\',\'' . $_GET['city'] . '\',\'' .
                $_GET['state'] . '\',\'' . $_GET['zipcode'] . '\',\'' . $_GET['mission'] . '\',ST_SetSRID(ST_Point(\'' .
                $_GET['longitude'] . '\'::float,\'' . $_GET['lattitude'] . '\'::float), 4326))';
    $api_key='KEYGOESHERE';

    //first add the location
    $params = array(
        'format' => 'JSON',
        'q' => $loc_sql,
        'api_key' => $api_key
    );
    $base_url = 'https://haverfordds.cartodb.com/api/v2/sql';
    CallAPI('POST', $base_url, $params);

    //then get its new cartodb_id
    $loc_id_sql = 'SELECT cartodb_id FROM location WHERE loc_name=\'' . $_GET['loc_name'] . '\'';
    $params['q'] = $loc_id_sql;
    $loc_id_json = json_decode(CallAPI('GET', $base_url, $params), true);
    $loc_id = $loc_id_json['rows'][0]['cartodb_id'];

    //Now add each type to the lookup table
    AddToLookup($types, 'type', 'type', 'type', $loc_id, $api_key);
    AddToLookup($areas, 'area_served', 'area_name', 'area', $loc_id, $api_key);

    echo htmlspecialchars($loc_sql);
    echo htmlspecialchars(implode($_GET['types']));
    echo htmlspecialchars(implode($_GET['areas']));
}
?>
