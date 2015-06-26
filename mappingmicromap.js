infoTabsAllowed = 5; //Actually the amount of tabs allowed + 1

//Function to create a menu. Taken from http://jsfiddle.net/DkHyd/ I AM NOT SURE WHO WROTE THIS CODE
$.fn.togglepanels = function(){
  return this.each(function(){
    $(this).addClass("ui-accordion ui-accordion-icons ui-widget ui-helper-reset")
  .find("h3")
    .addClass("ui-accordion-header ui-helper-reset ui-state-default ui-corner-top ui-corner-bottom")
    .hover(function() { $(this).toggleClass("ui-state-hover"); })
    .click(function() {
      $(this)
        .toggleClass("ui-accordion-header-active ui-state-active ui-state-default ui-corner-bottom")
        .find("> .ui-icon").toggleClass("ui-icon-triangle-1-e ui-icon-triangle-1-s").end()
        .next().slideToggle();
      return false;
    })
    .next()
      .addClass("ui-accordion-content ui-helper-reset ui-widget-content ui-corner-bottom")
      .hide();
  });
};

//The function below causes the cursor to switch to a pointer when hovering over a feature
//It is by Javi Santana, taken from https://gist.github.com/javisantana/6102814
function addCursorInteraction(layer) {
        var hovers = [];

        layer.bind('featureOver', function(e, latlon, pxPos, data, layer) {
          hovers[layer] = 1;
          if(_.any(hovers)) {
            $('#map').css('cursor', 'pointer');
          }
        });

        layer.bind('featureOut', function(m, layer) {
          hovers[layer] = 0;
          if(!_.any(hovers)) {
            $('#map').css('cursor', 'auto');
          }
        });
      }



