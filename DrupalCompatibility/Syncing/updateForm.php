<style>

  form {
    font-family: Lato, Verdana, Helvetica;
  }

  label {
    display: block;
    margin: 10 0;
    width: 600px;
    border-top: 1px solid #FFF;
    border-bottom: 1px solid #FFF;
    padding: 5px;
  }

  label:hover {
    background: rgba(120,220,80,.4);
    border-top: 1px solid #888;
    border-bottom: 1px solid #888;
  }

  input {
    width: 150px;
    margin: 0;
    float: right;
  }

  h3 {
    border-top: 1px solid #999;
    border-bottom: 1px solid #999;
    width: 40%;
    padding: 8px 0;
  }

  ul {
    list-style-type: none;
  }

  input[type='submit'] {
    float: none;
    margin: 0 auto;
  }

  #delete {
      display: none;
  }

  .update-only {
      display: none;
  }
</style>

<!--jQuery-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<?php
// from: http://stackoverflow.com/questions/9802788/call-a-rest-api-in-php
/*
* This uses the PHP curl extension to make an HTTP request.
* The query parameters will be added to the request if you include them in the $data array, so you dont need to include the query
* string in the URL in most cases.
*/
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

//set defualt timezone for dropbox marking
date_default_timezone_set('EST5EDT');

/*
* Sends a request to the dropbox API containing the three cartodb tables.
* Both parameters are strings. $file_content is the contents of the file to be uploaded and $name is its name.
*/
function BackupToDropBox($file_content, $name) {
    //http://www.lornajane.net/posts/2009/putting-data-fields-with-php-curl

    $header = array(
        'Authorization: Bearer Place Holder',
        'Content-Length: ' . strlen($file_content),
        'Content-Type: text/csv; charset=UTF-8'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_URL, 'https://api-content.dropbox.com/1/files_put/auto/' . $name);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $response = curl_exec($ch);

    if(!$response) {
        return false;
        echo "Something Happened";
    } else {
        return $response;
    }
}

/*
* Takes an array that represents JSON as a parameter
* Simple function that looks at the array and sets a drupal error if the JSON has an array field, which only happens when cartodb is markerrad
* Then it returns the same array so that a variable can be set to it.
*/
function CheckCartodbError($JSONarray) {
    if (isset($JSONarray['error'])) {
        drupal_set_message('CartoDB error: ' . $JSONarray['error'][0], 'error');
    }
    return $JSONarray;
}
/*
* This adds the type and area served data to a single lookup table to be specified
* $array is the array of form data used, $table is the name of the table, area_served or type, $name_col is the column with the names in that table,
* $prop is the name of the property, type or area, $loc_id is the id of the location that is being added to, and $key is the api_key
*/
function AddToLookup ($array, $table, $name_col, $prop, $loc_name, $key) {
    $url_base = 'https://haverfordds.cartodb.com/api/v2/sql';
=    $url_params = array(
        'format' => 'JSON',
        'q' => '',
        'api_key' => $key
    );

    //get the cartodb_id of the location
    $loc_id_sql = 'SELECT cartodb_id FROM location WHERE loc_name=\'' . $loc_name . '\'';
    $url_params['q'] = $loc_id_sql;
    $loc_id_json = CheckCartodbError(json_decode(CallAPI('GET', $url_base, $url_params), true));
    $loc_id = $loc_id_json['rows'][0]['cartodb_id'];

    //Get the cartodb_id for each type that was checked
    foreach ($array as $name) {
        $lookup_id_sql = 'SELECT cartodb_id FROM ' . $table . ' WHERE ' . $name_col . '=\'' . $name . '\'';
        $url_params['q'] = $lookup_id_sql;
        $lookup_id_json = CheckCartodbError(json_decode(CallAPI('GET', $url_base, $url_params), true));
        $lookup_id = $lookup_id_json['rows'][0]['cartodb_id'];

        //Now add the entry to the lookup table
        $add_lookup_sql = 'INSERT INTO lookup_loc_' . $prop . ' (loc_id,'. $prop .'_id) VALUES (' . $loc_id . ',' . $lookup_id . ')';
        $url_params['q'] = $add_lookup_sql;
        CheckCartodbError(json_decode(CallAPI('POST', $url_base, $url_params), true));
    }
}

