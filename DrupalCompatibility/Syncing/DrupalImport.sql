/*Final Query Used to import the data into Drupal using the Drupal Import Feeds module and the CartoDB SQL API.
* The final URL is:
https://haverfordds.cartodb.com/api/v2/sql?format=CSV&q=SELECT%20*%20FROM%20(SELECT%20cartodb_id,%20city,%20email,%20link,%20loc_name,%20mission,%20phone_number,%20state,%20zipcode,%20address,string_agg(area_name,%27,%27%20ORDER%20BY%20area_name)%20AS%20areas%20FROM%20(SELECT%20l.cartodb_id,%20l.address,%20l.city,%20l.email,%20l.link,%20l.loc_name,l.mission,l.phone_number,l.state,l.zipcode,%20a.area_name%20FROM%20location%20AS%20l%20JOIN%20lookup_loc_area%20AS%20look_a%20ON%20l.cartodb_id=look_a.loc_id%20JOIN%20area_served%20AS%20a%20ON%20a.cartodb_id=look_a.area_id%20ORDER%20BY%20l.loc_name)%20AS%20doug%20GROUP%20BY%20cartodb_id,%20city,%20email,%20link,%20loc_name,%20mission,%20phone_number,%20state,%20zipcode,%20address%20ORDER%20BY%20cartodb_id)%20AS%20the_areas%20JOIN%20(SELECT%20cartodb_id,%20city,%20email,%20link,%20loc_name,%20mission,%20phone_number,%20state,%20zipcode,%20address,string_agg(type,%27,%27%20ORDER%20BY%20type)%20AS%20types%20FROM%20(SELECT%20l.cartodb_id,%20l.address,%20l.city,%20l.email,%20l.link,%20l.loc_name,l.mission,l.phone_number,l.state,l.zipcode,%20t.type%20FROM%20location%20AS%20l%20JOIN%20lookup_loc_type%20AS%20look_t%20ON%20l.cartodb_id=look_t.loc_id%20JOIN%20type%20AS%20t%20ON%20t.cartodb_id=look_t.type_id)%20AS%20Fred%20GROUP%20BY%20cartodb_id,%20city,%20email,%20link,%20loc_name,%20mission,%20phone_number,%20state,%20zipcode,%20address%20ORDER%20BY%20cartodb_id)%20AS%20the_types%20USING%20(cartodb_id,%20city,%20email,%20link,%20loc_name,%20mission,%20phone_number,%20state,%20zipcode,%20address)
*/
SELECT *
FROM
(SELECT cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address,string_agg(area_name,',' ORDER BY area_name) AS areas
FROM
(SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, a.area_name
FROM
location AS l
JOIN
lookup_loc_area AS look_a ON l.cartodb_id=look_a.loc_id
JOIN
area_served AS a ON a.cartodb_id=look_a.area_id
ORDER BY l.loc_name) AS doug
GROUP BY cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address
ORDER BY cartodb_id)
AS the_areas

JOIN

(SELECT cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address,string_agg(type,',' ORDER BY type) AS types
FROM
(SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, t.type
FROM
location AS l
JOIN
lookup_loc_type AS look_t ON l.cartodb_id=look_t.loc_id
JOIN
type AS t ON t.cartodb_id=look_t.type_id) AS Fred
GROUP BY cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address
ORDER BY cartodb_id)
AS the_types
USING (cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address)

/*SELECTING type and area id's instead of the names */
SELECT *
FROM
(SELECT cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address,string_agg(area_id::text,',' ORDER BY area_id) AS areas
FROM
(SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, look_a.area_id
FROM
location AS l
JOIN
lookup_loc_area AS look_a ON l.cartodb_id=look_a.loc_id) AS doug
GROUP BY cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address
ORDER BY cartodb_id)
AS the_areas

JOIN

(SELECT cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address,string_agg(type_id::text,',' ORDER BY type_id) AS types
FROM
(SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, look_t.type_id
FROM
location AS l
JOIN
lookup_loc_type AS look_t ON l.cartodb_id=look_t.loc_id) AS Fred
GROUP BY cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address
ORDER BY cartodb_id)
AS the_types
USING (cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address)