window.onload = function () {
  var origSql = "SELECT the_geom_webmercator,cartodb_id,loc_name,address,email,mission,phone_number,city,state,zipcode,the_geom,link " +
                "FROM location";
  //First get the parameters out of the hash, from http://stackoverflow.com/questions/901115/how-can-i-get-query-string-values-in-javascript/2880929#2880929
  var urlParams;
  var match,
    pl     = /\+/g,  // Regex for replacing addition symbol with a space
    search = /([^&=]+)=?([^&]*)/g,
    decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
    query  = window.location.hash.substring(1);

  urlParams = {};
  while (match = search.exec(query)) {
     urlParams[decode(match[1])] = decode(match[2]);
   }
   console.log(urlParams);
  //takes the url parameters and updates the hash to represent filter settings
  function updateHash(urlp) {
    newHash = "";
    _.each(urlp, function(val, key, list){
      if(val) {
        newHash = newHash + "&" + key + "=" + val;
      }
    });
    newHash = "#" + newHash.substring(1);
    window.location.hash = newHash;
  }
  //Joins the area_sql and type_sql so that the filters work together
  function finalizeSQL(layer, area_sql, type_sql, urlp) {
    var finalSQL = "";
    if (area_sql != "" && type_sql != "") {
      finalSQL = "SELECT a.the_geom_webmercator,a.cartodb_id,a.loc_name,a.address,a.email,a.mission,a.phone_number,a.city,a.state,a.zipcode,a.the_geom,a.link FROM (" +
                  area_sql + ") AS a JOIN (" + type_sql + ") AS t USING (loc_name)";
    } else if (area_sql == "") {
      finalSQL = type_sql;
    } else {
      finalSQL = area_sql;
    }
    //Change the parameters to represent the current filter situation
    $("input:checked").each(function() {
      urlp[$(this).attr("data-urlid")]="1";
    });
    $("input:checkbox:not(:checked)").each(function() { //http://stackoverflow.com/a/8465833
      urlp[$(this).attr("data-urlid")]="";
    });
    urlp["zip"]=$("#zip-field").val();
    updateHash(urlp);
    console.log(urlp);
    layer.setSQL(finalSQL);
  }

  //Filters to only show areas that intersect with a zipcode when the user puts their info into the window
  function answerForm() {
    zipreg = /^\d{5}(?:[-\s]\d{4})?$/; //from http://stackoverflow.com/questions/2577236/regex-for-zip-code
    var inval = $("#zip-field").val();
    var basesql = "SELECT l.the_geom_webmercator,l.cartodb_id,l.loc_name,l.address,l.email,l.mission,l.phone_number,l.city,l.state,l.zipcode,l.the_geom,l.link,a.the_geom as area_geom " +
                  "FROM location AS l " +
                  "JOIN lookup_loc_area AS looka ON looka.loc_id=l.cartodb_id " +
                  "JOIN area_served AS a ON looka.area_id=a.cartodb_id";
    if (zipreg.test(inval)) {
      inval = inval.substring(0,5);
      var newSQL = "SELECT * FROM (" + basesql + ") AS thelayer WHERE ST_Intersects(area_geom, (SELECT the_geom FROM pa_nj_de_zcta5ce WHERE zcta5ce::integer=" + inval+"))";
      $("#form-feedback").html("Here you go!");
      return newSQL;
    } else {
      $("#form-feedback").html("Not a Valid Zip Code");
      return "";
    }
  }

  function createTypeSQL() {
    var sql = "SELECT * FROM location WHERE cartodb_id IN (SELECT loc_id FROM lookup_loc_type WHERE type_id IN (SElECT cartodb_id FROM type AS t";
    if (!($('#selectall').is(":checked")) && $("input:checked").length) {
      //Here selectall is not checked, but there are other checked boxes
      sql = sql + " WHERE";
      $("input:checked").each(function(i) {
        //iterates through the checked boxes to construct the SQL
        sql = sql + $(this).attr("data") + " OR ";
      });
      //deletes the last OR, maybe a better way to do this?
      sql = sql.substring(0,sql.length - 4);
    } else if (!($("input:checked").length)) {
      //display nothing if there are no boxes checked
      sql = "((SELECT * FROM location limit 0";
    }
    sql = sql + "))";
    return sql;
  }
  // Create layer selector
  function createSelector(layer, urlp) {
      var type_sql = "";
      var area_sql = "";
      //Take the starting URL Parameters and filter the data accordingly
      _.each(urlp, function(val, key, list) {
        if(val) {
          $("[data-urlid=" + key + "]").prop("checked", true);
        }
      })
      if (urlp.zip) {
        if(!$("input:checked").length) {
          $("#selectall").prop("checked", true);
        }
        $("#zip-field").val(urlp.zip);
      }
      $("#actual-form").submit(function() {
        area_sql=answerForm();
        finalizeSQL(layer, area_sql, type_sql, urlp);
      });
      //deselect button functionality
      $("#deselect").click(function() {
        $("input:checked").prop("checked",false);
        sql = "SELECT * FROM location limit 0";
        type_sql = sql;
        finalizeSQL(layer,area_sql,type_sql, urlp);
      });
      //Adds functionality to button for to reset zip code filtering
      $("#reset-zip").click(function(){
        area_sql = "SELECT * FROM location";
        $("#zip-field").val("");
        finalizeSQL(layer, area_sql, type_sql, urlp);
      })
      //Selects input tags, not sure why CartoDB uses find
      var $options = $(".layer_selector").find("input");
      //Anonymous function for when the checkbox changes
      $options.change(function(e) {
        type_sql = createTypeSQL();
        finalizeSQL(layer, area_sql, type_sql, urlp);
      });
  }

  $("#actual-form > input").keyup(function(e){
    if (e.which != 13) { //only resets the form-feedback if a key besides enter is pressed
      $("#form-feedback").html("");
    }
  });

  //Function for when you click the close button on the infowindow


    // Put layer data into a JS object

    var layerSource = {
            user_name: 'haverfordds',
            type: 'cartodb',
            sublayers: [
            {
              sql: "SELECT the_geom_webmercator, cartodb_id FROM pa_counties_clip",
              cartocss: $("#county").text()
            },
            {
              sql: "SELECT the_geom_webmercator, cartodb_id FROM tl_2010_34_county10",
              cartocss: $("#county").text()
            },
            {
              sql: "SELECT the_geom_webmercator, cartodb_id FROM tl_2010_10_county10",
              cartocss: $("#county").text()
            },
            {
                sql: origSql,
                cartocss: $("#simple").text(),
                interactivity: "cartodb_id, the_geom_webmercator, address, email, phone_number, mission, the_geom, state, city, loc_name, zipcode, link" // Simple visualization
            }]
        };

    // Instantiate new map object, place it in 'map' element
    var map_object = new L.Map('map', {
        center: [39.9500,-75.1667], // Philly
        zoom: 9
    });

    //gets the data for the info window, first all overlapping points must be detected
    //much of this is based on http://zevross.com/blog/2014/05/05/cartodb-handling-infowindows-for-overlapping-features/
    function fillInfowindow(data, lat, sql) {
      var z = map_object.getZoom();
      var c = 40075000; //equatorial circumference of the earth in meters
      var markerrad = 7; //this is half the marker width plus two pixels to get more than just direct overlaps
      var radius = markerrad * Math.abs(c  * Math.cos(lat * (Math.PI/180)) / Math.pow(2,z + 8));//equation from open street map wiki
      var q = "SELECT * FROM ("+ sql + ") AS thelayer WHERE ST_Contains((SELECT ST_Buffer(the_geom::geography, " + radius + ")::geometry FROM location WHERE cartodb_id=" + data.cartodb_id + "), the_geom) ORDER BY loc_name";
      $.getJSON('http://haverfordds.cartodb.com/api/v2/sql/?q='+ q, function(datas) {
        console.log(datas);
        //Because the way the join works creates multiple data points with the same name (but different types), these duplicates must be deleted
        //THERE MAY BE A BETTER WAY. Also, if we want to track types then that will have to be updated as the duplicates are deleted
        var noDupes = [];
        _.each(datas.rows, function(ele, ind, list) {
          if (ind == 0 || list[ind].loc_name != list[ind-1].loc_name) {
            noDupes.push(list[ind]);
            console.log(ind);
          }
        });
        console.log(noDupes);
        var address      = _.pluck(noDupes, "address");
        var mission      = _.pluck(noDupes, "mission");
        var email        = _.pluck(noDupes, "email");
        var phone_number = _.pluck(noDupes, "phone_number");
        var state        = _.pluck(noDupes, "state");
        var city         = _.pluck(noDupes, "city");
        var loc_name     = _.pluck(noDupes, "loc_name");
        var zipcode      = _.pluck(noDupes, "zipcode");
        var link         = _.pluck(noDupes, "link");
        console.log(address);

        var infohtml =_.template($("#info-template").html(), {length: noDupes.length});
        console.log(infohtml);
        $("#infowindow").html(infohtml);
        $("#infowindow > span").click(function() {
          $("#infowindow").css('display','none');
        });
        //Function for creating a small mission statement that drops down to a larger one

        if(noDupes.length < infoTabsAllowed) {
          var index = 0;
        //At first the tab is set to show the data for the first point in the list, but only if the number of locations at this point is less than the desired amount of tabs to show
          var bodyhtml = _.template($("#info-body-template").html(), {infoTabsAllowed: infoTabsAllowed, name: loc_name[index], address: address[index],
                                                                      email: email[index], mission: mission[index], phone_number: phone_number[index], state: state[index], city: city[index],
                                                                      zipcode: zipcode[index], link: link[index]});
          $("#tab0").addClass("info-selected");
          $("#info-body").html(bodyhtml);
          $("#mission").click(function() {
            $("#full-statement").slideToggle();
          });
        }
        //When a different tab is clicked this changes the data in the body of the infowindow
        $(".info-tab").click(function() {
          if(!($(this).hasClass("info-selected"))){
            $(".info-selected").removeClass("info-selected");
            $(this).addClass("info-selected");
            index = Number($(this).attr("data"));
            bodyhtml = _.template($("#info-body-template").html(), {infoTabsAllowed: infoTabsAllowed, name: loc_name[index], address: address[index],
                                                                    email: email[index], mission: mission[index], phone_number: phone_number[index], state: state[index], city: city[index],
                                                                    zipcode: zipcode[index], link: link[index]});
            $("#info-body").html(bodyhtml);
            $("#mission").click(function() {
              $("#full-statement").slideToggle();
            });
          }
        });
        $("#infowindow").css('display','inline');
      });
    }
    L.tileLayer('http://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map_object);

    // Add data layer to your map
    cartodb.createLayer(map_object,layerSource)
        .addTo(map_object)
        .done(function(layer) {
            sublayer = layer.getSubLayer(3);
            createSelector(sublayer, urlParams);
            if($("input:checked").length) {
              finalizeSQL(sublayer, answerForm(), createTypeSQL(), urlParams);
            }
            $('#category-menu').togglepanels();
            sublayer.setInteraction(true);
            addCursorInteraction(sublayer);
            sublayer.on('featureClick', function(e, latlng, pos, data) {
              var subSQL = sublayer.getSQL();
              fillInfowindow(data, latlng[0], subSQL);
            });

        })
        .error(function(err) {
            console.log("error: " + err);
        });
}