//Removes all entries related to the $Loc_name from both lookup tables, $prop is the propert (area or type), and key is the api key
function DeleteFromLookup($prop, $loc_name, $key) {
    $url_base = 'https://haverfordds.cartodb.com/api/v2/sql';
    $del_sql = 'DELETE FROM lookup_loc_' . $prop . ' WHERE loc_id IN (SELECT cartodb_id FROM location WHERE location.loc_name=\'' . $loc_name . '\')';
    $url_params = array(
        'format' => 'JSON',
        'q' => $del_sql,
        'api_key' => $key
    );
    CheckCartodbError(json_decode(CallAPI('POST', $url_base, $url_params), true));
}

function isNotEmptyString($string) {
    return ($string !== '');
}

//Check if the form is being submitted vs. the user just visiting the page
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $form_mode = $_POST['form-mode'];
    $form_data = str_replace("'","''",$_POST);

    $base_url = 'https://haverfordds.cartodb.com/api/v2/sql';

    //Backup all tables to dropbox before updating
    $backup_params = array(
        'format' => 'CSV',
        'q' => 'SELECT * FROM location ORDER BY cartodb_id'
    );
    //First backup location
    $location_bak = CallAPI('GET', $base_url, $backup_params);
    BackupToDropBox($location_bak, 'location' . date(DATE_ATOM) . '.csv');

    //Then the lookup tables
    $backup_params['q'] = 'SELECT * FROM lookup_loc_area ORDER BY cartodb_id';
    $lookup_area_bak = CallAPI('GET', $base_url, $backup_params);
    BackupToDropBox($lookup_area_bak, 'lookup_loc_area' . date(DATE_ATOM) . '.csv');
    $backup_params['q'] = 'SELECT * FROM lookup_loc_type ORDER BY cartodb_id';
    $lookup_type_bak = CallAPI('GET', $base_url, $backup_params);
    BackupToDropBox($lookup_type_bak, 'lookup_loc_type' . date(DATE_ATOM) . '.csv');

    //The CartoDB api key
    $api_key = 'Place Holder';
    //parameters for making a call to cartodb
    $params = array(
        'format' => 'JSON',
        'q' => '',
        'api_key' => $api_key
    );

    if ($form_mode === 'add') {
        //check to make sure the location is not already in the database
        $params['q'] = 'SELECT * FROM location WHERE loc_name =\'' . $form_data['loc_name'] . '\'';
        $data = CheckCartodbError(json_decode(CallAPI('GET', $base_url, $params), true));
        if (!empty($data['rows'])) {
            echo "Sorry, a location with the name " . htmlspecialchars($_POST['loc_name']) . " is already in the database. Use 'update' to change its information.";
        } else {
            $types = $form_data['types'];
            $areas = $form_data['areas'];

            $loc_sql = 'INSERT INTO location (loc_name, link, email, phone_number, address, city, state, zipcode, mission, the_geom) ' .
                        'VALUES (\'' . $form_data['loc_name'] . '\',\'' . $form_data['link'] . '\',\'' . $form_data['email'] . '\',\'' .
                        $form_data['phone_number'] . '\',\'' . $form_data['address'] . '\',\'' . $form_data['city'] . '\',\'' .
                        $form_data['state'] . '\',\'' . $form_data['zipcode'] . '\',\'' . $form_data['mission'] . '\',ST_SetSRID(ST_Point(\'' .
                        $form_data['longitude'] . '\'::float,\'' . $form_data['latitude'] . '\'::float), 4326))';

            //first add the location
            $params['q'] = $loc_sql;
            CheckCartodbError(json_decode(CallAPI('POST', $base_url, $params), true));

            //Now add each type to the lookup table
            AddToLookup($types, 'type', 'type', 'type', $form_data['loc_name'], $api_key);
            AddToLookup($areas, 'area_served', 'area_name', 'area', $form_data['loc_name'], $api_key);

            echo "Successfully added " . htmlspecialchars($_POST['loc_name']);
        }
    } else if ($form_mode === 'delete') {

        //Delete the location's lookup entries
        DeleteFromLookup('type', $form_data['del_name'], $api_key);
        DeleteFromLookup('area', $form_data['del_name'], $api_key);

        //Now remove it from location
        $del_sql = 'DELETE FROM location WHERE loc_name=\'' . $form_data['del_name'] . '\'';
        $params['q'] = $del_sql;
        $del_json = CheckCartodbError(json_decode(CallAPI('POST', $base_url, $params), true));

        //Check to see if the location was actually in the databse. Perhaps this should be done first?
        if (isset($del_json['total_rows']) and $del_json['total_rows'] === 0) {
            echo htmlspecialchars($_POST['del_name']) . 'is not in the database.';
        } else if(isset($del_json['total_rows'])) {
            echo 'Successfully deleted ' . htmlspecialchars($_POST['del_name']);
        }
    } else if ($form_mode === 'update') {
        //First make sure the location to be updated is actually in the database
        $params['q'] = 'SELECT * FROM location WHERE loc_name =\'' . $form_data['old_name'] . '\'';
        $data = CheckCartodbError(json_decode(CallAPI('GET', $base_url, $params), true));
        if (empty($data['rows'])) {
            echo "The location: " . htmlspecialchars($form_data['old_name']) . " could not be found in the databse and was not updated.";
        } else {
            $update_loc = $form_data;

            //Remove keys that do not correspond to actual columns in the database
            unset($update_loc['form-mode']);
            unset($update_loc['types']);
            unset($update_loc['areas']);
            unset($update_loc['old_name']);
            unset($update_loc['longitude']);
            unset($update_loc['latitude']);

            //Format columns and values so that they work for the Postgres update statement
            $columns = '(' . implode(', ', array_keys(array_filter($update_loc,'isNotEmptyString')));
            $values = '(\'' . implode('\', \'', array_filter($update_loc,'isNotEmptyString')) . '\'';

            //add the_geom to columns and values if longitude and latitude are set equal to a number
            if ($form_data['longitude'] !== '' and $form_data['latitude'] !== '' and is_numeric($form_data['longitude']) and is_numeric($form_data['latitude'])) {
                $columns = $columns . ',the_geom';
                $values = $values . ',ST_SetSRID(ST_Point(\'' . $form_data['longitude'] . '\'::float,\'' . $form_data['latitude'] . '\'::float), 4326)';
                //reformat strings in the case that there is nothing being updated besides the geom
                if (!count(array_filter($update_loc,'isNotEmptyString'))) {
                    $values = '(' . substr($values, 4);
                    $columns = '(' . substr($columns, 2);
                }
            }

            $columns = $columns . ')';
            $values = $values . ')';
            $update_sql = 'UPDATE location SET ' . $columns . ' = ' . $values . ' WHERE loc_name=\'' . $form_data['old_name'] . '\'';
            $params['q'] = $update_sql;

            //Don't call the API if nothing is getting updated. This could be checked earlier
            if ($columns !== '()') {
                CheckCartodbError(json_decode(CallAPI('POST', $base_url, $params), true));
            }

            //Change the $current name to match whatever the name was set too if the old_name was overriden (updated)
            $current_name = $form_data['old_name'];
            if (isNotEmptyString($update_loc['loc_name'])) {
                $current_name = $update_loc['loc_name'];
            }

            //if types or areas have been changed, delete all the old ones and add the new ones
            if (array_key_exists('types', $form_data)) {
                DeleteFromLookup('type', $current_name, $api_key);
                AddToLookup($form_data['types'], 'type', 'type', 'type', $current_name, $api_key);
            }
            if (array_key_exists('areas', $form_data)) {
                DeleteFromLookup('area', $current_name, $api_key);
                AddToLookup($form_data['areas'], 'area_served', 'area_name', 'area', $current_name, $api_key);
            }
            echo "Updated!";
        }
    }
}
?>
<div>
</div>

<form method="post" action="http://ds.haverford.edu/mappingmicrofinance/node/7465">
    <select name="form-mode">
        <option value="add">Add a New Location</option>
        <option value="update">Update an Existing Location</option>
        <option value="delete">Delete a Location</option>
    </select>

<div id="add-update">
    <div class="update-only">
        <p>Please enter the name of the location you wish to update.</p>
        <label>Name:<input id="old_name" type="text" name="old_name"></label>
        <p>Enter the new information below, leave a field blank if you don't want to change it.</p>
    </div>
    <label>Location Name:<input id="loc_name" type="text" name="loc_name"></label>
  <label>Link to Website:<input id="link" type="text" name="link"></label>
  <label>Email:<input id="email" type="text" name="email"></label>
  <label>Phone Number:<input id="phone_number" type="text" name="phone_number"></label>
  <label>Address:<input id="address" type="text" name="address"></label>
  <label>City:<input id="city" type="text" name="city"></label>
  <label>State:<input id="state" type="text" name="state"></label>
  <label>Zip Code:<input id="zipcode" type="text" name="zipcode"></label>
  <label>Mission Statement:<textarea id="mission" type="textarea" rows="6" cols="72" name="mission"></textarea></label>
  <label>Longitude:<input id="longitude" type="number" value="" name="longitude" step="any"></label>
  <label>Latitude:<input id="latitude" type="number" value="" name="latitude" step="any"></label>
  <div id="toggle-panels">
  <div class="update-only">
      Warning: Changing a type or area served will delete all previous types or areas served
  </div>
  <span id="before-types"></span>
  <h3 id="types-header">Types</h3>
  <div id="types">
    <ul>
      <li><label>1-on-1 Consulting<input type="checkbox" name="types[]" value="1-on-1 Consulting"></label></li>
      <li><label>Classes<input type="checkbox" name="types[]" value="Classes"></label></li>
      <li><label>Directory<input type="checkbox" name="types[]" value="Directory"></label></li>
      <li><label>Financial Literacy<input type="checkbox" name="types[]" value="Financial Literacy"></label></li>
      <li><label>Grants<input type="checkbox" name="types[]" value="Grants"></label></li>
      <li><label>Group Consulting<input type="checkbox" name="types[]" value="Group Consulting"></label></li>
      <li><label>Group Network<input type="checkbox" name="types[]" value="Group Network"></label></li>
      <li><label>Incubator<input type="checkbox" name="types[]" value="Incubator"></label></li>
      <li><label>Kiva Zip Trustee<input type="checkbox" name="types[]" value="Kiva Zip Trustee"></label></li>
      <li><label>Loan Intermediary<input type="checkbox" name="types[]" value="Loan Intermediary"></label></li>
      <li><label>Microloans<input type="checkbox" name="types[]" value="Microloans"></label></li>
      <li><label>Midsize Loans<input type="checkbox" name="types[]" value="Midsize Loans"></label></li>
      <li><label>Networking Events<input type="checkbox" name="types[]" value="Networking Events"></label></li>
      <li><label>Regulatory Help<input type="checkbox" name="types[]" value="Regulatory Help"></label></li>
      <li><label>Relocation<input type="checkbox" name="types[]" value="Relocation"></label></li>
      <li><label>Savings Program<input type="checkbox" name="types[]" value="Savings Program"></label></li>
      <li><label>Seed Capital<input type="checkbox" name="types[]" value="Seed Capital"></label></li>
      <li><label>Workshops<input type="checkbox" name="types[]" value="Workshops"></label></li>
      <li><label>Work Space<input type="checkbox" name="types[]" value="Work Space"></label></li>
    </ul>
  </div>
  <span id="before-areas"></span>
  <h3 id="areas-header">Areas Served</h3>
  <div id="areas">
    <ul>
      <li><label>Atlantic County<input type="checkbox" name="areas[]" value="Atlantic County"></label></li>
      <li><label>Berks County<input type="checkbox" name="areas[]" value="Berks County"></label></li>
      <li><label>Brewerytown, Philadelphia<input type="checkbox" name="areas[]" value="Brewerytown, Philadelphia"></label></li>
      <li><label>Bucks County<input type="checkbox" name="areas[]" value="Bucks County"></label></li>
      <li><label>Burlington County<input type="checkbox" name="areas[]" value="Burlington County"></label></li>
      <li><label>Camden County<input type="checkbox" name="areas[]" value="Camden County"></label></li>
      <li><label>Carbon County<input type="checkbox" name="areas[]" value="Carbon County"></label></li>
      <li><label>Center City District<input type="checkbox" name="areas[]" value="Center City District"></label></li>
      <li><label>Chester County<input type="checkbox" name="areas[]" value="Chester County"></label></li>
      <li><label>Chinatown, Philadelphia<input type="checkbox" name="areas[]" value="Chinatown, Philadelphia"></label></li>
      <li><label>Cumberland County<input type="checkbox" name="areas[]" value="Cumberland County"></label></li>
      <li><label>Delaware County<input type="checkbox" name="areas[]" value="Delaware County"></label></li>
      <li><label>Delaware State<input type="checkbox" name="areas[]" value="Delaware State"></label></li>
      <li><label>East Kensington, Philadelphia<input type="checkbox" name="areas[]" value="East Kensington, Philadelphia"></label></li>
      <li><label>Fairmount, Philadelphia<input type="checkbox" name="areas[]" value="Fairmount, Philadelphia"></label></li>
      <li><label>Fishtown, Philadelphia<input type="checkbox" name="areas[]" value="Fishtown, Philadelphia"></label></li>
      <li><label>Francisville, Philadelphia<input type="checkbox" name="areas[]" value="Francisville, Philadelphia"></label></li>
      <li><label>Frankford, Philadelphia<input type="checkbox" name="areas[]" value="Frankford, Philadelphia"></label></li>
      <li><label>Gloucester County<input type="checkbox" name="areas[]" value="Gloucester County"></label></li>
      <li><label>Greater Philadelphia Area<input type="checkbox" name="areas[]" value="Greater Philadelphia Area"></label></li>
      <li><label>Harrowgate, Philadelphia<input type="checkbox" name="areas[]" value="Harrowgate, Philadelphia"></label></li>
      <li><label>Kent County<input type="checkbox" name="areas[]" value="Kent County"></label></li>
      <li><label>Lehigh County<input type="checkbox" name="areas[]" value="Lehigh County"></label></li>
      <li><label>Main Line Region<input type="checkbox" name="areas[]" value="Main Line Region"></label></li>
      <li><label>Mercer County<input type="checkbox" name="areas[]" value="Mercer County"></label></li>
      <li><label>Montgomery County<input type="checkbox" name="areas[]" value="Montgomery County"></label></li>
      <li><label>New Castle County<input type="checkbox" name="areas[]" value="New Castle County"></label></li>
      <li><label>New Jersey<input type="checkbox" name="areas[]" value="New Jersey"></label></li>
      <li><label>New York<input type="checkbox" name="areas[]" value="New York"></label></li>
      <li><label>Northampton County<input type="checkbox" name="areas[]" value="Northampton County"></label></li>
      <li><label>North Central Philadelphia Empowerment Zone<input type="checkbox" name="areas[]" value="North Central Philadelphia Empowerment Zone"></label></li>
      <li><label>Olde Richmond, Philadelphia<input type="checkbox" name="areas[]" value="Olde Richmond, Philadelphia"></label></li>
      <li><label>Open<input type="checkbox" name="areas[]" value="Open"></label></li>
      <li><label>Pennsylvania<input type="checkbox" name="areas[]" value="Pennsylvania"></label></li>
      <li><label>Philadelphia County<input type="checkbox" name="areas[]" value="Philadelphia County"></label></li>
      <li><label>Port Richmond, Philadelphia<input type="checkbox" name="areas[]" value="Port Richmond, Philadelphia"></label></li>
      <li><label>Salem County<input type="checkbox" name="areas[]" value="Salem County"></label></li>
      <li><label>Schuylkill County<input type="checkbox" name="areas[]" value="Schuylkill County"></label></li>
      <li><label>Southeastern Pennsylvania<input type="checkbox" name="areas[]" value="Southeastern Pennsylvania"></label></li>
      <li><label>South Street Head House District<input type="checkbox" name="areas[]" value="South Street Head House District"></label></li>
      <li><label>Spring Garden, Philadelphia<input type="checkbox" name="areas[]" value="Spring Garden, Philadelphia"></label></li>
      <li><label>Sussex County<input type="checkbox" name="areas[]" value="Sussex County"></label></li>
      <li><label>United States<input type="checkbox" name="areas[]" value="United States"></label></li>
      <li><label>West Philadelphia<input type="checkbox" name="areas[]" value="West Philadelphia"></label></li>
    </ul>
  </div>
</div>
</div>
<div id="delete">
    <p>Enter a Location Name to Delete It:</p>
    <label>Location Name: <input type="text" id="del_name" name="del_name"></label>
</div>
<div id="feedback"><input type="submit" value="Submit"></div>
</form>

<script type="text/javascript">

(function($) {
//This is the same function as used in DrupalMap.html
//Function to create a menu. Taken from http://jsfiddle.net/DkHyd/ I AM NOT SURE WHO WROTE THIS CODE
$.fn.togglepanels = function(){
  return this.each(function(){
    $(this).find("h3")
    .click(function() {
      $(this)
        .find("> .ui-icon").toggleClass("ui-icon-triangle-1-e ui-icon-triangle-1-s").end()
        .next().slideToggle();
      return false;
    })
    .next()
      .hide();
  });
};
$(document).ready(function() {

  $('#toggle-panels').togglepanels();

  //Allow the user to switch between add, update, and delete
  $('select').change(function() {
     $('.warning').hide();
     if ($(this).val() === 'delete') {
         $('#delete').show();
         $('#add-update').hide();
     } else {
         $('#delete').hide();
         $('#add-update').show();
         if($(this).val() === 'update') {
             $('.update-only').show();
         } else {
             $('.update-only').hide();
         }
     }
  });

  //stops the defualt form and adds a warning to the incorrect field
  function appendWarning(id, warning, e) {
     $("<p class=\"warning\">" + warning + "</p>").insertAfter('#' + id).css("color", "red");
     e.preventDefault();
  }

  //Form validation
  $('form').submit(function(event) {
      $('.warning').hide();
      if ($('select').val() === 'add') {
          if ($('#loc_name').val() === '') {
              appendWarning('loc_name', 'Please enter a Location Name', event);
          }
          if ($('#link').val() !== '' && $('#link').val().indexOf('http') != 0) {
              $('#link').val('http://' + $('#link').val());
          }
          //Check that at least one type and area served have been checked
          if ($('#types input:checked').length === 0) {
              appendWarning('before-types','Please Check at Least One Type', event);
          }
          if ($('#areas input:checked').length === 0) {
              var areaWarning = appendWarning('before-areas','Please Check at Least One Area Served', event);
          }
          if (isNaN($('#longitude').val()) || $('#longitude').val() === '') {
              appendWarning('longitude', 'Please Enter a Number', event);
          }
          if (isNaN($('#latitude').val()) || $('#latitude').val() === '') {
              appendWarning('latitude', 'Please Enter a Number', event);
          }
      } else if ($('select').val() === 'delete') {
          if (!confirm('Are you sure you want to delete ' + $('#del_name').val() + '? This cannot be undone.')) {
              event.preventDefault();
          }
      } else if ($('select').val() === 'update') {
          if ($('#old_name').val() === '') {
              appendWarning('old_name', 'Please enter a Location Name to Update', event);
          }
      }
  });
});
})(jQuery);

</script>
